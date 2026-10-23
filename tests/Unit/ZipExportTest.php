<?php

declare(strict_types=1);

namespace CaiqueMcz\SslConverter\Tests\Unit;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\SslConverter;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\Utils\NormalizerUtil;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ZipExportTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
            if (strpos(basename(dirname($path)), 'ssl_converter_') === 0) {
                rmdir(dirname($path));
            }
        }
    }

    public function testArchiveContainsAllFormatsAndPreservesTheChainAndKey(): void
    {
        $this->requireTools();
        $data = $this->certificateChain('Example.COM');
        $path = $this->export($data);
        self::assertSame(realpath(sys_get_temp_dir()), realpath(dirname(dirname($path))));
        self::assertMatchesRegularExpression('/^Example\.COM_[0-9]{12}\.zip$/', basename($path));
        if (DIRECTORY_SEPARATOR === '/') {
            self::assertSame(0600, fileperms($path) & 0777);
            self::assertSame(0700, fileperms(dirname($path)) & 0777);
        }

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            $expected = [
                'pem/fullchain.pem', 'pem/ca-bundle.pem',
                'pem/certificate.pem', 'pem/private.pem',
                'pfx/certificate.pfx', 'jks/certificate.jks',
                'p12/certificate.p12',
                'crt/ca-bundle.crt', 'crt/certificate.crt',
                'crt/private.key', 'jks/alias.txt',
                'jks/password.txt', 'p12/password.txt',
                'pfx/password.txt',
            ];
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            self::assertEqualsCanonicalizing($expected, $names);
            self::assertSame($data['certificate'], $zip->getFromName($expected[2]));
            self::assertSame($data['ca_bundle'], $zip->getFromName($expected[1]));
            self::assertCount(3, NormalizerUtil::splitCertificates($zip->getFromName($expected[0])));
            $key = $zip->getFromName($expected[3]);
            self::assertStringContainsString('BEGIN ENCRYPTED PRIVATE KEY', $key);
            self::assertFalse(openssl_pkey_get_private($key, 'wrong-password'));
            $resource = openssl_pkey_get_private($key, 'shared-password');
            self::assertNotFalse($resource);
            self::assertTrue(openssl_x509_check_private_key($data['certificate'], $resource));
            self::assertSame($data['certificate'], $zip->getFromName('crt/certificate.crt'));
            self::assertSame($data['ca_bundle'], $zip->getFromName('crt/ca-bundle.crt'));
            self::assertSame($key, $zip->getFromName('crt/private.key'));
            self::assertStringContainsString('BEGIN CERTIFICATE', $zip->getFromName('crt/certificate.crt'));
            foreach (['jks', 'p12', 'pfx'] as $format) {
                self::assertSame('shared-password', $zip->getFromName($format . '/password.txt'));
            }
            $alias = $zip->getFromName('jks/alias.txt');
            self::assertSame('examplecom', $alias);
            self::assertMatchesRegularExpression('/^[a-z0-9]{1,32}$/', $alias);
            $pfx = [];
            self::assertTrue(openssl_pkcs12_read($zip->getFromName($expected[4]), $pfx, 'shared-password'));
            $wrongPasswordData = [];
            self::assertFalse(openssl_pkcs12_read($zip->getFromName($expected[4]), $wrongPasswordData, 'wrong-password'));
            $this->assertSameCertificates($data, $pfx);
            $p12Content = $zip->getFromName('p12/certificate.p12');
            self::assertSame($zip->getFromName($expected[4]), $p12Content);
            $p12 = [];
            self::assertTrue(openssl_pkcs12_read($p12Content, $p12, 'shared-password'));
            $this->assertSameCertificates($data, $p12);

            $jksPath = $this->temporaryFile($zip->getFromName($expected[5]));
            $listing = new ProcessUtil([
                'keytool', '-list', '-rfc', '-keystore', $jksPath, '-storetype', 'jks',
                '-storepass', 'shared-password', '-alias', $alias,
            ]);
            $listing->run();
            self::assertTrue($listing->isSuccessful());
            $chain = NormalizerUtil::splitCertificates($listing->getOutput());
            self::assertCount(3, $chain);
            $expectedChain = NormalizerUtil::splitCertificates($data['certificate'] . $data['ca_bundle']);
            foreach ($expectedChain as $index => $certificate) {
                self::assertSame(openssl_x509_fingerprint($certificate), openssl_x509_fingerprint($chain[$index]));
            }
            $roundTrip = $this->temporaryFile('');
            unlink($roundTrip);
            $process = new ProcessUtil([
                'keytool', '-importkeystore', '-srckeystore', $jksPath, '-srcstoretype', 'jks',
                '-srcstorepass', 'shared-password', '-srcalias', 'examplecom',
                '-destkeystore', $roundTrip, '-deststoretype', 'pkcs12',
                '-deststorepass', 'shared-password', '-noprompt',
            ]);
            $process->run();
            self::assertTrue($process->isSuccessful(), 'JKS must open with the shared password and derived alias.');
            $jks = [];
            self::assertTrue(openssl_pkcs12_read(file_get_contents($roundTrip), $jks, 'shared-password'));
            $this->assertSameCertificates($data, $jks);
        } finally {
            $zip->close();
        }
    }

    public function testEncryptedInputKeyIsExportedWithTheSharedPassword(): void
    {
        $this->requireTools();
        $data = $this->certificateChain('*.Example.COM');
        openssl_pkey_export($data['private_key'], $encrypted, 'input-password');
        $data['private_key'] = $encrypted;
        $path = $this->export($data, 'input-password');
        self::assertMatchesRegularExpression('/^_\.Example\.COM_[0-9]{12}\.zip$/', basename($path));
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            $key = $zip->getFromName('pem/private.pem');
            self::assertNotFalse($key);
            self::assertNotFalse(openssl_pkey_get_private($key, 'shared-password'));
            self::assertNotSame($encrypted, $key);
            self::assertSame($key, $zip->getFromName('crt/private.key'));
            self::assertSame('examplecom', $zip->getFromName('jks/alias.txt'));
        } finally {
            $zip->close();
        }
    }

    public function testZipEncryptionIsOptionalAndUsesTheSharedPassword(): void
    {
        $this->requireTools();
        $data = $this->certificateChain('example.com');
        $path = $this->export($data, null, 'shared-password', true);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            self::assertSame(14, $zip->numFiles);
            for ($index = 0; $index < $zip->numFiles; $index++) {
                self::assertFalse($zip->getFromIndex($index), 'Every entry must require the ZIP password.');
            }
            self::assertTrue($zip->setPassword('wrong-password'));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                self::assertFalse($zip->getFromIndex($index));
            }
            self::assertTrue($zip->setPassword('shared-password'));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                self::assertNotFalse($zip->getFromIndex($index));
            }
            self::assertSame($data['certificate'], $zip->getFromName('pem/certificate.pem'));
            foreach (['jks', 'p12', 'pfx'] as $format) {
                self::assertSame('shared-password', $zip->getFromName($format . '/password.txt'));
            }
            self::assertSame('examplecom', $zip->getFromName('jks/alias.txt'));
            $p12 = [];
            self::assertTrue(openssl_pkcs12_read(
                $zip->getFromName('p12/certificate.p12'),
                $p12,
                'shared-password'
            ));
            $this->assertSameCertificates($data, $p12);
        } finally {
            $zip->close();
        }
    }

    public function testRepeatedExportsHaveDifferentPaths(): void
    {
        $this->requireTools();
        $data = $this->certificateChain('example.com');
        $first = $this->export($data);
        $second = $this->export($data);
        self::assertNotSame($first, $second);
        self::assertNotSame(basename($first), basename($second));
        self::assertMatchesRegularExpression('/^example\.com_[0-9]{12}\.zip$/', basename($first));
        self::assertMatchesRegularExpression('/^example\.com_[0-9]{12}\.zip$/', basename($second));
        self::assertFileExists($first);
        self::assertFileExists($second);
    }

    /** @dataProvider zipEncryptionModes */
    public function testLegacyPkcs12ExportPreservesMaterial(bool $encryptZip): void
    {
        $this->requireTools();
        $data = $this->certificateChain('example.com');
        $path = $this->export($data, null, 'shared-password', $encryptZip, true);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            if ($encryptZip) {
                self::assertTrue($zip->setPassword('shared-password'));
            }
            $pfx = $zip->getFromName('pfx/certificate.pfx');
            self::assertSame($pfx, $zip->getFromName('p12/certificate.p12'));
            $pfxPath = $this->temporaryFile($pfx);
            $info = new ProcessUtil([
                'openssl', 'pkcs12', '-legacy', '-info', '-noout',
                '-in', $pfxPath, '-passin', 'pass:shared-password',
            ]);
            $info->run();
            self::assertTrue($info->isSuccessful());
            self::assertStringContainsString('pbeWithSHA1And3-KeyTripleDES-CBC', $info->getErrorOutput());

            $read = new ProcessUtil([
                'openssl', 'pkcs12', '-legacy', '-in', $pfxPath,
                '-passin', 'pass:shared-password', '-passout', 'pass:shared-password',
            ]);
            $read->run();
            self::assertTrue($read->isSuccessful());
            $certificates = NormalizerUtil::splitCertificates($read->getOutput());
            $fingerprint = static function (string $certificate): string {
                return openssl_x509_fingerprint($certificate);
            };
            self::assertEqualsCanonicalizing(
                array_map($fingerprint, NormalizerUtil::splitCertificates($data['certificate'] . $data['ca_bundle'])),
                array_map($fingerprint, $certificates)
            );
            self::assertSame(1, preg_match(
                '/-----BEGIN ENCRYPTED PRIVATE KEY-----.*?-----END ENCRYPTED PRIVATE KEY-----/s',
                $read->getOutput(),
                $matches
            ));
            $key = openssl_pkey_get_private($matches[0], 'shared-password');
            self::assertNotFalse($key);
            self::assertTrue(openssl_x509_check_private_key($data['certificate'], $key));
            self::assertSame('shared-password', $zip->getFromName('pfx/password.txt'));
            self::assertSame('shared-password', $zip->getFromName('p12/password.txt'));
        } finally {
            $zip->close();
        }
    }

    public function zipEncryptionModes(): array
    {
        return ['unencrypted ZIP' => [false], 'encrypted ZIP' => [true]];
    }

    /** @dataProvider certificateOnlyModes */
    public function testWithoutPrivateKeyExportsOnlyCertificateDirectories(bool $encryptZip, bool $legacy): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not available');
        }
        $data = $this->certificateChain('example.com');
        $data['private_key'] = null;
        $previousPath = getenv('PATH');
        putenv('PATH=/nonexistent-ssl-converter-test');
        try {
            $path = $this->export($data, null, 'shared-password', $encryptZip, $legacy);
        } finally {
            putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
        }
        self::assertMatchesRegularExpression('/^example\.com_[0-9]{12}\.zip$/', basename($path));
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            $expected = [
                'crt/certificate.crt', 'crt/ca-bundle.crt',
                'pem/certificate.pem', 'pem/ca-bundle.pem', 'pem/fullchain.pem',
            ];
            $names = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $names[] = $zip->getNameIndex($index);
                if ($encryptZip) {
                    self::assertFalse($zip->getFromIndex($index));
                }
            }
            self::assertEqualsCanonicalizing($expected, $names);
            if ($encryptZip) {
                self::assertTrue($zip->setPassword('shared-password'));
            }
            foreach (['crt/certificate.crt', 'pem/certificate.pem'] as $name) {
                self::assertSame($data['certificate'], $zip->getFromName($name));
            }
            foreach (['crt/ca-bundle.crt', 'pem/ca-bundle.pem'] as $name) {
                self::assertSame($data['ca_bundle'], $zip->getFromName($name));
            }
            $fullchain = NormalizerUtil::splitCertificates($zip->getFromName('pem/fullchain.pem'));
            self::assertSame(
                NormalizerUtil::splitCertificates($data['certificate'] . $data['ca_bundle']),
                $fullchain
            );
        } finally {
            $zip->close();
        }
    }

    public function certificateOnlyModes(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function testWithoutPrivateKeyDoesNotRequireAValidJksAlias(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not available');
        }
        $data = $this->certificateChain('---');
        $data['private_key'] = null;
        $path = $this->export($data);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        try {
            self::assertSame(5, $zip->numFiles);
            self::assertSame($data['certificate'], $zip->getFromName('crt/certificate.crt'));
        } finally {
            $zip->close();
        }
    }

    public function testKeytoolFailureDoesNotLeakSecretsOrLeaveTemporaryFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not available');
        }
        $data = $this->certificateChain('example.com');
        $before = array_merge(glob(sys_get_temp_dir() . '/ssl_converter_*'), glob(sys_get_temp_dir() . '/jks_*'));
        $previousPath = getenv('PATH');
        putenv('PATH=/nonexistent-ssl-converter-test');
        try {
            $this->export($data);
            self::fail('Conversion must fail without keytool.');
        } catch (ConversionException $exception) {
            self::assertStringNotContainsString('shared-password', (string) $exception);
            self::assertStringContainsString('keytool', $exception->getMessage());
        } finally {
            putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
        }
        self::assertSame(
            $before,
            array_merge(glob(sys_get_temp_dir() . '/ssl_converter_*'), glob(sys_get_temp_dir() . '/jks_*'))
        );
    }

    public function testLegacyFailureDoesNotLeakSecretsOrLeaveTemporaryFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not available');
        }
        $data = $this->certificateChain('example.com');
        $temporaryFiles = static function (): array {
            $files = [];
            foreach (['ssl_converter_', 'jks_', 'pfx_', 'pem_', 'legacy_'] as $prefix) {
                $files = array_merge($files, glob(sys_get_temp_dir() . '/' . $prefix . '*'));
            }
            return $files;
        };
        $before = $temporaryFiles();
        $previousPath = getenv('PATH');
        putenv('PATH=/nonexistent-ssl-converter-test');
        try {
            $this->export($data, null, 'shared-password', false, true);
            self::fail('Legacy conversion must fail without OpenSSL CLI.');
        } catch (ConversionException $exception) {
            self::assertStringContainsString('OpenSSL', $exception->getMessage());
            self::assertStringNotContainsString('shared-password', $exception->getMessage());
            self::assertStringNotContainsString('BEGIN PRIVATE KEY', $exception->getMessage());
        } finally {
            putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
        }
        self::assertSame($before, $temporaryFiles());
    }

    public function testMissingZipExtensionProducesADomainException(): void
    {
        $code = <<<'PHP'
require $argv[1];
try {
    (new \CaiqueMcz\SslConverter\SslConverter('unused'))->toZip('shared-password');
    exit(1);
} catch (\CaiqueMcz\SslConverter\Exceptions\ConversionException $exception) {
    echo $exception->getMessage();
}
PHP;
        $process = new ProcessUtil([PHP_BINARY, '-n', '-r', $code, dirname(__DIR__, 2) . '/vendor/autoload.php']);
        $process->run();
        self::assertTrue($process->isSuccessful());
        self::assertSame('The ZIP extension is required to generate an archive.', $process->getOutput());
    }

    public function testZipEncryptionNeverFallsBackWhenAesSupportIsUnavailable(): void
    {
        $code = <<<'PHP'
class ZipArchive {}
require $argv[1];
try {
    (new \CaiqueMcz\SslConverter\SslConverter('unused'))->toZip('shared-password', true);
    exit(1);
} catch (\CaiqueMcz\SslConverter\Exceptions\ConversionException $exception) {
    echo $exception->getMessage();
}
PHP;
        $process = new ProcessUtil([PHP_BINARY, '-n', '-r', $code, dirname(__DIR__, 2) . '/vendor/autoload.php']);
        $process->run();
        self::assertTrue($process->isSuccessful());
        self::assertSame('ZIP encryption requires an extension with AES-256 support.', $process->getOutput());
    }

    public function testCommonNameCannotCreateNestedOrParentDirectories(): void
    {
        $this->requireTools();
        $data = $this->certificateChain('../evil/name');
        $zip = new ZipArchive();
        $path = $this->export($data);
        self::assertMatchesRegularExpression('/^_evil_name_[0-9]{12}\.zip$/', basename($path));
        self::assertTrue($zip->open($path));
        try {
            self::assertNotFalse($zip->getFromName('pem/fullchain.pem'));
            for ($index = 0; $index < $zip->numFiles; $index++) {
                self::assertStringNotContainsString('../', $zip->getNameIndex($index));
                self::assertCount(2, explode('/', $zip->getNameIndex($index)));
            }
        } finally {
            $zip->close();
        }
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputFailsWithoutLeavingTemporaryFiles(string $case, bool $withPrivateKey = true): void
    {
        $data = $this->certificateChain($case === 'missing-cn' ? null : 'example.com');
        if (!$withPrivateKey) {
            $data['private_key'] = null;
        }
        $password = 'shared-password';
        $keyPassword = null;
        switch ($case) {
            case 'invalid-key': $data['private_key'] = 'invalid-private-key'; break;
            case 'empty-key': $data['private_key'] = ''; break;
            case 'missing-bundle': $data['ca_bundle'] = null; break;
            case 'invalid-certificate': $data['certificate'] = 'invalid'; break;
            case 'invalid-bundle': $data['ca_bundle'] = 'invalid'; break;
            case 'truncated-bundle': $data['ca_bundle'] .= "\n-----BEGIN CERTIFICATE-----\n"; break;
            case 'mismatched-key': $data['private_key'] = CertificateFixtures::generatePrivateKey(); break;
            case 'wrong-key-password':
                openssl_pkey_export($data['private_key'], $encrypted, 'correct-password');
                $data['private_key'] = $encrypted;
                $keyPassword = 'wrong-password';
                break;
            case 'empty-password': $password = ''; break;
            case 'short-password': $password = 'short'; break;
        }
        $before = glob(sys_get_temp_dir() . '/ssl_converter_*');
        try {
            $this->export($data, $keyPassword, $password);
            self::fail('Invalid input must fail.');
        } catch (ConversionException $exception) {
            self::assertStringNotContainsString('shared-password', $exception->getMessage());
            self::assertStringNotContainsString('wrong-password', $exception->getMessage());
            self::assertStringNotContainsString('BEGIN PRIVATE KEY', $exception->getMessage());
        }
        self::assertSame($before, glob(sys_get_temp_dir() . '/ssl_converter_*'));
    }

    public function invalidInputs(): array
    {
        $inputs = [];
        foreach ([
            'invalid-key', 'empty-key', 'missing-bundle', 'missing-cn', 'invalid-certificate', 'invalid-bundle',
            'truncated-bundle', 'mismatched-key', 'wrong-key-password', 'empty-password', 'short-password',
        ] as $case) {
            $inputs[$case] = [$case, true];
        }
        foreach ([
            'missing-bundle', 'missing-cn', 'invalid-certificate', 'invalid-bundle',
            'truncated-bundle', 'empty-password', 'short-password',
        ] as $case) {
            $inputs[$case . ' without key'] = [$case, false];
        }
        return $inputs;
    }

    private function export(
        array $data,
        ?string $keyPassword = null,
        string $password = 'shared-password',
        bool $encryptZip = false,
        bool $useLegacyAlgorithm = false
    ): string {
        $converter = new SslConverter($data['certificate']);
        if ($data['private_key'] !== null) {
            $converter->withPrivateKey($data['private_key'], $keyPassword);
        }
        if ($data['ca_bundle'] !== null) {
            $converter->withCaBundle($data['ca_bundle']);
        }
        $path = $converter->toZip($password, $encryptZip, $useLegacyAlgorithm);
        $this->paths[] = $path;
        return $path;
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip_test_');
        $this->paths[] = $path;
        file_put_contents($path, $contents);
        return $path;
    }

    private function requireTools(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ext-zip is not available');
        }
        $process = new ProcessUtil(['keytool', '-help']);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->markTestSkipped('keytool is not available');
        }
    }

    private function assertSameCertificates(array $data, array $container): void
    {
        self::assertSame(openssl_x509_fingerprint($data['certificate']), openssl_x509_fingerprint($container['cert']));
        self::assertTrue(openssl_x509_check_private_key($container['cert'], $container['pkey']));
        $fingerprint = static function (string $certificate): string {
            return openssl_x509_fingerprint($certificate);
        };
        self::assertEqualsCanonicalizing(
            array_map($fingerprint, NormalizerUtil::splitCertificates($data['ca_bundle'])),
            array_map($fingerprint, $container['extracerts'])
        );
    }

    private function certificateChain(?string $commonName): array
    {
        $rootKey = CertificateFixtures::generatePrivateKey();
        $rootCsr = openssl_csr_new(['commonName' => 'ZIP Test Root'], $rootKey);
        $root = openssl_csr_sign($rootCsr, null, $rootKey, 30, ['digest_alg' => 'sha256']);
        $intermediateKey = CertificateFixtures::generatePrivateKey();
        $intermediateCsr = openssl_csr_new(['commonName' => 'ZIP Test Intermediate'], $intermediateKey);
        $intermediate = openssl_csr_sign($intermediateCsr, $root, $rootKey, 30, ['digest_alg' => 'sha256']);
        $privateKey = CertificateFixtures::generatePrivateKey();
        $subject = $commonName === null ? ['organizationName' => 'No CN'] : ['commonName' => $commonName];
        $csr = openssl_csr_new($subject, $privateKey);
        $leaf = openssl_csr_sign($csr, $intermediate, $intermediateKey, 30, ['digest_alg' => 'sha256']);
        openssl_x509_export($leaf, $certificate);
        openssl_x509_export($intermediate, $intermediatePem);
        openssl_x509_export($root, $rootPem);
        return ['certificate' => $certificate, 'private_key' => $privateKey, 'ca_bundle' => $intermediatePem . $rootPem];
    }
}
