<?php

declare(strict_types=1);

namespace CaiqueMcz\SslConverter\Utils;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;

final class JksAlias
{
    public static function normalize(string $alias): string
    {
        $alias = substr(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias)), 0, 32);
        if ($alias === '') {
            throw new ConversionException('JKS alias must contain at least one ASCII letter or digit.');
        }
        return $alias;
    }
}
