<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\ValueObjects\VirtualFile;

class VirtualFileTest extends TestCase
{
    public function testGetName()
    {
        $file = new VirtualFile('fullchain.pem', 'CERTDATA');
        $this->assertSame('fullchain.pem', $file->getName());
    }

    public function testGetContent()
    {
        $content = "LINE1\nLINE2";
        $file = new VirtualFile('fullchain.pem', $content);
        $this->assertSame($content, $file->getContent());
    }

    public function testGetSizeForAscii()
    {
        $content = "ABC123\nXYZ";
        $file = new VirtualFile('x.txt', $content);
        $this->assertSame(strlen($content), $file->getSize());
    }

    public function testGetSizeForEmptyContent()
    {
        $file = new VirtualFile('empty.txt', '');
        $this->assertSame(0, $file->getSize());
    }

    public function testGetSizeForUtf8Multibyte()
    {
        $content = "çãó🙂";
        $file = new VirtualFile('utf8.txt', $content);
        $this->assertSame(strlen($content), $file->getSize());
    }
}
