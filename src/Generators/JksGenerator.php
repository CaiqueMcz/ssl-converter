<?php

namespace CaiqueMcz\SslConverter\Generators;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Utils\ProcessUtil;
use CaiqueMcz\SslConverter\Utils\JksAlias;
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
        $this->alias = JksAlias::normalize($alias);
        $this->useLegacyAlgorithm = $useLegacyAlgorithm;
    }

    public function generate()
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jks_' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new ConversionException('Unable to create a private temporary directory for JKS.');
        }
        $tempPfx = $directory . DIRECTORY_SEPARATOR . 'input.pfx';
        $tempJks = $directory . DIRECTORY_SEPARATOR . 'output.jks';

        try {
            $pfxGenerator = new PfxGenerator(
                $this->certificateData,
                $this->password,
                $this->useLegacyAlgorithm
            );
            $pfxData = $pfxGenerator->generate();
            if (file_put_contents($tempPfx, $pfxData) !== strlen($pfxData)) {
                throw new ConversionException('Unable to write the temporary PFX file.');
            }

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

            set_error_handler(static function (): void {
                throw new ConversionException('Unable to execute keytool.');
            }, E_WARNING);
            try {
                $process->run();
            } finally {
                restore_error_handler();
            }
            if (!$process->isSuccessful()) {
                throw new ConversionException('keytool could not import the certificate into JKS.');
            }

            $content = file_get_contents($tempJks);
            if ($content === false) {
                throw new ConversionException('Unable to read the generated JKS file.');
            }
            return $content;
        } catch (ConversionException $e) {
            throw new ConversionException("Failed to generate JKS file: " . $e->getMessage());
        } finally {
            if (file_exists($tempPfx)) {
                unlink($tempPfx);
            }
            if (file_exists($tempJks)) {
                unlink($tempJks);
            }
            rmdir($directory);
        }
    }
}
