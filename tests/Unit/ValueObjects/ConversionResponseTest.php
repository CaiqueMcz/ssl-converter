<?php

namespace SslConverter\Tests\Unit\ValueObjects;

use PHPUnit\Framework\TestCase;
use SslConverter\ValueObjects\ConversionResponse;
use SslConverter\ValueObjects\VirtualFile;
use SslConverter\Collections\VirtualFileCollection;

class ConversionResponseTest extends TestCase
{
    public function testStoresAndReturnsMainVirtualFile()
    {
        $main = new VirtualFile('main.pem', 'CERTDATA');
        $extras = new VirtualFileCollection();

        $response = new ConversionResponse($main, $extras);

        $this->assertSame($main, $response->virtualFile());
    }

    public function testStoresAndReturnsExtraVirtualFiles()
    {
        $main = new VirtualFile('main.pem', 'CERTDATA');

        $extra1 = new VirtualFile('ca.pem', 'CA');
        $extra2 = new VirtualFile('private.pem', 'KEY');

        $extras = new VirtualFileCollection();
        $extras->add($extra1)->add($extra2);

        $response = new ConversionResponse($main, $extras);

        $returned = $response->extraVirtualFile()->get();
        $this->assertCount(2, $returned);
        $this->assertSame($extra1, $returned[0]);
        $this->assertSame($extra2, $returned[1]);
    }

    public function testGetAllVirtualFilesReturnsCombinedCollection()
    {
        $main = new VirtualFile('main.pem', 'CERTDATA');

        $extra1 = new VirtualFile('ca.pem', 'CA');
        $extra2 = new VirtualFile('private.pem', 'KEY');

        $extras = new VirtualFileCollection();
        $extras->add($extra1)->add($extra2);

        $response = new ConversionResponse($main, $extras);
        $all = $response->getAllVirtualFiles()->get();

        $this->assertCount(3, $all);
        $this->assertSame('main.pem', $all[0]->getName());
        $this->assertSame('ca.pem', $all[1]->getName());
        $this->assertSame('private.pem', $all[2]->getName());
    }
}
