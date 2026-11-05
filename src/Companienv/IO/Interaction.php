<?php

namespace Companienv\IO;

interface Interaction
{
    public function askConfirmation(string $question): bool;

    /**
     * @throws UnansweredQuestionException
     */
    public function ask(string $question, ?string $default = null): string;

    public function writeln($messageOrMessages);
}
