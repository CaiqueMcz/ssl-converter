<?php

namespace CaiqueMcz\SslConverter\Contracts;

use CaiqueMcz\SslConverter\Collections\VirtualFileCollection;
use CaiqueMcz\SslConverter\ValueObjects\VirtualFile;

interface ConversionResponseInterface
{
    public function virtualFile(): VirtualFile;

    public function extraVirtualFile(): VirtualFileCollection;
}
