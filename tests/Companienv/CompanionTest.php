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
     */
    public function testFillGaps(array $files, array $answers, $expectedEnv, string $expectedOutput)
    {
        $fileSystem = new InMemoryFileSystem();
        foreach ($files as $path => $contents) {
            $fileSystem->write($path, $contents);
        }
        $interaction = new InMemoryInteraction($answers);

        (new Companion($fileSystem, $interaction, new Chained(Application::defaultExtensions())))->fillGaps();

        $this->assertSame(
            ['.env' => $expectedEnv, 'output' => $expectedOutput],
            ['.env' => $fileSystem->getContents('.env'), 'output' => $interaction->getBuffer()]
        );
    }

    public static function fillGapsDataProvider(): array
    {
        $missingOne = "It looks like you are missing some configuration (1 variables). I will help you to sort this out.\n"
            . "Let's fix this? (y)\n";

        return [
            'no env file: every variable is asked and written' => [
                ['.env.dist' => "## Something\n# With more details\nMY_VARIABLE=default-value\nOTHER_VARIABLE=\n"],
                ["Let's fix this? (y)" => 'y', 'MY_VARIABLE ? (default-value)' => 'my-value', 'OTHER_VARIABLE ?' => 'other-value'],
                "MY_VARIABLE=my-value\nOTHER_VARIABLE=other-value\n",
                "It looks like you are missing some configuration (2 variables). I will help you to sort this out.\n"
                . "Let's fix this? (y)\n\n<info>Something</info>\nWith more details\n\nMY_VARIABLE ? (default-value)\nOTHER_VARIABLE ?\n",
            ],
            'env file: only the missing variable is asked and appended' => [
                [
                    '.env.dist' => "## Something\nMY_VARIABLE=default-value\nA_NEW_VARIABLE=\n",
                    '.env' => "MY_VARIABLE=something-else\n",
                ],
                ["Let's fix this? (y)" => 'y', 'A_NEW_VARIABLE ?' => 'value'],
                "MY_VARIABLE=something-else\nA_NEW_VARIABLE=value\n",
                $missingOne . "\n<info>Something</info>\n\nA_NEW_VARIABLE ?\n",
            ],
            'empty value with a non-empty reference: asked again and updated in place' => [
                [
                    '.env.dist' => "MY_VARIABLE=default-value\nOTHER_VARIABLE=kept\n",
                    '.env' => "MY_VARIABLE=\nOTHER_VARIABLE=kept\n",
                ],
                ["Let's fix this? (y)" => 'y', 'MY_VARIABLE ? (default-value)' => 'my-value'],
                "MY_VARIABLE=my-value\nOTHER_VARIABLE=kept\n",
                $missingOne . "\nMY_VARIABLE ? (default-value)\n",
            ],
            'empty value updated with a value containing a dollar and a digit' => [
                ['.env.dist' => "DB_PASSWORD=secret\n", '.env' => "DB_PASSWORD=\n"],
                ["Let's fix this? (y)" => 'y', 'DB_PASSWORD ? (secret)' => 'pa$1ss'],
                'DB_PASSWORD=pa$1ss' . "\n",
                $missingOne . "\nDB_PASSWORD ? (secret)\n",
            ],
            'empty value with an empty reference: nothing is asked' => [
                ['.env.dist' => "EMPTY_VARIABLE=\n", '.env' => "EMPTY_VARIABLE=\n"],
                [],
                "EMPTY_VARIABLE=\n",
                '',
            ],
            'falsy value: kept' => [
                ['.env.dist' => "MY_VARIABLE=default-value\n", '.env' => "MY_VARIABLE=0\n"],
                [],
                "MY_VARIABLE=0\n",
                '',
            ],
            'value containing an equals sign' => [
                ['.env.dist' => "A_BASE64_VALUE=abc123=\n", '.env' => "A_BASE64_VALUE=\n"],
                ["Let's fix this? (y)" => 'y', 'A_BASE64_VALUE ? (abc123=)' => 'abc123='],
                "A_BASE64_VALUE=abc123=\n",
                $missingOne . "\nA_BASE64_VALUE ? (abc123=)\n",
            ],
            'confirmation declined: nothing is written' => [
                ['.env.dist' => "MY_VARIABLE=default-value\n"],
                ["Let's fix this? (y)" => ''],
                false,
                $missingOne . "\n<comment>I let you think about it then. Re-run the command to get started again.</comment>\n\n",
            ],
        ];
    }
}
