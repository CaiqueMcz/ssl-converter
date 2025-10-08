<?php

namespace CaiqueMcz\SslConverter\Formats;

use CaiqueMcz\SslConverter\Collections\VirtualFileCollection;
use CaiqueMcz\SslConverter\Contracts\CertificateFormatInterface;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Generators\JksGenerator;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\ConversionResponse;
use CaiqueMcz\SslConverter\ValueObjects\VirtualFile;

class JksFormat implements CertificateFormatInterface
{
    private CertificateData $certificateData;
    private ?string $jksPassword = null;
    private bool $useLegacyAlgorithm = false;

    public function __construct(CertificateData $certificateData)
    {
        $this->certificateData = $certificateData;
    }

    public function setJksPassword(string $password): self
    {
        $this->jksPassword = $password;
        return $this;
    }

    public function withLegacyAlgorithm(): self
    {
        $this->setUseLegacyAlgorithm(true);
        return $this;
    }

    public function setUseLegacyAlgorithm(bool $useLegacy): self
    {
        $this->useLegacyAlgorithm = $useLegacy;
        return $this;
    }

    /**
     * @throws ConversionException
     */
    public function convert(): ConversionResponseInterface
    {
        $pfxContent = $this->generateJks();
        $mainFile = new VirtualFile("certificate.jks", $pfxContent);
        $extraFiles = new VirtualFileCollection();

        return new ConversionResponse($mainFile, $extraFiles);
    }

    public function validateOrFail(): void
    {
        if (!$this->certificateData->hasPrivateKey()) {
            throw new ConversionException("Private key is required for PFX format");
        }

        if (empty($this->certificateData->getCertificate())) {
            throw new ConversionException("Certificate is required for PFX format");
        }

        if ($this->jksPassword === null) {
            throw new ConversionException("PFX password is required");
        }
    }

    public function getName(): string
    {
        return "jks";
    }

    /**
     * @throws ConversionException
     */
    private function generateJks(): string
    {
        return (new JksGenerator($this->certificateData, $this->jksPassword, 'cert', $this->useLegacyAlgorithm))
            ->generate();
    }
}
