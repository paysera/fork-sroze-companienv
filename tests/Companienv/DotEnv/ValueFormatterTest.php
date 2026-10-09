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
            'plain value' => ['abc123=$1', false, 'abc123=$1'],
            'value in double quotes' => ['"My App"', false, '"My App"'],
            'value in single quotes' => ["'my site'", false, "'my site'"],
            'value with a space' => ['my site', false, "'my site'"],
            'value with a hash' => ['a#b', false, "'a#b'"],
            'value with a double quote' => ['a"b', false, "'a\"b'"],
            'value with a backslash' => ['C:\\new', false, "'C:\\new'"],
            'two quoted strings' => ['"a" "b"', false, "'\"a\" \"b\"'"],
            'value with a single quote' => ["it's", false, '"it\'s"'],
            'value with a single quote, double quotes and a backslash' => ['it\'s "a\\b"', false, '"it\'s \\"a\\\\b\\""'],
            'forced quotes on a plain value' => ['abc', true, "'abc'"],
            'forced quotes on an empty value' => ['', true, "''"],
        ];
    }
}
