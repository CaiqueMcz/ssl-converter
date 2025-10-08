<?php

declare(strict_types=1);

namespace CaiqueMcz\SslConverter;

use CaiqueMcz\SslConverter\Contracts\CertificateFormatInterface;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\Contracts\ConverterInterface;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

class Converter implements ConverterInterface
{
    public function convert(
        CertificateFormatInterface $to
    ): ConversionResponseInterface {
        return $to->convert();
    }

}
