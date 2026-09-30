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
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FileToPropagateTest extends TestCase
{
    private const QUESTION = 'KEY_PATH: What is the path of your downloaded file?';

    /**
     * @dataProvider propagationDataProvider
     *
     * @param array<string, string> $files
     * @param array<string, string> $answers
     */
    public function testGetVariableValue(
        Block $block,
        array $files,
        array $answers,
        ?string $expectedValue,
        string $expectedQuestions,
        ?string $expectedTarget
    ): void {
        $fileSystem = $this->fileSystem($files);
        $interaction = new InMemoryInteraction($answers);

        $value = (new FileToPropagate())->getVariableValue(
            new Companion($fileSystem, $interaction, new Chained()),
            $block,
            new Variable('KEY_PATH', 'target.pem')
        );

        $this->assertSame(
            ['value' => $expectedValue, 'questions' => $expectedQuestions, 'target' => $expectedTarget],
            [
                'value' => $value,
                'questions' => $interaction->getBuffer(),
                'target' => $fileSystem->exists('target.pem') ? $fileSystem->getContents('target.pem') : null,
            ]
        );
    }

    /**
     * @return array<string, array{0: Block, 1: array<string, string>, 2: array<string, string>, 3: string|null, 4: string, 5: string|null}>
     */
    public static function propagationDataProvider(): array
    {
        return [
            'no file-to-propagate attribute' => [new Block('Keys'), [], [], null, '', null],
            'target present and variable defined: kept' => [
                self::block(),
                ['target.pem' => 'CURRENT', '.env' => "KEY_PATH=elsewhere.pem\n"],
                [],
                'elsewhere.pem',
                '',
                'CURRENT',
            ],
            'copied from the downloaded file' => [
                self::block(),
                ['/downloads/key.pem' => 'DOWNLOADED'],
                [self::QUESTION => '/downloads/key.pem'],
                'target.pem',
                self::QUESTION . "\n",
                'DOWNLOADED',
            ],
        ];
    }

    public function testGetVariableValueRejectsAMissingDownloadedFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "/downloads/missing.pem" does not exist');

        (new FileToPropagate())->getVariableValue(
            new Companion(
                $this->fileSystem([]),
                new InMemoryInteraction([self::QUESTION => '/downloads/missing.pem']),
                new Chained()
            ),
            self::block(),
            new Variable('KEY_PATH', 'target.pem')
        );
    }

    /**
     * @dataProvider requirementDataProvider
     */
    public function testIsVariableRequiringValue(Block $block, int $expected): void
    {
        $this->assertSame(
            $expected,
            (new FileToPropagate())->isVariableRequiringValue(
                new Companion($this->fileSystem([]), new InMemoryInteraction(), new Chained()),
                $block,
                new Variable('KEY_PATH', 'target.pem')
            )
        );
    }

    /**
     * @return array<string, array{0: Block, 1: int}>
     */
    public static function requirementDataProvider(): array
    {
        return [
            'no file-to-propagate attribute' => [new Block('Keys'), Extension::ABSTAIN],
            'target file absent' => [self::block(), Extension::ABSTAIN],
        ];
    }

    private static function block(): Block
    {
        return new Block('Keys', '', [], [new Attribute('file-to-propagate', ['KEY_PATH'], [])]);
    }

    /**
     * @param array<string, string> $files
     */
    private function fileSystem(array $files): InMemoryFileSystem
    {
        $fileSystem = new InMemoryFileSystem();
        foreach (['.env.dist' => ''] + $files as $path => $contents) {
            $fileSystem->write($path, $contents);
        }

        return $fileSystem;
    }
}
