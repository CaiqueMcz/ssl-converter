<?php

namespace SslConverter\Contracts;

interface PrivateKeyDataInterface
{
    public function getPrivateKey(): string;

    public function getPassword(): ?string;

    public function hasPassword(): bool;
}
