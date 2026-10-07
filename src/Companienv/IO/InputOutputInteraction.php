<?php

namespace Companienv\IO;

use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class InputOutputInteraction implements Interaction
{
    private $input;
    private $output;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    public function askConfirmation(string $question) : bool
    {
        return in_array(strtolower($this->ask($question, 'y')), ['y', 'yes']);
    }

    public function ask(string $question, ?string $default = null) : string
    {
        $answer = (new QuestionHelper())->ask($this->input, $this->output, new Question($question, $default));

        if ($this->input->isInteractive() && (null === $answer || ('' === $answer && $default !== null))) {
            return $this->ask($question, $default);
        }

        if (null === $answer) {
            throw new RuntimeException(sprintf(
                'Cannot answer "%s" in non-interactive mode: the question has no default.',
                trim(strip_tags($question))
            ));
        }

        return $answer;
    }

    public function writeln($messageOrMessages)
    {
        return $this->output->writeln($messageOrMessages);
    }
}
