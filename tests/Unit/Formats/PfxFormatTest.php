<?php

namespace SslConverter\Tests\Unit\Formats;

use PHPUnit\Framework\TestCase;
use SslConverter\Exceptions\ConversionException;
use SslConverter\Formats\PfxFormat;
use SslConverter\Tests\Fixtures\CertificateFixtures;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\PrivateKeyData;

class PfxFormatTest extends TestCase
{
    public function testGetNameReturnsPfx()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);

        $this->assertEquals('pfx', $format->getName());
    }

    public function testSetPfxPasswordReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);
        $result = $format->setPfxPassword('test123');

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

        $format = new PfxFormat($certificateData);
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

        $format = new PfxFormat($certificateData);
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

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');
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

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');
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

        $format = new PfxFormat($certificateData);
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

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');
        $format->validateOrFail();

        $this->assertTrue(true);
    }

    public function testConvertReturnsConversionResponseWithPfxFile()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');

        $response = $format->convert();

        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertReturnsEmptyExtraFiles()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');

        $response = $format->convert();
        $extraFiles = $response->extraVirtualFile()->get();

        $this->assertCount(0, $extraFiles);
    }

    public function testConvertWithLegacyAlgorithmGeneratesPfxFile()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');
        $format->withLegacyAlgorithm();

        $response = $format->convert();

        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertWithEncryptedPrivateKey()
    {
        $password = 'keypass123';

        $unencryptedKey = CertificateFixtures::generatePrivateKey(false);
        $certificate = CertificateFixtures::generateSelfSignedCertificate($unencryptedKey);
        $caBundle = CertificateFixtures::generateCaBundle();

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $encryptedKey = '';
        openssl_pkey_export($res, $encryptedKey, $password);
        $certFromEncrypted = '';

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
        openssl_x509_export($x509, $certFromEncrypted);

        $certificateData = new CertificateData(
            $certFromEncrypted,
            new PrivateKeyData($encryptedKey, $password),
            $caBundle
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('pfxpass123');

        $response = $format->convert();

        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertWithoutCaBundleGeneratesPfxFile()
    {
        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            null
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');

        $response = $format->convert();

        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }
}