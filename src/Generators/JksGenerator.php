<?php

namespace CaiqueMcz\SslConverter\Generators;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;

class JksGenerator
{
    private CertificateData $certificateData;
    private string $password;
    private string $alias;
    private bool $useLegacyAlgorithm;

    public function __construct(
        CertificateData $certificateData,
        string $password,
        string $alias = 'certificate',
        bool $useLegacyAlgorithm = false
    ) {
        $this->certificateData = $certificateData;
        $this->password = $password;
        $this->alias = $alias;
        $this->useLegacyAlgorithm = $useLegacyAlgorithm;
    }

    public function generate()
    {
        $tempPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        $tempJks = tempnam(sys_get_temp_dir(), 'jks_');

        unlink($tempJks);

        try {
            $pfxGenerator = new PfxGenerator(
                $this->certificateData,
                $this->password,
                $this->useLegacyAlgorithm
            );
            $pfxData = $pfxGenerator->generate();
            file_put_contents($tempPfx, $pfxData);

            $process = new ProcessUtil([
                'keytool',
                '-importkeystore',
                '-srckeystore', $tempPfx,
                '-srcstoretype', 'pkcs12',
                '-srcalias', '1',
                '-srcstorepass', $this->password,
                '-destkeystore', $tempJks,
                '-deststoretype', 'jks',
                '-deststorepass', $this->password,
                '-destalias', $this->alias,
                '-noprompt'
            ]);

            $process->mustRun();

            return file_get_contents($tempJks);
        } catch (ConversionException $e) {
            throw new ConversionException("Failed to generate JKS file: " . $e->getMessage());
        } finally {
            if (file_exists($tempPfx)) {
                unlink($tempPfx);
            }
            if (file_exists($tempJks)) {
                unlink($tempJks);
            }
        }
    }
}
