<?php

declare(strict_types=1);

namespace Companienv;

use Companienv\Extension\Chained;
use Companienv\IO\InMemoryFileSystem;
use Companienv\IO\InMemoryInteraction;
use PHPUnit\Framework\TestCase;

final class CompanionTest extends TestCase
{
    /**
     * @dataProvider fillGapsDataProvider
     *
     * @param array<string, string> $files
     * @param array<string, string> $answers
     */
    public function testFillGaps(
        array $files,
        array $answers,
        ?string $expectedEnv,
        string $expectedOutput
    ): void {
        $fileSystem = $this->createFileSystem($files);
        $interaction = new InMemoryInteraction($answers);
        $extensions = new Chained(Application::defaultExtensions());
        $companion = new Companion($fileSystem, $interaction, $extensions);

        $companion->fillGaps();

        $this->assertSame(
            ['.env' => $expectedEnv, 'output' => $expectedOutput],
            [
                '.env' => $fileSystem->exists('.env') ? $fileSystem->getContents('.env') : null,
                'output' => $interaction->getBuffer(),
            ]
        );
    }

    /**
     * @return iterable<string, array{
     *     files: array<string, string>,
     *     answers: array<string, string>,
     *     expectedEnv: string|null,
     *     expectedOutput: string
     * }>
     */
    public function fillGapsDataProvider(): iterable
    {
        $missingOne = 'It looks like you are missing some configuration (1 variables). '
            . "I will help you to sort this out.\n"
            . "Let's fix this? (y)\n"
        ;

        yield 'no env file: every variable is asked and written' => [
            'files' => ['.env.dist' => "## Something\n# With more details\nMY_VARIABLE=default-value\nOTHER_VARIABLE=\n"],
            'answers' => [
                "Let's fix this? (y)" => 'y',
                'MY_VARIABLE ? (default-value)' => 'my-value',
                'OTHER_VARIABLE ?' => 'other-value',
            ],
            'expectedEnv' => "MY_VARIABLE=my-value\nOTHER_VARIABLE=other-value\n",
            'expectedOutput' => "It looks like you are missing some configuration (2 variables). I will help you to sort this out.\n"
                . "Let's fix this? (y)\n\n<info>Something</info>\nWith more details\n\n"
                . "MY_VARIABLE ? (default-value)\nOTHER_VARIABLE ?\n"
            ,
        ];
        yield 'env file: only the missing variable is asked and appended' => [
            'files' => [
                '.env.dist' => "## Something\nMY_VARIABLE=default-value\nA_NEW_VARIABLE=\n",
                '.env' => "MY_VARIABLE=something-else\n",
            ],
            'answers' => ["Let's fix this? (y)" => 'y', 'A_NEW_VARIABLE ?' => 'value'],
            'expectedEnv' => "MY_VARIABLE=something-else\nA_NEW_VARIABLE=value\n",
            'expectedOutput' => $missingOne . "\n<info>Something</info>\n\nA_NEW_VARIABLE ?\n",
        ];
        yield 'block without missing variables: skipped' => [
            'files' => [
                '.env.dist' => "## Complete\nMY_VARIABLE=default-value\n## Incomplete\nA_NEW_VARIABLE=\n",
                '.env' => "MY_VARIABLE=something-else\n",
            ],
            'answers' => ["Let's fix this? (y)" => 'y', 'A_NEW_VARIABLE ?' => 'value'],
            'expectedEnv' => "MY_VARIABLE=something-else\nA_NEW_VARIABLE=value\n",
            'expectedOutput' => $missingOne . "\n<info>Incomplete</info>\n\nA_NEW_VARIABLE ?\n",
        ];
        yield 'empty value with a non-empty reference: asked again and updated in place' => [
            'files' => [
                '.env.dist' => "MY_VARIABLE=default-value\nOTHER_VARIABLE=kept\n",
                '.env' => "MY_VARIABLE=\nOTHER_VARIABLE=kept\n",
            ],
            'answers' => ["Let's fix this? (y)" => 'y', 'MY_VARIABLE ? (default-value)' => 'my-value'],
            'expectedEnv' => "MY_VARIABLE=my-value\nOTHER_VARIABLE=kept\n",
            'expectedOutput' => $missingOne . "\nMY_VARIABLE ? (default-value)\n",
        ];
        yield 'empty value with an empty reference: nothing is asked' => [
            'files' => ['.env.dist' => "EMPTY_VARIABLE=\n", '.env' => "EMPTY_VARIABLE=\n"],
            'answers' => [],
            'expectedEnv' => "EMPTY_VARIABLE=\n",
            'expectedOutput' => '',
        ];
        yield 'falsy value: kept' => [
            'files' => ['.env.dist' => "MY_VARIABLE=default-value\n", '.env' => "MY_VARIABLE=0\n"],
            'answers' => [],
            'expectedEnv' => "MY_VARIABLE=0\n",
            'expectedOutput' => '',
        ];
        yield 'value containing an equals sign' => [
            'files' => ['.env.dist' => "A_BASE64_VALUE=abc123=\n", '.env' => "A_BASE64_VALUE=\n"],
            'answers' => ["Let's fix this? (y)" => 'y', 'A_BASE64_VALUE ? (abc123=)' => 'abc123='],
            'expectedEnv' => "A_BASE64_VALUE=abc123=\n",
            'expectedOutput' => $missingOne . "\nA_BASE64_VALUE ? (abc123=)\n",
        ];
        yield 'confirmation declined: nothing is written' => [
            'files' => ['.env.dist' => "MY_VARIABLE=default-value\n"],
            'answers' => ["Let's fix this? (y)" => 'n'],
            'expectedEnv' => null,
            'expectedOutput' => $missingOne . "\n<comment>I let you think about it then. "
                . "Re-run the command to get started again.</comment>\n\n"
            ,
        ];
    }

    /**
     * @param array<string, string> $files
     */
    private function createFileSystem(array $files): InMemoryFileSystem
    {
        $fileSystem = new InMemoryFileSystem();
        foreach ($files as $path => $contents) {
            $fileSystem->write($path, $contents);
        }

        return $fileSystem;
    }
}
