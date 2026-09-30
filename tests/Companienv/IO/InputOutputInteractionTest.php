<?php

declare(strict_types=1);

namespace Companienv\IO;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

final class InputOutputInteractionTest extends TestCase
{
    /**
     * @dataProvider interactionDataProvider
     */
    public function testInteraction(bool $interactive, string $answers, callable $act, $expectedResult, string $expectedOutput)
    {
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $answers);
        rewind($inputStream);
        $input = new ArrayInput([]);
        $input->setInteractive($interactive);
        $input->setStream($inputStream);
        $output = new StreamOutput(fopen('php://memory', 'r+'), OutputInterface::VERBOSITY_NORMAL, false);

        $result = $act(new InputOutputInteraction($input, $output));

        rewind($output->getStream());
        $this->assertSame(
            ['result' => $expectedResult, 'output' => $expectedOutput],
            ['result' => $result, 'output' => stream_get_contents($output->getStream())]
        );
    }

    public static function interactionDataProvider(): array
    {
        $confirm = static function (InputOutputInteraction $interaction) {
            return $interaction->askConfirmation("Let's fix this? (y) ");
        };
        $ask = static function (InputOutputInteraction $interaction) {
            return $interaction->ask('MY_VARIABLE ? ', 'default-value');
        };
        $askFalsy = static function (InputOutputInteraction $interaction) {
            return $interaction->ask('COUNT ? ', '0');
        };

        return [
            'non-interactive answer' => [false, '', $ask, 'default-value', ''],
            'non-interactive falsy answer' => [false, '', $askFalsy, '0', ''],
            'interactive answer' => [true, "my-value\n", $ask, 'my-value', 'MY_VARIABLE ? '],
            'interactive empty answer' => [true, "\n", $ask, 'default-value', 'MY_VARIABLE ? '],
            'non-interactive confirmation' => [false, '', $confirm, true, ''],
            'interactive confirmation with y' => [true, "y\n", $confirm, true, "Let's fix this? (y) "],
            'interactive confirmation with YES' => [true, "YES\n", $confirm, true, "Let's fix this? (y) "],
            'interactive confirmation with an empty answer' => [true, "\n", $confirm, true, "Let's fix this? (y) "],
            'interactive confirmation with n' => [true, "n\n", $confirm, false, "Let's fix this? (y) "],
            'one line written' => [
                false,
                '',
                static function (InputOutputInteraction $interaction) {
                    return $interaction->writeln('<info>A message</info>');
                },
                null,
                "A message\n",
            ],
            'several lines written' => [
                false,
                '',
                static function (InputOutputInteraction $interaction) {
                    return $interaction->writeln(['', 'A message']);
                },
                null,
                "\nA message\n",
            ],
        ];
    }
}
