<?php

namespace CaiqueMcz\SslConverter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Converter;
use CaiqueMcz\SslConverter\Contracts\CertificateFormatInterface;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\Formats\PfxFormat;
class ConverterTest extends TestCase
{
    public function testConvertDelegatesToFormatConvert()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $converter = new Converter();

        $response = $converter->convert($format);

        $this->assertInstanceOf(ConversionResponseInterface::class, $response);
    }

    public function testConvertWithPemFormat()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $converter = new Converter();

        $response = $converter->convert($format);

        $this->assertEquals('fullchain.pem', $response->virtualFile()->getName());
        $this->assertStringContainsString('BEGIN CERTIFICATE', $response->virtualFile()->getContent());
    }

    public function testConvertWithPfxFormat()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword('test123');

        $converter = new Converter();
        $response = $converter->convert($format);

        $this->assertEquals('certificate.pfx', $response->virtualFile()->getName());
        $this->assertNotEmpty($response->virtualFile()->getContent());
    }

    public function testConvertReturnsResponseFromFormat()
    {
        $mockFormat = $this->createMock(CertificateFormatInterface::class);
        $mockResponse = $this->createMock(ConversionResponseInterface::class);

        $mockFormat->expects($this->once())
            ->method('convert')
            ->willReturn($mockResponse);

        $converter = new Converter();
        $result = $converter->convert($mockFormat);

        $this->assertSame($mockResponse, $result);
    }

    public function testConvertCallsFormatConvertOnce()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = $this->getMockBuilder(PemFormat::class)
            ->setConstructorArgs([$certificateData])
            ->onlyMethods(['convert'])
            ->getMock();

        $format->expects($this->once())
            ->method('convert');

        $converter = new Converter();
        $converter->convert($format);
    }
}