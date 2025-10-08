<?php

namespace SslConverter\Contracts;

use SslConverter\Collections\VirtualFileCollection;
use SslConverter\ValueObjects\VirtualFile;

interface ConversionResponseInterface
{
    public function virtualFile(): VirtualFile;

    public function extraVirtualFile(): VirtualFileCollection;
}
