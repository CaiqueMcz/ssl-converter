<?php

namespace CaiqueMcz\SslConverter\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use CaiqueMcz\SslConverter\Utils\NormalizerUtil;

class NormalizerUtilTest extends TestCase
{
    public function testRemovesSingleDoubleBreak()
    {
        $in = 'a' . PHP_EOL . PHP_EOL . 'b';
        $out = NormalizerUtil::removeDoubleLineBreaks($in);
        $this->assertSame('a' . PHP_EOL . 'b', $out);
    }

    public function testRemovesMultipleDoubleBreaks()
    {
        $in = 'a' . PHP_EOL . PHP_EOL . 'b' . PHP_EOL . PHP_EOL . 'c';
        $out = NormalizerUtil::removeDoubleLineBreaks($in);
        $this->assertSame('a' . PHP_EOL . 'b' . PHP_EOL . 'c', $out);
    }

    public function testDoesNotChangeSingleBreaks()
    {
        $in = 'a' . PHP_EOL . 'b' . PHP_EOL . 'c';
        $out = NormalizerUtil::removeDoubleLineBreaks($in);
        $this->assertSame($in, $out);
    }

    public function testHandlesEmptyString()
    {
        $this->assertSame('', NormalizerUtil::removeDoubleLineBreaks(''));
    }

    public function testTripleBreakBecomesDouble()
    {
        $in = 'a' . PHP_EOL . PHP_EOL . PHP_EOL . 'b';
        $out = NormalizerUtil::removeDoubleLineBreaks($in);
        $this->assertSame('a' . PHP_EOL . PHP_EOL . 'b', $out);
    }

    /**
     * @dataProvider certificateBundleProvider
     */
    public function testSplitCertificates(string $caBundle, array $expected): void
    {
        $this->assertSame($expected, NormalizerUtil::splitCertificates($caBundle));
    }

    public function certificateBundleProvider(): array
    {
        $first = "-----BEGIN CERTIFICATE-----\nZmlyc3Q=\n-----END CERTIFICATE-----";
        $second = "-----BEGIN CERTIFICATE-----\n c2Vjb25k\n-----END CERTIFICATE-----";
        $windowsCertificate = str_replace("\n", "\r\n", $second);

        return [
            'single certificate' => [$first, [$first]],
            'multiple certificates in order' => [$first . "\n\n" . $second, [$first, $second]],
            'adjacent certificates' => [$first . $second, [$first, $second]],
            'mixed line endings' => [$first . "\r\n" . $windowsCertificate, [$first, $windowsCertificate]],
            'surrounding text' => ["CA bundle\n" . $first . "\n# next CA\n" . $second . "\n", [$first, $second]],
            'empty bundle' => ['', []],
            'no certificate blocks' => ['invalid-certificate', []],
            'incomplete certificate' => ["-----BEGIN CERTIFICATE-----\nZmlyc3Q=", []],
        ];
    }
}
