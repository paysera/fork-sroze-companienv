<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class ValueFormatterTest extends TestCase
{
    /**
     * @dataProvider valueDataProvider
     */
    public function testFormatValue(string $value, bool $forceQuotes, string $expected): void
    {
        $this->assertSame($expected, (new ValueFormatter())->formatValue($value, $forceQuotes));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function valueDataProvider(): array
    {
        return [
            'unquoted value is written as is' => ['a\\b"c d', false, 'a\\b"c d'],
            'quoted value escapes backslashes and double quotes' => ['a\\b"c d', true, '"a\\\\b\\"c d"'],
            'quoted empty value' => ['', true, '""'],
        ];
    }
}
