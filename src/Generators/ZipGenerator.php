<?php

declare(strict_types=1);

namespace CaiqueMcz\SslConverter\Generators;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Formats\JksFormat;
use CaiqueMcz\SslConverter\Formats\PemFormat;
use CaiqueMcz\SslConverter\Formats\PfxFormat;
use CaiqueMcz\SslConverter\Utils\JksAlias;
use CaiqueMcz\SslConverter\Utils\NormalizerUtil;
use CaiqueMcz\SslConverter\Utils\PrivateKeyUtil;
use CaiqueMcz\SslConverter\ValueObjects\CertificateData;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;
use ZipArchive;

final class ZipGenerator
{
    private CertificateData $certificateData;
    private string $password;
    private bool $encryptZip;
    private bool $useLegacyAlgorithm;

    public function __construct(
        CertificateData $certificateData,
        string $password,
        bool $encryptZip = false,
        bool $useLegacyAlgorithm = false
    ) {
        $this->certificateData = $certificateData;
        $this->password = $password;
        $this->encryptZip = $encryptZip;
        $this->useLegacyAlgorithm = $useLegacyAlgorithm;
    }

    /**
     * The caller owns the returned ZIP and its private temporary directory and must delete both.
     *
     * @throws ConversionException
     */
    public function generate(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new ConversionException('The ZIP extension is required to generate an archive.');
        }
        if (
            $this->encryptZip
            && (!method_exists(ZipArchive::class, 'setEncryptionName') || !defined('ZipArchive::EM_AES_256'))
        ) {
            throw new ConversionException('ZIP encryption requires an extension with AES-256 support.');
        }
        // keytool requires at least six characters for the destination keystore password.
        if (strlen($this->password) < 6) {
            throw new ConversionException('The shared password must contain at least six characters.');
        }
        $commonName = $this->commonName();
        $archiveName = $this->archiveName($commonName);
        $alias = JksAlias::normalize($commonName);
        $data = $this->encryptedCertificateData();
        $formats = [
            new PemFormat($data),
            (new PfxFormat($data))->setPfxPassword($this->password)->setUseLegacyAlgorithm($this->useLegacyAlgorithm),
            (new JksFormat($data))->setJksPassword($this->password)->setAlias($alias),
        ];

        // A private directory also protects libzip's temporary replacement files.
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssl_converter_' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new ConversionException('Unable to create a private temporary directory for the ZIP.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . $archiveName;
        $zip = new ZipArchive();
        $opened = false;
        $completed = false;
        try {
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new ConversionException('Unable to open the ZIP archive.');
            }
            $opened = true;
            foreach ($formats as $format) {
                $format->validateOrFail();
                foreach ($format->convert()->getAllVirtualFiles() as $file) {
                    $name = $format->getName() . '/' . $file->getName();
                    $this->addFile($zip, $name, $file->getContent());
                    // PFX and P12 are names for the same PKCS#12 container.
                    if ($format->getName() === 'pfx') {
                        $this->addFile($zip, 'p12/certificate.p12', $file->getContent());
                    }
                }
            }
            $this->addCrtFiles($zip, $data);
            $this->addKeystoreMetadata($zip, $alias);
            $closed = $zip->close();
            $opened = false;
            if (!$closed || !chmod($path, 0600)) {
                throw new ConversionException('Unable to save the ZIP archive securely.');
            }
            $completed = true;
            return $path;
        } finally {
            try {
                if ($opened) {
                    $zip->close();
                }
            } finally {
                if (!$completed) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                    rmdir($directory);
                }
            }
        }
    }

    private function addCrtFiles(ZipArchive $zip, CertificateData $data): void
    {
        $this->addFile($zip, 'crt/ca-bundle.crt', $data->getCaBundle());
        $this->addFile($zip, 'crt/certificate.crt', $data->getCertificate());
        $this->addFile($zip, 'crt/private.key', $data->getPrivateKeyData()->getPrivateKey());
    }

    private function addKeystoreMetadata(ZipArchive $zip, string $alias): void
    {
        $this->addFile($zip, 'jks/alias.txt', $alias);
        foreach (['jks', 'p12', 'pfx'] as $format) {
            $this->addFile($zip, $format . '/password.txt', $this->password);
        }
    }

    private function addFile(ZipArchive $zip, string $name, string $content): void
    {
        if (!$zip->addFromString($name, $content)) {
            throw new ConversionException('Unable to add a converted file to the ZIP archive.');
        }
        if ($this->encryptZip && !$zip->setEncryptionName($name, ZipArchive::EM_AES_256, $this->password)) {
            throw new ConversionException('Unable to encrypt a ZIP entry with AES-256.');
        }
    }

    private function commonName(): string
    {
        $parsed = $this->readOpenSsl(function () {
            return openssl_x509_parse($this->certificateData->getCertificate());
        }, 'Invalid certificate format.');
        if ($parsed === false) {
            throw new ConversionException('Invalid certificate format.');
        }
        $commonName = $parsed['subject']['CN'] ?? null;
        if (!is_string($commonName) || trim($commonName) === '') {
            throw new ConversionException('A single certificate common name (CN) is required for ZIP export.');
        }
        return $commonName;
    }

    private function archiveName(string $commonName): string
    {
        $baseName = trim(preg_replace('/[^a-zA-Z0-9._-]/', '_', $commonName), '.');
        if ($baseName === '') {
            throw new ConversionException('Certificate common name cannot be used as an archive filename.');
        }
        // Avoid reserved Windows device names when the archive is downloaded.
        if (preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $baseName)) {
            $baseName = '_' . $baseName;
        }
        $sequence = sprintf('%06d%06d', random_int(0, 999999), random_int(0, 999999));
        return $baseName . '_' . $sequence . '.zip';
    }

    private function encryptedCertificateData(): CertificateData
    {
        if (!$this->certificateData->hasPrivateKey()) {
            throw new ConversionException('Private key is required for ZIP export.');
        }
        $bundle = $this->certificateData->getCaBundle();
        if ($bundle === null) {
            throw new ConversionException('CA bundle is required for ZIP export.');
        }
        $certificates = NormalizerUtil::splitCertificates($bundle);
        if (
            $certificates === []
            || substr_count($bundle, '-----BEGIN CERTIFICATE-----') !== count($certificates)
            || substr_count($bundle, '-----END CERTIFICATE-----') !== count($certificates)
        ) {
            throw new ConversionException('Invalid CA bundle for ZIP export.');
        }
        foreach ($certificates as $certificate) {
            $parsed = $this->readOpenSsl(static function () use ($certificate) {
                return openssl_x509_read($certificate);
            }, 'Invalid certificate in CA bundle.');
            if ($parsed === false) {
                throw new ConversionException('Invalid certificate in CA bundle.');
            }
        }
        $keyData = $this->certificateData->getPrivateKeyData();
        $key = $this->readOpenSsl(static function () use ($keyData) {
            return (new PrivateKeyUtil($keyData->getPrivateKey(), $keyData->getPassword()))->getKeyResource();
        }, 'Invalid private key or password.');
        if (!openssl_x509_check_private_key($this->certificateData->getCertificate(), $key)) {
            throw new ConversionException('Private key does not match the certificate.');
        }
        $encrypted = '';
        $exported = $this->readOpenSsl(function () use ($key, &$encrypted) {
            return openssl_pkey_export(
                $key,
                $encrypted,
                $this->password,
                ['encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC]
            );
        }, 'Unable to encrypt the PEM private key.');
        if (!$exported) {
            throw new ConversionException('Unable to encrypt the PEM private key.');
        }
        return new CertificateData(
            $this->certificateData->getCertificate(),
            new PrivateKeyData($encrypted, $this->password),
            $bundle
        );
    }

    /** Convert OpenSSL warnings into domain errors without exposing input or secrets. */
    private function readOpenSsl(callable $operation, string $message)
    {
        set_error_handler(static function () use ($message): void {
            throw new ConversionException($message);
        }, E_WARNING);
        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }
}
