<?php

namespace Companienv\Composer;

use Companienv\IO\Interaction;
use Composer\IO\IOInterface;
use RuntimeException;

class InteractionViaComposer implements Interaction
{
    private $io;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function askConfirmation(string $question): bool
    {
        if (!$this->io->isInteractive()) {
            $this->writeln('Automatically confirmed in non-interactive mode');

            return true;
        }

        return $this->io->askConfirmation($question);
    }

    public function ask(string $question, ?string $default = null): string
    {
        if (!$this->io->isInteractive()) {
            if (null === $default) {
                throw new RuntimeException(sprintf(
                    'Cannot answer "%s" in non-interactive mode: the question has no default.',
                    trim(strip_tags($question))
                ));
            }

            $this->writeln(sprintf('Automatically returned "%s" in non-interactive mode', $default));

            return $default;
        }

        $answer = $this->io->ask($question, $default);

        return null === $answer ? $this->ask($question, $default) : $answer;
    }

    public function writeln($messageOrMessages)
    {
        return $this->io->write($messageOrMessages);
    }
}
