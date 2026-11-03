<?php

namespace spec\Companienv\IO;

use PhpSpec\ObjectBehavior;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InputOutputInteractionSpec extends ObjectBehavior
{
    public function let(InputInterface $input, OutputInterface $output)
    {
        $input->isInteractive()->willReturn(false);
        $this->beConstructedWith($input, $output);
    }

    public function it_will_return_the_default_value()
    {
        $this->ask('VALUE ?', 'true')->shouldReturn('true');
    }

    public function it_will_return_the_default_value_even_if_it_looks_falsy()
    {
        $this->ask('COUNT ?', '0')->shouldReturn('0');
    }
}
