<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class MissingVariableTest extends TestCase
{
    /**
     * @dataProvider currentValueDataProvider
     */
    public function testMissingVariable(?string $currentValue): void
    {
        $variable = new MissingVariable(new Variable('NAME', 'reference'), $currentValue);

        $this->assertSame(
            ['name' => 'NAME', 'value' => 'reference', 'current value' => $currentValue],
            [
                'name' => $variable->getName(),
                'value' => $variable->getValue(),
                'current value' => $variable->getCurrentValue(),
            ]
        );
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function currentValueDataProvider(): array
    {
        return [
            'with a current value' => ['current'],
            'without a current value' => [null],
        ];
    }
}
