<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class ValueFormatterTest extends TestCase
{
    /**
     * @dataProvider valueDataProvider
     */
    public function testFormatValue(string $value, bool $forceQuotes, ?string $referenceValue, string $expected): void
    {
        $this->assertSame($expected, (new ValueFormatter($referenceValue))->formatValue($value, $forceQuotes));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string|null, 3: string}>
     */
    public static function valueDataProvider(): array
    {
        return [
            'plain value' => ['abc123=$1', false, null, 'abc123=$1'],
            'value in double quotes' => ['"My App"', false, null, '"My App"'],
            'value in single quotes' => ["'my site'", false, null, "'my site'"],
            'value with a space' => ['my site', false, null, "'my site'"],
            'value with a hash' => ['a#b', false, null, "'a#b'"],
            'value with a double quote' => ['a"b', false, null, "'a\"b'"],
            'value with a backslash' => ['C:\\new', false, null, "'C:\\new'"],
            'two quoted strings' => ['"a" "b"', false, null, "'\"a\" \"b\"'"],
            'value with a single quote' => ["it's", false, null, '"it\'s"'],
            'value with a single quote, double quotes and a backslash' => ['it\'s "a\\b"', false, null, '"it\'s \\"a\\\\b\\""'],
            'reference value' => ['dev # dev or prod', false, 'dev # dev or prod', 'dev # dev or prod'],
            'reference value Dotenv cannot read' => ['My App', false, 'My App', "'My App'"],
            'reference value ending in an unmatched quote' => ['27"', false, '27"', "'27\"'"],
            'value other than the reference value' => ['dev # local', false, 'dev # dev or prod', "'dev # local'"],
            'forced quotes on a plain value' => ['abc', true, null, "'abc'"],
            'forced quotes on an empty value' => ['', true, null, "''"],
            'forced quotes on an empty reference value' => ['', true, '', "''"],
        ];
    }
}
