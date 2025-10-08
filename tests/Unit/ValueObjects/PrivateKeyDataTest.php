<?php

namespace Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\ValueObjects\PrivateKeyData;

class PrivateKeyDataTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $privateKey = "test-private-key";
        $password = "test-password";

        $data = new PrivateKeyData($privateKey, $password);

        $this->assertEquals($privateKey, $data->getPrivateKey());
        $this->assertEquals($password, $data->getPassword());
    }

    public function testHasPasswordWithPassword(): void
    {
        $data = new PrivateKeyData("key", "password");

        $this->assertTrue($data->hasPassword());
    }

    public function testHasPasswordWithoutPassword(): void
    {
        $data = new PrivateKeyData("key");

        $this->assertFalse($data->hasPassword());
    }

    public function testHasPasswordWithEmptyPassword(): void
    {
        $data = new PrivateKeyData("key", "");

        $this->assertFalse($data->hasPassword());
    }
}