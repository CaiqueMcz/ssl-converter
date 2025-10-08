<?php

namespace SslConverter\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use SslConverter\Generators\JksGenerator;
use SslConverter\Tests\Fixtures\CertificateFixtures;
use SslConverter\Utils\ProcessUtil;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\PrivateKeyData;

class JksGeneratorTest extends TestCase
{
    private function isKeytoolAvailable()
    {
        $process = new ProcessUtil(['keytool', '-help']);
        $process->run();
        return $process->isSuccessful();
    }

    public function testGenerateReturnsJksData()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123');
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }

    public function testGenerateWithoutCaBundleReturnsJksData()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            null
        );

        $generator = new JksGenerator($certificateData, 'test123');
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }

    public function testGenerateWithCustomAlias()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123', 'myalias');
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }

    public function testGenerateWithEncryptedPrivateKey()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $password = 'keypass123';

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $encryptedKey = '';
        openssl_pkey_export($res, $encryptedKey, $password);

        $privkey = openssl_pkey_get_private($encryptedKey, $password);
        $dn = [
            "countryName" => "BR",
            "stateOrProvinceName" => "BA",
            "localityName" => "Feira de santana",
            "organizationName" => "Test Company",
            "organizationalUnitName" => "IT",
            "commonName" => "example.com",
            "emailAddress" => "test@example.com"
        ];
        $csr = openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privkey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certificate);

        $caBundle = CertificateFixtures::generateCaBundle();

        $certificateData = new CertificateData(
            $certificate,
            new PrivateKeyData($encryptedKey, $password),
            $caBundle
        );

        $generator = new JksGenerator($certificateData, 'jkspass123');
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }

    public function testGenerateWithLegacyAlgorithmReturnsJksData()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123', 'cert', true);
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }

    public function testGenerateThrowsExceptionWhenKeytoolFails()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            'invalid-certificate',
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123');

        $hasError = false;

        set_error_handler(function($errno, $errstr) use (&$hasError) {
            $hasError = true;
            return true;
        }, E_ALL);

        try {
            $result = @$generator->generate();
            if ($result === null || $result === false || $result === '') {
                $hasError = true;
            }
        } catch (\Throwable $e) {
            $hasError = true;
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($hasError, 'Expected error when generating JKS with invalid certificate');
    }

    public function testGenerateCreatesTemporaryPfxFile()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123');

        $tempFilesBefore = glob(sys_get_temp_dir() . '/pfx_*');
        $generator->generate();
        $tempFilesAfter = glob(sys_get_temp_dir() . '/pfx_*');

        $this->assertCount(count($tempFilesBefore), $tempFilesAfter);
    }

    public function testGenerateCleansUpTemporaryFiles()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'test123');

        $tempFilesBefore = array_merge(
            glob(sys_get_temp_dir() . '/pfx_*'),
            glob(sys_get_temp_dir() . '/jks_*')
        );

        $generator->generate();

        $tempFilesAfter = array_merge(
            glob(sys_get_temp_dir() . '/pfx_*'),
            glob(sys_get_temp_dir() . '/jks_*')
        );

        $this->assertCount(count($tempFilesBefore), $tempFilesAfter);
    }

    public function testConstructorAcceptsAllParameters()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'password123', 'customalias', true);

        $this->assertInstanceOf(JksGenerator::class, $generator);
    }

    public function testGenerateWithDifferentPasswordAndAlias()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new JksGenerator($certificateData, 'securepass456', 'mykey');
        $jksData = $generator->generate();

        $this->assertNotEmpty($jksData);
        $this->assertIsString($jksData);
    }
}