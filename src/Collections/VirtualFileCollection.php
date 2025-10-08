<?php

namespace CaiqueMcz\SslConverter\Collections;

use ArrayIterator;
use IteratorAggregate;
use CaiqueMcz\SslConverter\ValueObjects\VirtualFile;

class VirtualFileCollection implements IteratorAggregate
{
    private array $collection = [];

    public function add(VirtualFile $file): VirtualFileCollection
    {
        $this->collection[] = $file;
        return $this;
    }
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->collection);
    }
    public function get(): array
    {
        return $this->collection;
    }
}
