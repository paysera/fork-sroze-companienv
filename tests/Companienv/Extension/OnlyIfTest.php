<?php

declare(strict_types=1);

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Attribute;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use Companienv\IO\InMemoryFileSystem;
use Companienv\IO\InMemoryInteraction;
use PHPUnit\Framework\TestCase;

final class OnlyIfTest extends TestCase
{
    /**
     * @dataProvider conditionDataProvider
     *
     * @param array<string, string> $files
     * @param string|null $expectedValue
     */
    public function testCondition(Block $block, array $files, $expectedValue, int $expectedRequirement): void
    {
        $fileSystem = new InMemoryFileSystem();
        foreach (['.env.dist' => ''] + $files as $path => $contents) {
            $fileSystem->write($path, $contents);
        }
        $companion = new Companion($fileSystem, new InMemoryInteraction(), new Chained());
        $variable = new Variable('TARGET', 'dist-value');
        $extension = new OnlyIf();

        $this->assertSame(
            ['value' => $expectedValue, 'requirement' => $expectedRequirement],
            [
                'value' => $extension->getVariableValue($companion, $block, $variable),
                'requirement' => $extension->isVariableRequiringValue($companion, $block, $variable),
            ]
        );
    }

    /**
     * @return array<string, array{0: Block, 1: array<string, string>, 2: string|null, 3: int}>
     */
    public static function conditionDataProvider(): array
    {
        $block = new Block('Block', '', [], [new Attribute('only-if', ['TARGET'], ['SWITCH' => 'on'])]);

        return [
            'no only-if attribute' => [new Block('Block'), ['.env' => "SWITCH=off\n"], null, Extension::ABSTAIN],
            'only-if attribute for another variable' => [
                new Block('Block', '', [], [new Attribute('only-if', ['OTHER'], ['SWITCH' => 'on'])]),
                ['.env' => "SWITCH=off\n"],
                null,
                Extension::ABSTAIN,
            ],
            'condition variable not defined yet' => [$block, [], null, Extension::ABSTAIN],
            'condition met' => [$block, ['.env' => "SWITCH=on\n"], null, Extension::ABSTAIN],
            'condition not met' => [$block, ['.env' => "SWITCH=off\n"], 'dist-value', Extension::VARIABLE_SKIP],
        ];
    }
}
