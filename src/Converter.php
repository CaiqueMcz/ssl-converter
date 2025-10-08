<?php

declare(strict_types=1);

namespace SslConverter;

use SslConverter\Contracts\CertificateFormatInterface;
use SslConverter\Contracts\ConversionResponseInterface;
use SslConverter\Contracts\ConverterInterface;

class Converter implements ConverterInterface
{
    public function convert(
        CertificateFormatInterface $to
    ): ConversionResponseInterface {
        return $to->convert();
    }
}
