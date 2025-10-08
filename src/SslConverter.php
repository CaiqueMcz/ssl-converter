<?php

namespace CaiqueMcz\SslConverter;

use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;
use CaiqueMcz\SslConverter\Formats\JksFormat;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\Formats\PfxFormat;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

class SslConverter
{
    private string $certificate;
    private ?string $caBundle = null;
    private ?string $privateKey = null;
    private ?string $privateKeyPassword = null;

    public function __construct(string $certificate)
    {
        $this->certificate = $certificate;
    }

    public function withCaBundle(string $caBundle): self
    {
        $this->caBundle = $caBundle;
        return $this;
    }

    public function withPrivateKey(string $privateKey, ?string $password = null): self
    {
        $this->privateKey = $privateKey;
        $this->privateKeyPassword = $password;
        return $this;
    }

    public function toPem(): ConversionResponseInterface
    {
        $certificateData = $this->buildCertificateData();
        $format = new PemFormat($certificateData);
        return (new Converter())->convert($format);
    }

    public function toPfx(string $password, bool $useLegacyAlgorithm = false): ConversionResponseInterface
    {
        $certificateData = $this->buildCertificateData();

        $format = new PfxFormat($certificateData);
        $format->setPfxPassword($password);

        if ($useLegacyAlgorithm) {
            $format->withLegacyAlgorithm();
        }

        return (new Converter())->convert($format);
    }

    public function toJks(
        string $password,
        string $alias = 'certificate',
        bool   $useLegacyAlgorithm = false
    ): ConversionResponseInterface
    {
        $certificateData = $this->buildCertificateData();

        $format = new JksFormat($certificateData);
        $format->setJksPassword($password);

        if ($useLegacyAlgorithm) {
            $format->withLegacyAlgorithm();
        }

        return (new Converter())->convert($format);
    }

    private function buildCertificateData(): CertificateData
    {
        $privateKeyData = null;

        if ($this->privateKey !== null) {
            $privateKeyData = new PrivateKeyData($this->privateKey, $this->privateKeyPassword);
        }

        return new CertificateData($this->certificate, $privateKeyData, $this->caBundle);
    }
}
