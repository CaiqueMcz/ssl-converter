<?php

declare(strict_types=1);

namespace SslConverter\Contracts;

interface ConverterInterface
{
    public function convert(
        CertificateFormatInterface $to
    ): ConversionResponseInterface;
}
