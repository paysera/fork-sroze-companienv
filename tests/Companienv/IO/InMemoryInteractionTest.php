<?php

declare(strict_types=1);

namespace Companienv\IO;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InMemoryInteractionTest extends TestCase
{
    /**
     * @dataProvider askDataProvider
     *
     * @param array<string, string> $answers
     * @param string|array<string, string> $expectedResult
     */
    public function testAsk(
        array $answers,
        $expectedResult,
        ?string $default = null,
        bool $interactive = true
    ): void {
        $interaction = new InMemoryInteraction($answers, $interactive);

        try {
            $result = $interaction->ask('<comment>Question</comment>? ', $default);
        } catch (RuntimeException $exception) {
            $result = [get_class($exception) => $exception->getMessage()];
        }

        $this->assertSame($expectedResult, $result);
    }

    /**
     * @return iterable<string, array{
     *     answers: array<string, string>,
     *     expectedResult: string|array<string, string>,
     *     default: string|null,
     *     interactive: bool
     * }>
     */
    public function askDataProvider(): iterable
    {
        $noAnswer = [RuntimeException::class => 'No answer for question "Question?"'];
        $unanswered = [
            UnansweredQuestionException::class => 'Cannot answer "Question?" in non-interactive mode: '
                . 'the question has no default.',
        ];

        yield 'one answer' => [
            'answers' => ['Question?' => 'yes'],
            'expectedResult' => 'yes',
            'default' => null,
            'interactive' => true,
        ];

        yield 'no answer' => [
            'answers' => [],
            'expectedResult' => $noAnswer,
            'default' => null,
            'interactive' => true,
        ];

        yield 'no answer, a default: still unexpected' => [
            'answers' => [],
            'expectedResult' => $noAnswer,
            'default' => 'default',
            'interactive' => true,
        ];

        yield 'an answer over the default' => [
            'answers' => ['Question?' => 'yes'],
            'expectedResult' => 'yes',
            'default' => 'default',
            'interactive' => true,
        ];

        yield 'without interaction, no answer: the default' => [
            'answers' => [],
            'expectedResult' => 'default',
            'default' => 'default',
            'interactive' => false,
        ];

        yield 'without interaction, no answer and no default' => [
            'answers' => [],
            'expectedResult' => $unanswered,
            'default' => null,
            'interactive' => false,
        ];

        yield 'without interaction, an answer is ignored' => [
            'answers' => ['Question?' => 'yes'],
            'expectedResult' => 'default',
            'default' => 'default',
            'interactive' => false,
        ];

        yield 'without interaction, an answer cannot replace a missing default' => [
            'answers' => ['Question?' => 'yes'],
            'expectedResult' => $unanswered,
            'default' => null,
            'interactive' => false,
        ];
    }

    public function testAskReusesAnAnswer(): void
    {
        $interaction = new InMemoryInteraction(['Question?' => 'yes']);

        $firstAnswer = $interaction->ask('Question?');
        $secondAnswer = $interaction->ask('Question?');

        $this->assertSame(['yes', 'yes'], [$firstAnswer, $secondAnswer]);
    }

    /**
     * @dataProvider askConfirmationConfirmsAsTheConsoleDoesDataProvider
     *
     * @param array<string, string> $answers
     */
    public function testAskConfirmationConfirmsAsTheConsoleDoes(array $answers, bool $interactive, bool $expected): void
    {
        $interaction = new InMemoryInteraction($answers, $interactive);

        $this->assertSame($expected, $interaction->askConfirmation('<info>Confirm? (y) </info>'));
    }

    /**
     * @return iterable<string, array{answers: array<string, string>, interactive: bool, expected: bool}>
     */
    public function askConfirmationConfirmsAsTheConsoleDoesDataProvider(): iterable
    {
        yield 'y' => [
            'answers' => ['Confirm? (y)' => 'y'],
            'interactive' => true,
            'expected' => true,
        ];

        yield 'yes' => [
            'answers' => ['Confirm? (y)' => 'yes'],
            'interactive' => true,
            'expected' => true,
        ];

        yield 'Y' => [
            'answers' => ['Confirm? (y)' => 'Y'],
            'interactive' => true,
            'expected' => true,
        ];

        yield 'empty answer: the default' => [
            'answers' => ['Confirm? (y)' => ''],
            'interactive' => true,
            'expected' => true,
        ];

        yield 'n' => [
            'answers' => ['Confirm? (y)' => 'n'],
            'interactive' => true,
            'expected' => false,
        ];

        yield 'no' => [
            'answers' => ['Confirm? (y)' => 'no'],
            'interactive' => true,
            'expected' => false,
        ];

        yield 'without interaction: confirmed' => [
            'answers' => [],
            'interactive' => false,
            'expected' => true,
        ];

        yield 'without interaction, an answer of n: still confirmed' => [
            'answers' => ['Confirm? (y)' => 'n'],
            'interactive' => false,
            'expected' => true,
        ];
    }
}
