<?php

namespace CaiqueMcz\SslConverter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\SslConverter;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;

class SslConverterTest extends TestCase
{
    private function isKeytoolAvailable()
    {
        $process = new ProcessUtil(['keytool', '-help']);
        $process->run();
        return $process->isSuccessful();
    }

    public function testConstructorAcceptsCertificate()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $converter = new SslConverter($data['certificate']);

        $this->assertInstanceOf(SslConverter::class, $converter);
    }

    public function testWithCaBundleReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $converter = new SslConverter($data['certificate']);
        $result = $converter->withCaBundle($data['ca_bundle']);

        $this->assertSame($converter, $result);
    }

    public function testWithPrivateKeyReturnsSelf()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $converter = new SslConverter($data['certificate']);
        $result = $converter->withPrivateKey($data['private_key']);

        $this->assertSame($converter, $result);
    }

    public function testToPemReturnsConversionResponse()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toPem();

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
        $this->assertEquals('fullchain.pem', $response->virtualFile()->getName());
    }

    public function testToPemWithoutPrivateKey()
    {
        $data = CertificateFixtures::generateCompleteCertificateData(false);

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->toPem();

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
        $extraFiles = $response->extraVirtualFile()->get();
        $this->assertCount(1, $extraFiles);
    }

    public function testToPemWithEncryptedPrivateKey()
    {
        $password = 'test123';
        $data = CertificateFixtures::generateCompleteCertificateData(true, true, true, $password);

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'], $password)
            ->toPem();

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testToPfxReturnsConversionResponse()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toPfx('pfx-password');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testToPfxWithoutCaBundle()
    {
        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);

        $response = (new SslConverter($data['certificate']))
            ->withPrivateKey($data['private_key'])
            ->toPfx('pfx-password');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testToPfxWithLegacyAlgorithm()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toPfx('pfx-password', true);

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testToPfxWithEncryptedPrivateKey()
    {
        $keyPassword = 'key123';

        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        $encryptedKey = '';
        openssl_pkey_export($res, $encryptedKey, $keyPassword);

        $privkey = openssl_pkey_get_private($encryptedKey, $keyPassword);
        $dn = [
            "countryName" => "BR",
            "organizationName" => "Test Company",
            "commonName" => "example.com"
        ];
        $csr = openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privkey, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certificate);

        $response = (new SslConverter($certificate))
            ->withPrivateKey($encryptedKey, $keyPassword)
            ->toPfx('pfx-password');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testToJksReturnsConversionResponse()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toJks('jks-password');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
        $this->assertEquals('certificate.jks', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testToJksWithCustomAlias()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toJks('jks-password', 'mykey');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testToJksWithLegacyAlgorithm()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData();

        $response = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key'])
            ->toJks('jks-password', 'certificate', true);

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testToJksWithoutCaBundle()
    {
        if (!$this->isKeytoolAvailable()) {
            $this->markTestSkipped('keytool is not available');
        }

        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);

        $response = (new SslConverter($data['certificate']))
            ->withPrivateKey($data['private_key'])
            ->toJks('jks-password');

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testFluentInterfaceChaining()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();

        $converter = (new SslConverter($data['certificate']))
            ->withCaBundle($data['ca_bundle'])
            ->withPrivateKey($data['private_key']);

        $this->assertInstanceOf(SslConverter::class, $converter);
    }
}