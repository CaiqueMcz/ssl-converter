<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Generators;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Generators\PfxGenerator;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

class PfxGeneratorTest extends TestCase
{
    public function testGenerateReturnsPfxData()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, 'test123');
        $pfxData = $generator->generate();

        $this->assertNotEmpty($pfxData);
        $this->assertIsString($pfxData);
    }

    public function testGenerateWithoutCaBundleReturnsPfxData()
    {
        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            null
        );

        $generator = new PfxGenerator($certificateData, 'test123');
        $pfxData = $generator->generate();

        $this->assertNotEmpty($pfxData);
        $this->assertIsString($pfxData);
    }

    public function testGenerateWithEncryptedPrivateKey()
    {
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

        $generator = new PfxGenerator($certificateData, 'pfxpass123');
        $pfxData = $generator->generate();

        $this->assertNotEmpty($pfxData);
        $this->assertIsString($pfxData);
    }

    public function testGenerateThrowsExceptionWithInvalidCertificate()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            'invalid-certificate',
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, 'test123');

        $exceptionThrown = false;
        $errorTriggered = false;

        set_error_handler(function($errno, $errstr) use (&$errorTriggered) {
            if (strpos($errstr, 'X509 certificate') !== false) {
                $errorTriggered = true;
            }
            return true;
        });

        try {
            @$generator->generate();
        } catch (\Exception $e) {
            $exceptionThrown = true;
        } finally {
            restore_error_handler();
        }

        $this->assertTrue(
            $exceptionThrown || $errorTriggered,
            'Expected exception or error was not thrown'
        );
    }

    public function testGenerateThrowsExceptionWithInvalidPrivateKey()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Invalid private key or password');

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData('invalid-private-key'),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, 'test123');
        $generator->generate();
    }

    public function testGenerateThrowsExceptionWithWrongPrivateKeyPassword()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Invalid private key or password');

        $password = 'correctpass';

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
            "organizationName" => "Test Company",
            "commonName" => "example.com"
        ];
        $csr = openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privkey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certificate);

        $certificateData = new CertificateData(
            $certificate,
            new PrivateKeyData($encryptedKey, 'wrongpass'),
            null
        );

        $generator = new PfxGenerator($certificateData, 'pfxpass123');
        $generator->generate();
    }

    public function testGenerateWithLegacyAlgorithmReturnsPfxData()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, 'test123', true);
        $pfxData = $generator->generate();

        $this->assertNotEmpty($pfxData);
        $this->assertIsString($pfxData);
    }

    public function testGeneratedPfxCanBeReadBack()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $password = 'test123';

        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, $password);
        $pfxData = $generator->generate();

        $certs = [];
        $result = openssl_pkcs12_read($pfxData, $certs, $password);

        $this->assertTrue($result);
        $this->assertArrayHasKey('cert', $certs);
        $this->assertArrayHasKey('pkey', $certs);
    }

    public function testGeneratedPfxContainsCertificate()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $password = 'test123';

        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, $password);
        $pfxData = $generator->generate();

        $certs = [];
        openssl_pkcs12_read($pfxData, $certs, $password);

        $this->assertStringContainsString('BEGIN CERTIFICATE', $certs['cert']);
        $this->assertStringContainsString('END CERTIFICATE', $certs['cert']);
    }

    public function testGeneratedPfxContainsPrivateKey()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $password = 'test123';

        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, $password);
        $pfxData = $generator->generate();

        $certs = [];
        openssl_pkcs12_read($pfxData, $certs, $password);

        $this->assertStringContainsString('BEGIN', $certs['pkey']);
        $this->assertStringContainsString('PRIVATE KEY', $certs['pkey']);
    }

    public function testGeneratedPfxContainsCaBundle()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $password = 'test123';

        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $generator = new PfxGenerator($certificateData, $password);
        $pfxData = $generator->generate();

        $certs = [];
        openssl_pkcs12_read($pfxData, $certs, $password);

        $this->assertArrayHasKey('extracerts', $certs);
        $this->assertIsArray($certs['extracerts']);
        $this->assertNotEmpty($certs['extracerts']);
    }
}