<?php

declare(strict_types=1);

namespace Companienv;

use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension\AbstractExtension;
use Companienv\Interaction\AskVariableValues;
use Companienv\IO\UnansweredQuestionException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
     * @dataProvider runDataProvider
     *
     * @param list<Extension>|null $extensions
     * @param list<Extension> $registeredExtensions
     */
    public function testRun(?array $extensions, array $registeredExtensions, string $expectedEnv): void
    {
        file_put_contents($this->temporaryDirectory . '/app.dist', "## Something\nMY_VARIABLE=default-value\n");
        $application = new Application($this->temporaryDirectory, $extensions);
        $this->registerExtensions($application, $registeredExtensions);
        $application->setAutoExit(false);
        $input = new ArrayInput(['--file' => 'app.env', '--dist-file' => 'app.dist', '--no-interaction' => true]);
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        $this->assertSame(
            [
                'exit code' => 0,
                'files' => ['app.dist' => "## Something\nMY_VARIABLE=default-value\n", 'app.env' => $expectedEnv],
                'output' => 'It looks like you are missing some configuration (1 variables). '
                    . "I will help you to sort this out.\n"
                    . "\nSomething\n\n"
                ,
            ],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory(), 'output' => $output->fetch()]
        );
    }

    /**
     * @return iterable<string, array{
     *     extensions: list<Extension>|null,
     *     registeredExtensions: list<Extension>,
     *     expectedEnv: string
     * }>
     */
    public function runDataProvider(): iterable
    {
        $extension = new class () extends AbstractExtension {
            public function getVariableValue(Companion $companion, Block $block, Variable $variable): string
            {
                return 'from-extension';
            }
        };
        $askVariableValues = new AskVariableValues();

        yield 'default extensions' => [
            'extensions' => null,
            'registeredExtensions' => [],
            'expectedEnv' => "MY_VARIABLE=default-value\n",
        ];
        yield 'registered extension asked first' => [
            'extensions' => null,
            'registeredExtensions' => [$extension],
            'expectedEnv' => "MY_VARIABLE=from-extension\n",
        ];
        yield 'extensions given to the constructor' => [
            'extensions' => [$extension, $askVariableValues],
            'registeredExtensions' => [],
            'expectedEnv' => "MY_VARIABLE=from-extension\n",
        ];
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
        $input = new ArrayInput(['--version' => true]);
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        $this->assertSame(
            ['exit code' => 0, 'output' => "Companienv 0.2.x-dev\n"],
            ['exit code' => $exitCode, 'output' => $output->fetch()]
        );
    }

    /**
     * @dataProvider runExitsWithTheCompanionResultAsSymfony34And44ReadItDataProvider
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
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        $this->assertSame($expectedExitCode, $exitCode);
    }

    /**
     * @return iterable<string, array{result: mixed, expectedExitCode: int}>
     */
    public function runExitsWithTheCompanionResultAsSymfony34And44ReadItDataProvider(): iterable
    {
        yield 'nothing' => ['result' => null, 'expectedExitCode' => 0];
        yield 'an integer' => ['result' => 3, 'expectedExitCode' => 3];
        yield 'a numeric string' => ['result' => '7', 'expectedExitCode' => 7];
        yield 'a float' => ['result' => 2.9, 'expectedExitCode' => 2];
        yield 'true' => ['result' => true, 'expectedExitCode' => 0];
        yield 'a string with a numeric prefix' => ['result' => '12abc', 'expectedExitCode' => 0];
        yield 'an array' => ['result' => [1], 'expectedExitCode' => 0];
    }

    public function testNamingAnotherCommandIsRefused(): void
    {
        file_put_contents($this->temporaryDirectory . '/.env.dist', "MY_VARIABLE=default-value\n");
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);
        $input = new ArrayInput(['command' => 'list', '--no-interaction' => true]);
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

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
        $input = new ArrayInput(['--no-interaction' => true]);
        $output = new BufferedOutput();

        $exitCode = $application->run($input, $output);

        $this->assertSame(
            ['exit code' => 0, 'files' => ['.env' => $distFile, '.env.dist' => $distFile]],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory()]
        );
    }

    public function testRunWithoutInteractionNamesTheUnansweredQuestionInQuietOutput(): void
    {
        file_put_contents(
            $this->temporaryDirectory . '/.env.dist',
            "## Keys\n#+rsa-pair(KEY_PATH PUB_PATH KEY_PASS)\nKEY_PATH=private.pem\nPUB_PATH=public.pem\nKEY_PASS=\n"
        );
        $application = new Application($this->temporaryDirectory);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $input = new ArrayInput(['--no-interaction' => true]);
        $output = new BufferedOutput(OutputInterface::VERBOSITY_QUIET);

        try {
            $result = $application->run($input, $output);
        } catch (RuntimeException $exception) {
            $result = [get_class($exception) => $exception->getMessage()];
        }

        $this->assertSame(
            [
                'result' => [
                    UnansweredQuestionException::class => 'Cannot answer "Enter pass phrase to protect the keys:" '
                        . 'in non-interactive mode: the question has no default.',
                ],
                'output' => '',
            ],
            ['result' => $result, 'output' => $output->fetch()]
        );
    }

    /**
     * @param list<Extension> $extensions
     */
    private function registerExtensions(Application $application, array $extensions): void
    {
        foreach ($extensions as $extension) {
            $application->registerExtension($extension);
        }
    }
}
