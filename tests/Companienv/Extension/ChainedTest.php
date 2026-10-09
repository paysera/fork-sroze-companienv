<?php

declare(strict_types=1);

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use Companienv\IO\InMemoryFileSystem;
use Companienv\IO\InMemoryInteraction;
use PHPUnit\Framework\TestCase;

final class ChainedTest extends TestCase
{
    /**
     * @dataProvider extensionsDataProvider
     *
     * @param list<Extension> $extensions
     */
    public function testChain(array $extensions, ?string $expectedValue, int $expectedRequirement): void
    {
        $fileSystem = new InMemoryFileSystem();
        $fileSystem->write('.env.dist', '');
        $companion = new Companion($fileSystem, new InMemoryInteraction(), new Chained());
        $block = new Block('Block');
        $variable = new Variable('NAME', 'value');
        $chained = new Chained($extensions);

        $this->assertSame(
            ['value' => $expectedValue, 'requirement' => $expectedRequirement],
            [
                'value' => $chained->getVariableValue($companion, $block, $variable),
                'requirement' => $chained->isVariableRequiringValue($companion, $block, $variable),
            ]
        );
    }

    /**
     * @return array<string, array{0: list<Extension>, 1: string|null, 2: int}>
     */
    public static function extensionsDataProvider(): array
    {
        return [
            'no extensions' => [[], null, Extension::ABSTAIN],
            'no extension answers' => [[new AbstractExtension(), new AbstractExtension()], null, Extension::ABSTAIN],
            'first answer and first vote win' => [
                [
                    new AbstractExtension(),
                    self::extension('second', Extension::VARIABLE_REQUIRED),
                    self::extension('third', Extension::VARIABLE_SKIP),
                ],
                'second',
                Extension::VARIABLE_REQUIRED,
            ],
        ];
    }

    private static function extension(string $value, int $vote): Extension
    {
        return new class($value, $vote) extends AbstractExtension {
            private $value;
            private $vote;

            public function __construct(string $value, int $vote)
            {
                $this->value = $value;
                $this->vote = $vote;
            }

            public function getVariableValue(Companion $companion, Block $block, Variable $variable)
            {
                return $this->value;
            }

            public function isVariableRequiringValue(
                Companion $companion,
                Block $block,
                Variable $variable,
                ?string $currentValue = null
            ): int {
                return $this->vote;
            }
        };
    }
}
