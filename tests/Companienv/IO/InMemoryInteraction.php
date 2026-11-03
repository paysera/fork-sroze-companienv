<?php

namespace Companienv\IO;

use RuntimeException;

class InMemoryInteraction implements Interaction
{
    /**
     * @var array<string, string|list<string>>
     */
    private $answers;

    private $interactive;

    private $buffer = '';

    /**
     * @param array<string, string|list<string>> $answers
     */
    public function __construct(array $answers = [], bool $interactive = true)
    {
        $this->answers = $answers;
        $this->interactive = $interactive;
    }

    public function askConfirmation(string $question): bool
    {
        if (!$this->interactive) {
            return true;
        }

        $answer = $this->ask($question, 'y');

        return in_array(strtolower($answer !== '' ? $answer : 'y'), ['y', 'yes'], true);
    }

    public function ask(string $question, ?string $default = null): string
    {
        $normalizedKey = trim(strip_tags($question));
        $this->buffer .= trim(strip_tags($question)) . "\n";

        if (!$this->interactive) {
            if ($default === null) {
                throw new UnansweredQuestionException($question);
            }

            return $default;
        }

        if (is_array($this->answers[$normalizedKey] ?? null)) {
            $answer = array_shift($this->answers[$normalizedKey]);
        } else {
            $answer = $this->answers[$normalizedKey] ?? null;
        }

        if ($answer !== null) {
            return $answer;
        }

        throw new RuntimeException(sprintf(
            'No answer for question "%s"',
            $normalizedKey
        ));
    }

    public function writeln($messageOrMessages)
    {
        if (!is_array($messageOrMessages)) {
            $messageOrMessages = [$messageOrMessages];
        }

        $this->buffer .= implode("\n", $messageOrMessages) . "\n";
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
