<?php

namespace CaiqueMcz\SslConverter\Formats;

use CaiqueMcz\SslConverter\Collections\VirtualFileCollection;
use CaiqueMcz\SslConverter\Contracts\CertificateFormatInterface;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Utils\NormalizerUtil;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\ConversionResponse;
use CaiqueMcz\SslConverter\ValueObjects\VirtualFile;

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
        $extraFiles->add(new VirtualFile("certificate.pem", $this->certificateData->getCertificate()));
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
