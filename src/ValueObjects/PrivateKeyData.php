<?php

namespace CaiqueMcz\SslConverter\ValueObjects;

use CaiqueMcz\SslConverter\Contracts\PrivateKeyDataInterface;

class PrivateKeyData implements PrivateKeyDataInterface
{
    private string $privateKey;
    private ?string $password;

    public function __construct(string $privateKey, string $password = null)
    {
        $this->privateKey = $privateKey;
        $this->password = $password;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function hasPassword(): bool
    {
        return !empty($this->password);
    }
}
