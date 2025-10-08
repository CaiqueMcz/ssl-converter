<?php

namespace SslConverter\Tests\Unit\Collections;

use PHPUnit\Framework\TestCase;
use SslConverter\Collections\VirtualFileCollection;
use SslConverter\ValueObjects\VirtualFile;

class VirtualFileCollectionTest extends TestCase
{
    public function testStartsEmpty()
    {
        $collection = new VirtualFileCollection();
        $this->assertIsArray($collection->get());
        $this->assertCount(0, $collection->get());
    }

    public function testAddReturnsSelfAndStoresItemsInOrder()
    {
        $collection = new VirtualFileCollection();

        $file1 = new VirtualFile('a.txt', 'A');
        $file2 = new VirtualFile('b.txt', 'B');

        $returned = $collection->add($file1);
        $this->assertSame($collection, $returned);

        $collection->add($file2);

        $items = $collection->get();
        $this->assertCount(2, $items);
        $this->assertSame($file1, $items[0]);
        $this->assertSame($file2, $items[1]);
    }

    public function testIterableWithForeach()
    {
        $collection = new VirtualFileCollection();
        $files = [
            new VirtualFile('1.pem', 'X'),
            new VirtualFile('2.pem', 'Y'),
            new VirtualFile('3.pem', 'Z'),
        ];

        foreach ($files as $f) {
            $collection->add($f);
        }

        $seen = [];
        foreach ($collection as $item) {
            $this->assertInstanceOf(VirtualFile::class, $item);
            $seen[] = $item->getName();
        }

        $this->assertSame(['1.pem', '2.pem', '3.pem'], $seen);
    }

    public function testGetReturnsArrayOfVirtualFiles()
    {
        $collection = new VirtualFileCollection();
        $collection->add(new VirtualFile('fullchain.pem', 'CERT'));
        $collection->add(new VirtualFile('ca-bundle.pem', 'CA'));

        $items = $collection->get();
        $this->assertIsArray($items);
        $this->assertInstanceOf(VirtualFile::class, $items[0]);
        $this->assertInstanceOf(VirtualFile::class, $items[1]);
    }
}
