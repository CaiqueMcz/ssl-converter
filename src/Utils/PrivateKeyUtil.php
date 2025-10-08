<?php

namespace CaiqueMcz\SslConverter\Utils;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;

class PrivateKeyUtil
{
    private string $privateKey;
    private ?string $privateKeyPassword;

    public function __construct(string $privateKey, ?string $privateKeyPassword = null)
    {
        $this->privateKey = $privateKey;
        $this->privateKeyPassword = $privateKeyPassword;
    }

    public function getKeyResource()
    {
        $keyResource = openssl_pkey_get_private($this->privateKey, $this->privateKeyPassword ?? '');

        if ($keyResource === false) {
            throw new ConversionException("Invalid private key or password");
        }

        return $keyResource;
    }
}
