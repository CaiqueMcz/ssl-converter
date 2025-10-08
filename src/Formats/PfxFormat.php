<?php

namespace SslConverter\Formats;

use SslConverter\Collections\VirtualFileCollection;
use SslConverter\Contracts\CertificateFormatInterface;
use SslConverter\Contracts\ConversionResponseInterface;
use SslConverter\Exceptions\ConversionException;
use SslConverter\Generators\PfxGenerator;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\ConversionResponse;
use SslConverter\ValueObjects\VirtualFile;

class PfxFormat implements CertificateFormatInterface
{
    private CertificateData $certificateData;
    private ?string $pfxPassword = null;
    private bool $useLegacyAlgorithm = false;

    public function __construct(CertificateData $certificateData)
    {
        $this->certificateData = $certificateData;
    }

    public function setPfxPassword(string $password): self
    {
        $this->pfxPassword = $password;
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

    public function convert(): ConversionResponseInterface
    {
        $pfxContent = $this->generatePfx();
        $mainFile = new VirtualFile("certificate.pfx", $pfxContent);
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

        if ($this->pfxPassword === null) {
            throw new ConversionException("PFX password is required");
        }
    }

    public function getName(): string
    {
        return "pfx";
    }

    private function generatePfx(): string
    {
        return (new PfxGenerator($this->certificateData, $this->pfxPassword, $this->useLegacyAlgorithm))->generate();
    }
}
