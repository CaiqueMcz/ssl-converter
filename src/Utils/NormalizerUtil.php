<?php

namespace CaiqueMcz\SslConverter\Utils;

class NormalizerUtil
{
    public static function removeDoubleLineBreaks(string $content): string
    {
        return str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content);
    }
}
