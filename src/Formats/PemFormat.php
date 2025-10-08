<?php

namespace SslConverter\Formats;

use SslConverter\Collections\VirtualFileCollection;
use SslConverter\Contracts\CertificateFormatInterface;
use SslConverter\Contracts\ConversionResponseInterface;
use SslConverter\Exceptions\ConversionException;
use SslConverter\Utils\NormalizerUtil;
use SslConverter\ValueObjects\CertificateData;
use SslConverter\ValueObjects\ConversionResponse;
use SslConverter\ValueObjects\VirtualFile;

class PemFormat implements CertificateFormatInterface
{
    private CertificateData $certificateData;

    public function __construct(CertificateData $certificateData)
    {
        $this->certificateData = $certificateData;
    }

    public function convert(): ConversionResponseInterface
    {
        $fullChain = new VirtualFile("fullchain.pem", $this->getFullChain());
        $extraFiles = new VirtualFileCollection();
        $extraFiles->add(new VirtualFile("ca-bundle.pem", $this->certificateData->getCaBundle()));
        if ($this->certificateData->hasPrivateKey()) {
            $extraFiles->add(new VirtualFile("private.pem", $this->certificateData->getPrivateKeyData()
                ->getPrivateKey()));
        }
        return new ConversionResponse($fullChain, $extraFiles);
    }

    public function validateOrFail(): void
    {
        if (empty($this->certificateData->getCaBundle())) {
            throw new ConversionException("CA bundle is required");
        }
    }

    public function getFullChain(): string
    {
        $fullChain = $this->certificateData->getCertificate() . PHP_EOL . $this->certificateData->getCaBundle();
        return NormalizerUtil::removeDoubleLineBreaks($fullChain);
    }

    public function getName(): string
    {
        return "pem";
    }
}
