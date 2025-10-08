<?php

namespace SslConverter\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use SslConverter\Utils\NormalizerUtil;

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
}
