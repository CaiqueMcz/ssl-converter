<?php

declare(strict_types=1);

namespace CaiqueMcz\SslConverter\Tests\Unit\Utils;

use CaiqueMcz\SslConverter\Exceptions\ConversionException;
use CaiqueMcz\SslConverter\Utils\JksAlias;
use PHPUnit\Framework\TestCase;

class JksAliasTest extends TestCase
{
    /** @dataProvider aliases */
    public function testNormalizesAliases(string $input, string $expected): void
    {
        self::assertSame($expected, JksAlias::normalize($input));
    }

    public function aliases(): array
    {
        return [
            ['Example.COM', 'examplecom'],
            ['*.Example-01_com / Test!', 'example01comtest'],
            [str_repeat('A', 33), str_repeat('a', 32)],
            [str_repeat('b', 32), str_repeat('b', 32)],
            ['123', '123'],
            ['café.example', 'cafexample'],
        ];
    }

    /** @dataProvider emptyAliases */
    public function testRejectsAliasesWithoutLettersOrNumbers(string $alias): void
    {
        $this->expectException(ConversionException::class);
        JksAlias::normalize($alias);
    }

    public function emptyAliases(): array
    {
        return [[''], ['*.-_ /'], ['áé']];
    }
}
