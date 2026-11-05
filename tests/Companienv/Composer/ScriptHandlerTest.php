<?php

declare(strict_types=1);

namespace Companienv\Composer;

use Companienv\TemporaryDirectory;
use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use PHPUnit\Framework\TestCase;

final class ScriptHandlerTest extends TestCase
{
    use TemporaryDirectory;

    /**
     * @var string|false|null
     */
    private $workingDirectory;

    protected function setUp(): void
    {
        $this->workingDirectory = getcwd();
        chdir($this->createTemporaryDirectory());
    }

    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        $this->removeTemporaryDirectory();
    }

    /**
     * @dataProvider runDataProvider
     *
     * @param array<string, mixed> $extra
     * @param array<string, string> $files
     * @param array<string, string> $expectedFiles
     */
    public function testRun(array $extra, array $files, array $expectedFiles, string $expectedOutput): void
    {
        $this->writeTemporaryFiles($files);
        $package = new RootPackage('acme/application', '1.0.0.0', '1.0.0');
        $package->setExtra($extra);
        $composer = new Composer();
        $composer->setPackage($package);
        $io = new BufferIO();
        $event = new Event('post-install-cmd', $composer, $io);

        ScriptHandler::run($event);

        $this->assertSame(
            ['files' => $expectedFiles, 'output' => $expectedOutput],
            ['files' => $this->readTemporaryDirectory(), 'output' => $io->getOutput()]
        );
    }

    /**
     * @return iterable<string, array{
     *     extra: array<string, mixed>,
     *     files: array<string, string>,
     *     expectedFiles: array<string, string>,
     *     expectedOutput: string
     * }>
     */
    public function runDataProvider(): iterable
    {
        $distFile = "## Database\nDATABASE_HOST=localhost\nDATABASE_PORT=3306\n";
        $output = "It looks like you are missing some configuration (2 variables). I will help you to sort this out.\n"
            . "Automatically confirmed in non-interactive mode\n\nDatabase\n\n"
            . "Automatically returned \"localhost\" in non-interactive mode\n"
            . "Automatically returned \"3306\" in non-interactive mode\n"
        ;
        yield 'one pair of files' => [
            'extra' => ['companienv-parameters' => [['file' => '.env', 'dist-file' => '.dist.env']]],
            'files' => ['.dist.env' => $distFile],
            'expectedFiles' => ['.dist.env' => $distFile, '.env' => "DATABASE_HOST=localhost\nDATABASE_PORT=3306\n"],
            'expectedOutput' => $output,
        ];
        yield 'two pairs of files' => [
            'extra' => [
                'companienv-parameters' => [
                    ['file' => '.env', 'dist-file' => '.dist.env'],
                    ['file' => '.env.test', 'dist-file' => '.dist.env.test'],
                ],
            ],
            'files' => ['.dist.env' => $distFile, '.dist.env.test' => "APP_ENV=test\n"],
            'expectedFiles' => [
                '.dist.env' => $distFile,
                '.dist.env.test' => "APP_ENV=test\n",
                '.env' => "DATABASE_HOST=localhost\nDATABASE_PORT=3306\n",
                '.env.test' => "APP_ENV=test\n",
            ],
            'expectedOutput' => $output
                . "It looks like you are missing some configuration (1 variables). I will help you to sort this out.\n"
                . "Automatically confirmed in non-interactive mode\n\n"
                . "Automatically returned \"test\" in non-interactive mode\n"
            ,
        ];
        yield 'no parameters: .env from .env.dist' => [
            'extra' => [],
            'files' => ['.env.dist' => $distFile],
            'expectedFiles' => ['.env' => "DATABASE_HOST=localhost\nDATABASE_PORT=3306\n", '.env.dist' => $distFile],
            'expectedOutput' => $output,
        ];
    }
}
