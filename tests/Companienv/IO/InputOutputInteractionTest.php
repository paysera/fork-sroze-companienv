<?php

declare(strict_types=1);

namespace Companienv\IO;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class InputOutputInteractionTest extends TestCase
{
    /**
     * @dataProvider interactionDataProvider
     *
     * @param (callable(InputOutputInteraction): (bool|string))|(callable(InputOutputInteraction): void) $act
     * @param bool|string|array<string, string>|null $expectedResult
     */
    public function testInteraction(
        bool $interactive,
        string $answers,
        callable $act,
        $expectedResult,
        string $expectedOutput
    ): void {
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $answers);
        rewind($inputStream);
        $input = new ArrayInput([]);
        $input->setInteractive($interactive);
        $input->setStream($inputStream);
        $outputStream = fopen('php://memory', 'r+');
        $output = new StreamOutput($outputStream, OutputInterface::VERBOSITY_NORMAL, false);

        $interaction = new InputOutputInteraction($input, $output);

        try {
            $result = $act($interaction);
        } catch (RuntimeException $exception) {
            $result = [get_class($exception) => $exception->getMessage()];
        }

        rewind($output->getStream());
        $this->assertSame(
            ['result' => $expectedResult, 'output' => $expectedOutput],
            ['result' => $result, 'output' => stream_get_contents($output->getStream())]
        );
    }

    /**
     * @return iterable<string, array{
     *     interactive: bool,
     *     answers: string,
     *     act: (callable(InputOutputInteraction): (bool|string))|(callable(InputOutputInteraction): void),
     *     expectedResult: bool|string|array<string, string>|null,
     *     expectedOutput: string
     * }>
     */
    public function interactionDataProvider(): iterable
    {
        $confirm = static function (InputOutputInteraction $interaction): bool {
            return $interaction->askConfirmation("Let's fix this? (y) ");
        };
        $ask = static function (InputOutputInteraction $interaction): string {
            return $interaction->ask('MY_VARIABLE ? ', 'default-value');
        };
        $askFalsy = static function (InputOutputInteraction $interaction): string {
            return $interaction->ask('COUNT ? ', '0');
        };
        $askWithoutDefault = static function (InputOutputInteraction $interaction): string {
            return $interaction->ask('<comment>MY_VARIABLE</comment> ? ');
        };
        $askWithEmptyDefault = static function (InputOutputInteraction $interaction): string {
            return $interaction->ask('MY_VARIABLE ? ', '');
        };

        yield 'non-interactive answer' => [
            'interactive' => false,
            'answers' => '',
            'act' => $ask,
            'expectedResult' => 'default-value',
            'expectedOutput' => '',
        ];

        yield 'non-interactive falsy answer' => [
            'interactive' => false,
            'answers' => '',
            'act' => $askFalsy,
            'expectedResult' => '0',
            'expectedOutput' => '',
        ];

        yield 'non-interactive answer without a default' => [
            'interactive' => false,
            'answers' => '',
            'act' => $askWithoutDefault,
            'expectedResult' => [
                UnansweredQuestionException::class => 'Cannot answer a question without a default in non-interactive mode.',
            ],
            'expectedOutput' => "MY_VARIABLE ? \n",
        ];

        yield 'non-interactive empty default' => [
            'interactive' => false,
            'answers' => '',
            'act' => $askWithEmptyDefault,
            'expectedResult' => '',
            'expectedOutput' => '',
        ];

        yield 'interactive answer' => [
            'interactive' => true,
            'answers' => "my-value\n",
            'act' => $ask,
            'expectedResult' => 'my-value',
            'expectedOutput' => 'MY_VARIABLE ? ',
        ];

        yield 'interactive empty answer' => [
            'interactive' => true,
            'answers' => "\n",
            'act' => $ask,
            'expectedResult' => 'default-value',
            'expectedOutput' => 'MY_VARIABLE ? ',
        ];

        yield 'interactive empty answer to an empty default' => [
            'interactive' => true,
            'answers' => "\n",
            'act' => $askWithEmptyDefault,
            'expectedResult' => '',
            'expectedOutput' => 'MY_VARIABLE ? ',
        ];

        yield 'interactive answer after an empty one without a default' => [
            'interactive' => true,
            'answers' => "\nmy-value\n",
            'act' => $askWithoutDefault,
            'expectedResult' => 'my-value',
            'expectedOutput' => 'MY_VARIABLE ? MY_VARIABLE ? ',
        ];

        yield 'non-interactive confirmation' => [
            'interactive' => false,
            'answers' => '',
            'act' => $confirm,
            'expectedResult' => true,
            'expectedOutput' => '',
        ];

        yield 'interactive confirmation with y' => [
            'interactive' => true,
            'answers' => "y\n",
            'act' => $confirm,
            'expectedResult' => true,
            'expectedOutput' => "Let's fix this? (y) ",
        ];

        yield 'interactive confirmation with YES' => [
            'interactive' => true,
            'answers' => "YES\n",
            'act' => $confirm,
            'expectedResult' => true,
            'expectedOutput' => "Let's fix this? (y) ",
        ];

        yield 'interactive confirmation with an empty answer' => [
            'interactive' => true,
            'answers' => "\n",
            'act' => $confirm,
            'expectedResult' => true,
            'expectedOutput' => "Let's fix this? (y) ",
        ];

        yield 'interactive confirmation with n' => [
            'interactive' => true,
            'answers' => "n\n",
            'act' => $confirm,
            'expectedResult' => false,
            'expectedOutput' => "Let's fix this? (y) ",
        ];

        yield 'one line written' => [
            'interactive' => false,
            'answers' => '',
            'act' => static function (InputOutputInteraction $interaction): void {
                $interaction->writeln('<info>A message</info>');
            },
            'expectedResult' => null,
            'expectedOutput' => "A message\n",
        ];

        yield 'several lines written' => [
            'interactive' => false,
            'answers' => '',
            'act' => static function (InputOutputInteraction $interaction): void {
                $interaction->writeln(['', 'A message']);
            },
            'expectedResult' => null,
            'expectedOutput' => "\nA message\n",
        ];
    }
}
