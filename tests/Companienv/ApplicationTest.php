<?php

declare(strict_types=1);

namespace Companienv;

use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension\AbstractExtension;
use Companienv\Interaction\AskVariableValues;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
                'output' => 'It looks like you are missing some configuration (1 variables). '
                    . "I will help you to sort this out.\n"
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

    public function testVersion(): void
    {
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);
        $output = new BufferedOutput();

        $exitCode = $application->run(new ArrayInput(['--version' => true]), $output);

        $this->assertSame(
            ['exit code' => 0, 'output' => "Companienv 0.1.x-dev\n"],
            ['exit code' => $exitCode, 'output' => $output->fetch()]
        );
    }

    /**
     * @dataProvider companionResultDataProvider
     *
     * @param mixed $result
     */
    public function testRunExitsWithTheCompanionResultAsSymfony34And44ReadIt($result, int $expectedExitCode): void
    {
        $application = new class ($this->temporaryDirectory, $result) extends Application {
            /**
             * @var mixed
             */
            private $result;

            /**
             * @param mixed $result
             */
            public function __construct(string $rootDirectory, $result)
            {
                parent::__construct($rootDirectory);

                $this->result = $result;
            }

            public function companion(InputInterface $input, OutputInterface $output)
            {
                return $this->result;
            }
        };
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->assertSame($expectedExitCode, $application->run(new ArrayInput([]), new BufferedOutput()));
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function companionResultDataProvider(): array
    {
        return [
            'nothing' => [null, 0],
            'an integer' => [3, 3],
            'a numeric string' => ['7', 7],
            'a float' => [2.9, 2],
            'true' => [true, 0],
            'a string with a numeric prefix' => ['12abc', 0],
            'an array' => [[1], 0],
        ];
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

    public function testRunWithoutInteractionAcceptsEmptyAndFalsyDefaults(): void
    {
        $distFile = "EMPTY_VARIABLE=\nZERO=0\n";
        file_put_contents($this->temporaryDirectory . '/.env.dist', $distFile);
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $exitCode = $application->run(new ArrayInput(['--no-interaction' => true]), new BufferedOutput());

        $this->assertSame(
            ['exit code' => 0, 'files' => ['.env' => $distFile, '.env.dist' => $distFile]],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory()]
        );
    }

    /**
     * @return array<string, array{0: list<Extension>|null, 1: list<Extension>, 2: string}>
     */
    public static function extensionsDataProvider(): array
    {
        $extension = new class () extends AbstractExtension {
            public function getVariableValue(Companion $companion, Block $block, Variable $variable): string
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
