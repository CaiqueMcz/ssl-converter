<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Utils\PrivateKeyUtil;
use CaiqueMcz\SslConverter\Exceptions\ConversionException;

class PrivateKeyUtilTest extends TestCase
{
    public function testReturnsResourceForValidUnencryptedKey()
    {
        $key = $this->generateUnencryptedPrivateKey();
        $util = new PrivateKeyUtil($key);
        $res = $util->getKeyResource();
        $this->assertTrue($this->isOpenSslKey($res));
    }

    public function testReturnsResourceForValidEncryptedKeyWithCorrectPassword()
    {
        $password = 'secret123';
        $key = $this->generateEncryptedPrivateKey($password);
        $util = new PrivateKeyUtil($key, $password);
        $res = $util->getKeyResource();
        $this->assertTrue($this->isOpenSslKey($res));
    }

    public function testThrowsExceptionForEncryptedKeyWithWrongPassword()
    {
        $this->expectException(ConversionException::class);
        $password = 'secret123';
        $key = $this->generateEncryptedPrivateKey($password);
        $util = new PrivateKeyUtil($key, 'wrong');
        $util->getKeyResource();
    }

    public function testThrowsExceptionForInvalidKey()
    {
        $this->expectException(ConversionException::class);
        $util = new PrivateKeyUtil('invalid-key');
        $util->getKeyResource();
    }

    private function generateUnencryptedPrivateKey(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $out);
        return $out;
    }

    private function generateEncryptedPrivateKey(string $password): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $out, $password, [
            'encrypt_key' => true,
        ]);
        return $out;
    }

    private function isOpenSslKey($value): bool
    {
        if (is_resource($value)) {
            return get_resource_type($value) === 'OpenSSL key';
        }
        if (class_exists(\OpenSSLAsymmetricKey::class)) {
            return $value instanceof \OpenSSLAsymmetricKey;
        }
        return false;
    }
}
