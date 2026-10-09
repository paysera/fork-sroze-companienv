<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class BlockTest extends TestCase
{
    /**
     * @dataProvider variableNameDataProvider
     */
    public function testGetVariable(Block $block, string $name, ?Variable $expected): void
    {
        $this->assertSame($expected, $block->getVariable($name));
    }

    /**
     * @return array<string, array{0: Block, 1: string, 2: Variable|null}>
     */
    public static function variableNameDataProvider(): array
    {
        $variable = new Variable('NAME', 'value');
        $block = new Block('Block', '', [new Variable('OTHER', 'other'), $variable]);

        return [
            'defined variable' => [$block, 'NAME', $variable],
            'unknown variable' => [$block, 'UNKNOWN', null],
        ];
    }

    public function testGetAttributes(): void
    {
        $attributes = [
            new Attribute('only-if', ['NAME'], ['OTHER' => 'on']),
            new Attribute('rsa-pair', ['A', 'B', 'C'], []),
        ];

        $this->assertSame($attributes, (new Block('Block', '', [], $attributes))->getAttributes());
    }
}
