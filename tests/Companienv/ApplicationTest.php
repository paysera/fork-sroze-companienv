<?php

declare(strict_types=1);

namespace Companienv;

use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension\AbstractExtension;
use Companienv\Interaction\AskVariableValues;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationTest extends TestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @dataProvider extensionsDataProvider
     *
     * @param list<Extension>|null $extensions
     * @param list<Extension> $registeredExtensions
     */
    public function testRun(?array $extensions, array $registeredExtensions, string $expectedEnv): void
    {
        file_put_contents($this->temporaryDirectory . '/app.dist', "## Something\nMY_VARIABLE=default-value\n");
        $application = new Application($this->temporaryDirectory, $extensions);
        foreach ($registeredExtensions as $extension) {
            $application->registerExtension($extension);
        }
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(
            new ArrayInput(['--file' => 'app.env', '--dist-file' => 'app.dist', '--no-interaction' => true]),
            $output
        );

        $this->assertSame(
            [
                'exit code' => 0,
                'files' => ['app.dist' => "## Something\nMY_VARIABLE=default-value\n", 'app.env' => $expectedEnv],
                'output' => "It looks like you are missing some configuration (1 variables). I will help you to sort this out.\n"
                    . "\nSomething\n\n",
            ],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory(), 'output' => $output->fetch()]
        );
    }

    public function testCommands(): void
    {
        $application = new Application($this->temporaryDirectory);

        $this->assertSame(
            ['companion' => true, 'help' => true, 'list' => true],
            [
                'companion' => $application->has('companion'),
                'help' => $application->has('help'),
                'list' => $application->has('list'),
            ]
        );
    }

    public function testNamingAnotherCommandIsRefused(): void
    {
        file_put_contents($this->temporaryDirectory . '/.env.dist', "MY_VARIABLE=default-value\n");
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);

        $exitCode = $application->run(
            new ArrayInput(['command' => 'list', '--no-interaction' => true]),
            new BufferedOutput()
        );

        $this->assertSame(
            ['exit code' => 1, 'files' => ['.env.dist' => "MY_VARIABLE=default-value\n"]],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory()]
        );
    }

    public function testKeyPairWithoutInteractionStopsAtThePassPhrase(): void
    {
        $distFile = "## Keys\n#+rsa-pair(KEY_PATH PUB_PATH KEY_PASS)\n"
            . "KEY_PATH=private.pem\nPUB_PATH=public.pem\nKEY_PASS=\n";
        file_put_contents($this->temporaryDirectory . '/.env.dist', $distFile);
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $error = [];
        try {
            $application->run(new ArrayInput(['--no-interaction' => true]), new BufferedOutput());
        } catch (RuntimeException $exception) {
            $error = [get_class($exception) => $exception->getMessage()];
        }

        $this->assertSame(
            [
                'error' => [
                    RuntimeException::class => 'Cannot answer "Enter pass phrase to protect the keys:" '
                        . 'in non-interactive mode: the question has no default.',
                ],
                'files' => ['.env.dist' => $distFile],
            ],
            ['error' => $error, 'files' => $this->readTemporaryDirectory()]
        );
    }

    /**
     * @return array<string, array{0: list<Extension>|null, 1: list<Extension>, 2: string}>
     */
    public static function extensionsDataProvider(): array
    {
        $extension = new class() extends AbstractExtension {
            public function getVariableValue(Companion $companion, Block $block, Variable $variable)
            {
                return 'from-extension';
            }
        };

        return [
            'default extensions' => [null, [], "MY_VARIABLE=default-value\n"],
            'registered extension asked first' => [null, [$extension], "MY_VARIABLE=from-extension\n"],
            'extensions given to the constructor' => [
                [$extension, new AskVariableValues()],
                [],
                "MY_VARIABLE=from-extension\n",
            ],
        ];
    }
}
