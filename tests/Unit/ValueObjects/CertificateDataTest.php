<?php

namespace SslConverter\Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\PrivateKeyData;

class CertificateDataTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $certificate = "cert-content";
        $privateKeyData = new PrivateKeyData("private-key", "password");
        $caBundle = "ca-bundle-content";

        $data = new CertificateData($certificate, $privateKeyData, $caBundle);

        $this->assertEquals($certificate, $data->getCertificate());
        $this->assertEquals($privateKeyData, $data->getPrivateKeyData());
        $this->assertEquals($caBundle, $data->getCaBundle());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $certificate = "cert-content";

        $data = new CertificateData($certificate);

        $this->assertEquals($certificate, $data->getCertificate());
        $this->assertNull($data->getPrivateKeyData());
        $this->assertNull($data->getCaBundle());
    }

    public function testHasPrivateKeyWithKey(): void
    {
        $privateKeyData = new PrivateKeyData("key");
        $data = new CertificateData("cert", $privateKeyData);

        $this->assertTrue($data->hasPrivateKey());
    }

    public function testHasPrivateKeyWithoutKey(): void
    {
        $data = new CertificateData("cert");

        $this->assertFalse($data->hasPrivateKey());
    }

    public function testHasCaBundleWithBundle(): void
    {
        $data = new CertificateData("cert", null, "ca-bundle");

        $this->assertTrue($data->hasCaBundle());
    }

    public function testHasCaBundleWithoutBundle(): void
    {
        $data = new CertificateData("cert");

        $this->assertFalse($data->hasCaBundle());
    }

    public function testHasCaBundleWithEmptyString(): void
    {
        $data = new CertificateData("cert", null, "");

        $this->assertFalse($data->hasCaBundle());
    }

    public function testNormalizesDoubleLineBreaksInCertificate(): void
    {
        $certificate = "cert\n\ncontent";

        $data = new CertificateData($certificate);

        $this->assertEquals("cert\ncontent", $data->getCertificate());
    }

    public function testNormalizesDoubleLineBreaksInCaBundle(): void
    {
        $caBundle = "ca\n\nbundle";

        $data = new CertificateData("cert", null, $caBundle);

        $this->assertEquals("ca\nbundle", $data->getCaBundle());
    }

    public function testGetCaBundleReturnsNullWhenEmpty(): void
    {
        $data = new CertificateData("cert", null, "");

        $this->assertNull($data->getCaBundle());
    }
}