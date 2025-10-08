<?php

namespace CaiqueMcz\SslConverter\Contracts;

interface CertificateDataInterface
{
    public function getCertificate();

    public function getPrivateKeyData(): ?PrivateKeyDataInterface;

    public function getCaBundle(): ?string;
}
