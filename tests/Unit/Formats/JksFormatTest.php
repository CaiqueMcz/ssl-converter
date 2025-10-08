<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Formats;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Formats\JksFormat;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;

class JksFormatTest extends TestCase
{
    private function isKeytoolAvailable()
    {
        $process = new ProcessUtil(['keytool', '-help']);
        $process->run();
        return $process->isSuccessful();
    }

    public function testGetNameReturnsJks()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);

        $this->assertEquals('jks', $format->getName());
    }

    public function testSetJksPasswordReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $result = $format->setJksPassword('test123');

        $this->assertSame($format, $result);
    }

    public function testWithLegacyAlgorithmReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $result = $format->withLegacyAlgorithm();

        $this->assertSame($format, $result);
    }

    public function testSetUseLegacyAlgorithmReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $result = $format->setUseLegacyAlgorithm(true);

        $this->assertSame($format, $result);
    }

    public function testValidateOrFailThrowsExceptionWhenPrivateKeyIsMissing()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Private key is required for PFX format');

        $data = CertificateFixtures::generateCompleteCertificateData(false);
        $certificateData = new CertificateData(
            $data['certificate'],
            null,
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');
        $format->validateOrFail();
    }

    public function testValidateOrFailThrowsExceptionWhenCertificateIsEmpty()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Certificate is required for PFX format');

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            '',
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');
        $format->validateOrFail();
    }

    public function testValidateOrFailThrowsExceptionWhenPasswordIsNull()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('PFX password is required');

        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $format->validateOrFail();
    }

    public function testValidateOrFailPassesWithAllRequiredData()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');
        $format->validateOrFail();

        $this->assertTrue(true);
    }

    public function testConvertReturnsConversionResponseWithJksFile()
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

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');

        $response = $format->convert();

        $this->assertEquals('certificate.jks', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertReturnsEmptyExtraFiles()
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

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');

        $response = $format->convert();
        $extraFiles = $response->extraVirtualFile()->get();

        $this->assertCount(0, $extraFiles);
    }

    public function testConvertWithLegacyAlgorithmGeneratesJksFile()
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

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');
        $format->withLegacyAlgorithm();

        $response = $format->convert();

        $this->assertEquals('certificate.jks', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertWithEncryptedPrivateKey()
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

        $format = new JksFormat($certificateData);
        $format->setJksPassword('jkspass123');

        $response = $format->convert();

        $this->assertEquals('certificate.jks', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertWithoutCaBundleGeneratesJksFile()
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

        $format = new JksFormat($certificateData);
        $format->setJksPassword('test123');

        $response = $format->convert();

        $this->assertEquals('certificate.jks', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }
}