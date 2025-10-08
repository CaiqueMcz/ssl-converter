<?php

namespace CaiqueMcz\SslConverter\ValueObjects;

use CaiqueMcz\SslConverter\Collections\VirtualFileCollection;
use CaiqueMcz\SslConverter\Contracts\ConversionResponseInterface;

final class ConversionResponse implements ConversionResponseInterface
{
    private VirtualFile $virtualFile;
    private VirtualFileCollection $extraVirtualFile;

    public function __construct(VirtualFile $virtualFile, VirtualFileCollection $extraVirtualFile)
    {
        $this->virtualFile = $virtualFile;
        $this->extraVirtualFile = $extraVirtualFile;
    }

    public function virtualFile(): VirtualFile
    {
        return $this->virtualFile;
    }

    public function extraVirtualFile(): VirtualFileCollection
    {
        return $this->extraVirtualFile;
    }

    public function getAllVirtualFiles(): VirtualFileCollection
    {
        $collection = new VirtualFileCollection();
        $collection->add($this->virtualFile());
        foreach ($this->extraVirtualFile() as $virtualFile) {
            $collection->add($virtualFile);
        }
        return $collection;
    }
}
