<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    /**
     * @dataProvider valueDataProvider
     */
    public function testVariable(?string $value, bool $expectedHasValue, ?string $expectedDotenvValue): void
    {
        $variable = new Variable('NAME', $value);

        $this->assertSame(
            [
                'name' => 'NAME',
                'value' => $value,
                'has value' => $expectedHasValue,
                'dotenv value' => $expectedDotenvValue,
            ],
            [
                'name' => $variable->getName(),
                'value' => $variable->getValue(),
                'has value' => $variable->hasValue(),
                'dotenv value' => $variable->getDotenvValue(),
            ]
        );
    }

    /**
     * @return array<string, array{0: string|null, 1: bool, 2: string|null}>
     */
    public static function valueDataProvider(): array
    {
        return [
            'value' => ['value', true, 'value'],
            'empty value' => ['', false, ''],
            'no value' => [null, false, ''],
            'value with an inline comment' => ['keys/key.pem # the deploy key', true, 'keys/key.pem'],
            'value in double quotes' => ['"My App"', true, 'My App'],
            'value Dotenv cannot read' => ['My App', true, null],
            'value ending in an unmatched quote' => ['27"', true, null],
        ];
    }
}
