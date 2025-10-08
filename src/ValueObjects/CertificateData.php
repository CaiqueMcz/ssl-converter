<?php

declare(strict_types=1);

namespace SslConverter\ValueObjects;

use SslConverter\Contracts\CertificateDataInterface;
use SslConverter\Contracts\PrivateKeyDataInterface;
use SslConverter\Utils\NormalizerUtil;

final class CertificateData implements CertificateDataInterface
{
    private string $certificate;
    private ?PrivateKeyDataInterface $privateKeyData;
    private ?string $caBundle;


    public function __construct(
        string          $certificate,
        ?PrivateKeyData $privateKeyData = null,
        ?string         $caBundle = null
    )
    {
        $this->certificate = $certificate;
        $this->privateKeyData = $privateKeyData;
        $this->caBundle = $caBundle;
    }

    public function getCertificate(): string
    {
        return NormalizerUtil::removeDoubleLineBreaks($this->certificate);
    }

    public function getPrivateKeyData(): ?PrivateKeyDataInterface
    {
        return $this->privateKeyData;
    }

    public function getCaBundle(): ?string
    {
        if (empty($this->caBundle)) {
            return null;
        }
        return NormalizerUtil::removeDoubleLineBreaks($this->caBundle);
    }


    public function hasPrivateKey(): bool
    {
        return $this->privateKeyData !== null;
    }

    public function hasCaBundle(): bool
    {
        return !empty($this->caBundle);
    }
}
