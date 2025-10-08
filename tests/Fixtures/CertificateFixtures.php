<?php

namespace CaiqueMcz\SslConverter\Tests\Fixtures;

class CertificateFixtures
{
    public static function generatePrivateKey(bool $encrypted = false, string $password = ''): string
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $res = openssl_pkey_new($config);

        if ($encrypted) {
            openssl_pkey_export($res, $privateKey, $password);
        } else {
            openssl_pkey_export($res, $privateKey);
        }

        return $privateKey;
    }

    public static function generateSelfSignedCertificate(string $privateKey): string
    {
        $dn = [
            "countryName" => "BR",
            "stateOrProvinceName" => "BA",
            "localityName" => "Feira de santana",
            "organizationName" => "Test Company",
            "organizationalUnitName" => "IT",
            "commonName" => "example.com",
            "emailAddress" => "test@example.com"
        ];

        $privkey = openssl_pkey_get_private($privateKey);
        $csr = openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);

        $x509 = openssl_csr_sign($csr, null, $privkey, 365, ['digest_alg' => 'sha256']);

        openssl_x509_export($x509, $certout);

        return $certout;
    }

    public static function generateCaBundle(): string
    {
        $privateKey = self::generatePrivateKey();

        $dn = [
            "countryName" => "BR",
            "organizationName" => "Test CA",
            "commonName" => "Test Root CA"
        ];

        $privkey = openssl_pkey_get_private($privateKey);
        $csr = openssl_csr_new($dn, $privkey);
        $x509 = openssl_csr_sign($csr, null, $privkey, 3650);

        openssl_x509_export($x509, $cacert);

        return $cacert;
    }

    public static function generateCompleteCertificateData(
        bool   $withPrivateKey = true,
        bool   $encryptedKey = false,
        bool   $withCaBundle = true,
        string $keyPassword = ''
    ): array
    {
        $privateKey = null;
        $password = null;

        if ($withPrivateKey) {
            $privateKey = self::generatePrivateKey($encryptedKey, $keyPassword);
            $password = $encryptedKey ? $keyPassword : null;
        } else {
            $privateKey = self::generatePrivateKey();
        }

        $certificate = self::generateSelfSignedCertificate($privateKey);
        $caBundle = $withCaBundle ? self::generateCaBundle() : null;

        return [
            'certificate' => $certificate,
            'private_key' => $withPrivateKey ? $privateKey : null,
            'private_key_password' => $password,
            'ca_bundle' => $caBundle,
        ];
    }
}