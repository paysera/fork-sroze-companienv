<?php

declare(strict_types=1);

namespace Companienv;

use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension\AbstractExtension;
use Companienv\Interaction\AskVariableValues;
use PHPUnit\Framework\TestCase;
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
     */
    public function testRun(?array $extensions, array $registeredExtensions, string $expectedEnv)
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
                'output' => "It looks like you are missing some configuration (1 variables). I will help you to sort this out.\n\nSomething\n\n",
            ],
            ['exit code' => $exitCode, 'files' => $this->readTemporaryDirectory(), 'output' => $output->fetch()]
        );
    }

    public static function extensionsDataProvider(): array
    {
        $extension = new class extends AbstractExtension {
            public function getVariableValue(Companion $companion, Block $block, Variable $variable)
            {
                return 'from-extension';
            }
        };

        return [
            'default extensions' => [null, [], "MY_VARIABLE=default-value\n"],
            'registered extension asked first' => [null, [$extension], "MY_VARIABLE=from-extension\n"],
            'extensions given to the constructor' => [[$extension, new AskVariableValues()], [], "MY_VARIABLE=from-extension\n"],
        ];
    }
}
