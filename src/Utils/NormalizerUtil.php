<?php

namespace CaiqueMcz\SslConverter\Utils;

class NormalizerUtil
{
    public static function removeDoubleLineBreaks(string $content): string
    {
        return str_replace(PHP_EOL . PHP_EOL, PHP_EOL, $content);
    }

    public static function splitCertificates(string $caBundle): array
    {
        preg_match_all(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $caBundle,
            $matches
        );

        return $matches[0];
    }
}
