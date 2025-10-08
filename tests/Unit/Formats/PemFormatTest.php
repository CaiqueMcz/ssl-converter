<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Formats;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\Tests\Fixtures\CertificateFixtures;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

class PemFormatTest extends TestCase
{
    public function testGetNameReturnsPem()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);

        $this->assertEquals('pem', $format->getName());
    }

    public function testValidateOrFailThrowsExceptionWhenCaBundleIsMissing()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('CA bundle is required');

        $data = CertificateFixtures::generateCompleteCertificateData(true, false, false);
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key'])
        );

        $format = new PemFormat($certificateData);
        $format->validateOrFail();
    }

    public function testValidateOrFailPassesWhenCaBundleExists()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $format->validateOrFail();

        $this->assertTrue(true);
    }

    public function testGetFullChainCombinesCertificateAndCaBundle()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $fullChain = $format->getFullChain();

        $this->assertStringContainsString('BEGIN CERTIFICATE', $fullChain);
        $this->assertStringContainsString('END CERTIFICATE', $fullChain);
    }

    public function testGetFullChainNormalizesDoubleLineBreaks()
    {
        $certificate = "-----BEGIN CERTIFICATE-----\n\nCERT\n\n-----END CERTIFICATE-----";
        $caBundle = "-----BEGIN CERTIFICATE-----\n\nCA\n\n-----END CERTIFICATE-----";

        $certificateData = new CertificateData($certificate, null, $caBundle);
        $format = new PemFormat($certificateData);
        $fullChain = $format->getFullChain();

        $this->assertStringNotContainsString("\n\n\n", $fullChain);
    }

    public function testConvertReturnsConversionResponseWithMainFile()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $response = $format->convert();

        $this->assertEquals('fullchain.pem', $response->virtualFile()->getName());
        $this->assertStringContainsString('BEGIN CERTIFICATE', $response->virtualFile()->getContent());
    }

    public function testConvertIncludesCaBundleInExtraFiles()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $response = $format->convert();

        $extraFiles = $response->extraVirtualFile()->get();
        $this->assertCount(3, $extraFiles);

        $caBundleFile = $extraFiles[0];
        $this->assertEquals('ca-bundle.pem', $caBundleFile->getName());
        $this->assertStringContainsString('BEGIN CERTIFICATE', $caBundleFile->getContent());
    }

    public function testConvertIncludesPrivateKeyInExtraFilesWhenPresent()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $response = $format->convert();

        $extraFiles = $response->extraVirtualFile()->get();
        $this->assertCount(3, $extraFiles);

        $privateKeyFile = $extraFiles[2];
        $this->assertEquals('private.pem', $privateKeyFile->getName());
        $this->assertStringContainsString('BEGIN', $privateKeyFile->getContent());
    }

    public function testConvertWithoutPrivateKeyOnlyIncludesCaBundle()
    {
        $data = CertificateFixtures::generateCompleteCertificateData(false);
        $certificateData = new CertificateData(
            $data['certificate'],
            null,
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $response = $format->convert();

        $extraFiles = $response->extraVirtualFile()->get();
        $this->assertCount(2, $extraFiles);
        $this->assertEquals('ca-bundle.pem', $extraFiles[0]->getName());
    }

    public function testConvertReturnsAllThreeFilesWhenPrivateKeyPresent()
    {
        $data = CertificateFixtures::generateCompleteCertificateData();
        $certificateData = new CertificateData(
            $data['certificate'],
            new PrivateKeyData($data['private_key']),
            $data['ca_bundle']
        );

        $format = new PemFormat($certificateData);
        $response = $format->convert();

        $allFiles = $response->getAllVirtualFiles()->get();
        $this->assertCount(4, $allFiles);

        $fileNames = array_map(function ($file) {
            return $file->getName();
        }, $allFiles);

        $this->assertEquals(['fullchain.pem', 'ca-bundle.pem','certificate.pem', 'private.pem'], $fileNames);
    }
}