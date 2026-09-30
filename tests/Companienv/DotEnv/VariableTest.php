<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    /**
     * @dataProvider valueDataProvider
     */
    public function testVariable(?string $value, bool $expectedHasValue): void
    {
        $variable = new Variable('NAME', $value);

        $this->assertSame(
            ['name' => 'NAME', 'value' => $value, 'has value' => $expectedHasValue],
            ['name' => $variable->getName(), 'value' => $variable->getValue(), 'has value' => $variable->hasValue()]
        );
    }

    /**
     * @return array<string, array{0: string|null, 1: bool}>
     */
    public static function valueDataProvider(): array
    {
        return [
            'value' => ['value', true],
            'empty value' => ['', false],
            'no value' => [null, false],
        ];
    }
}
