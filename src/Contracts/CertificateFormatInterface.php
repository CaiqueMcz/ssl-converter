<?php

declare(strict_types=1);

namespace SslConverter\Contracts;

use SslConverter\ValueObjects\CertificateData;

interface CertificateFormatInterface
{
    public function __construct(CertificateData $certificateData);

    public function convert(): ConversionResponseInterface;

    public function validateOrFail(): void;

    public function getName(): string;
}
