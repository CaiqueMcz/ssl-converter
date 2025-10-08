<?php

namespace SslConverter\Generators;

use SslConverter\Exceptions\ConversionException;
use SslConverter\Utils\PrivateKeyUtil;
use SslConverter\ValueObjects\CertificateData;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PfxGenerator
{
    private CertificateData $certificateData;
    private string $password;
    private bool $useLegacyAlgorithm;

    public function __construct(CertificateData $certificateData, string $password, bool $useLegacyAlgorithm = false)
    {
        $this->certificateData = $certificateData;
        $this->password = $password;
        $this->useLegacyAlgorithm = $useLegacyAlgorithm;
    }

    public function generate(): string
    {
        $pfxData = $this->generateStandard();

        if ($this->useLegacyAlgorithm) {
            return $this->convertToLegacy($pfxData);
        }

        return $pfxData;
    }

    private function generateStandard(): string
    {
        $certificate = $this->certificateData->getCertificate();
        $privateKeyUtil = new PrivateKeyUtil(
            $this->certificateData->getPrivateKeyData()->getPrivateKey(),
            $this->certificateData->getPrivateKeyData()->getPassword()
        );
        $caBundle = $this->certificateData->getCaBundle();

        $certResource = openssl_x509_read($certificate);
        if ($certResource === false) {
            throw new ConversionException("Invalid certificate format");
        }

        $keyResource = $privateKeyUtil->getKeyResource();

        $caCerts = null;
        if ($caBundle) {
            $caCerts = [$caBundle];
        }

        $pfxData = '';
        $result = openssl_pkcs12_export(
            $certResource,
            $pfxData,
            $keyResource,
            $this->password,
            [
                'extracerts' => $caCerts,
            //     'friendly_name' => 'Certificate'
            ]
        );

        if (!$result) {
            throw new ConversionException("Failed to generate PFX file");
        }

        return $pfxData;
    }

    private function convertToLegacy(string $pfxData): string
    {
        $tempPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        $tempPem = tempnam(sys_get_temp_dir(), 'pem_');
        $tempLegacy = tempnam(sys_get_temp_dir(), 'legacy_');

        try {
            file_put_contents($tempPfx, $pfxData);

            $extractProcess = new Process([
                'openssl', 'pkcs12',
                '-in', $tempPfx,
                '-out', $tempPem,
                '-nodes',
                '-password', 'pass:' . $this->password
            ]);
            $extractProcess->mustRun();

            $convertProcess = new Process([
                'openssl', 'pkcs12',
                '-export',
                '-in', $tempPem,
                '-out', $tempLegacy,
                '-legacy',
                '-password', 'pass:' . $this->password,
                '-passout', 'pass:' . $this->password
            ]);
            $convertProcess->mustRun();

            $legacyData = file_get_contents($tempLegacy);

            return $legacyData;
        } catch (ProcessFailedException $e) {
            throw new ConversionException("Failed to convert PFX to legacy format: " . $e->getMessage());
        } finally {
            if (file_exists($tempPfx)) {
                unlink($tempPfx);
            }
            if (file_exists($tempPem)) {
                unlink($tempPem);
            }
            if (file_exists($tempLegacy)) {
                unlink($tempLegacy);
            }
        }
    }
}
