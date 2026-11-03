<?php

namespace Companienv;

use Companienv\DotEnv\Block;
use Companienv\DotEnv\MissingVariable;
use Companienv\DotEnv\Parser;
use Companienv\DotEnv\ValueFormatter;
use Companienv\DotEnv\Variable;
use Companienv\IO\FileSystem\FileSystem;
use Companienv\IO\Interaction;
use Jackiedo\DotenvEditor\DotenvWriter;
use Symfony\Component\Dotenv\Dotenv;

class Companion
{
    private $fileSystem;
    private $interaction;
    private $reference;
    private $extension;
    private $envFileName;
    // Never a read result, so that .env is parsed the first time
    private $envContents = false;
    private $definedVariables = [];
    private $resolvedDistValues = [];
    /**
     * @var array<string, true>
     */
    private $skippedVariableNames;

    public function __construct(FileSystem $fileSystem, Interaction $interaction, Extension $extension, string $envFileName = '.env', string $distFileName = '.env.dist')
    {
        $this->fileSystem = $fileSystem;
        $this->interaction = $interaction;
        $this->extension = $extension;
        $this->reference = (new Parser())->parse($fileSystem, $distFileName);
        $this->envFileName = $envFileName;
        $this->skippedVariableNames = [];
    }

    public function fillGaps()
    {
        $missingVariables = $this->getVariablesRequiringValues();
        if (count($missingVariables) == 0) {
            return;
        }

        $this->interaction->writeln(sprintf(
            'It looks like you are missing some configuration (%d variables). I will help you to sort this out.',
            count($missingVariables)
        ));

        if (!$this->askConfirmation('<info>Let\'s fix this? (y) </info>')) {
            $this->interaction->writeln([
                '',
                '<comment>I let you think about it then. Re-run the command to get started again.</comment>',
                ''
            ]);

            return;
        }

        foreach ($this->reference->getBlocks() as $block) {
            $this->fillBlockGaps($block, $missingVariables);
        }
    }

    private function fillBlockGaps(Block $block, array $missingVariables)
    {
        $variablesInBlock = $block->getVariablesInBlock($missingVariables);
        if (count($variablesInBlock) == 0) {
            return;
        }

        if (!empty($title = $block->getTitle())) {
            $this->interaction->writeln([
                '',
                '<info>' . $block->getTitle() . '</info>',
            ]);
        }

        if (!empty($description = $block->getDescription())) {
            $this->interaction->writeln($description);
        }

        $this->interaction->writeln('');

        foreach ($block->getVariables() as $variable) {
            if (isset($missingVariables[$variable->getName()])) {
                $this->writeVariable($variable, $this->extension->getVariableValue($this, $block, $variable));
            }
        }
    }

    private function writeVariable(Variable $variable, string $value) : void
    {
        $name = $variable->getName();

        if (!$this->fileSystem->exists($this->envFileName)) {
            $this->fileSystem->write($this->envFileName, '');
        }

        $variablesInFileHash = $this->getDefinedVariablesHash();
        if (($variablesInFileHash[$name] ?? null) === $value) {
            return;
        }

        $writer = new DotenvWriter(new ValueFormatter($variable->getValue()));
        $fileContents = $this->fileSystem->getContents($this->envFileName);
        $writer->setBuffer($fileContents);

        if (isset($variablesInFileHash[$name])) {
            $writer->updateSetter($name, $value, '');
        } else {
            $writer->appendSetter($name, $value, '');
        }

        $this->fileSystem->write($this->envFileName, $writer->getBuffer());
    }

    /**
     * @return MissingVariable[]
     */
    private function getVariablesRequiringValues()
    {
        $this->skippedVariableNames = [];
        $missingVariables = $this->voteForVariables();
        if ($this->hasSkippedVariables()) {
            $this->resolvedDistValues = [];
            $missingVariables = $this->voteForVariables();
        }

        return $missingVariables;
    }

    private function hasSkippedVariables(): bool
    {
        return count($this->skippedVariableNames) > 0;
    }

    /**
     * @return MissingVariable[]
     */
    private function voteForVariables(): array
    {
        $variablesInFile = $this->getDefinedVariablesHash();
        $missingVariables = [];

        foreach ($this->reference->getBlocks() as $block) {
            foreach ($block->getVariables() as $variable) {
                $currentValue = isset($variablesInFile[$variable->getName()]) ? $variablesInFile[$variable->getName()] : null;

                $vote = $this->extension->isVariableRequiringValue($this, $block, $variable, $currentValue);
                if ($vote === Extension::VARIABLE_REQUIRED) {
                    $missingVariables[$variable->getName()] = new MissingVariable($variable, $currentValue);
                } elseif ($vote === Extension::VARIABLE_SKIP) {
                    $this->skippedVariableNames[$variable->getName()] = true;
                }
            }
        }

        return $missingVariables;
    }

    public function getDefinedVariablesHash()
    {
        $envContents = $this->fileSystem->exists($this->envFileName)
            ? $this->fileSystem->getContents($this->envFileName)
            : null;

        // Parsed once for each .env content, so that a command in .env does not run again until .env changes. The
        // .env.dist values are resolved again then, and for the second vote of a run that skips a variable
        if ($envContents !== $this->envContents) {
            // Dotenv 4.4.0 and 5.0.0 raise a notice for an undefined ${VAR}, which Composer turns into an exception
            $this->definedVariables = null === $envContents
                ? []
                : @(new Dotenv())->parse($envContents, $this->envFileName);
            $this->resolvedDistValues = [];
            $this->envContents = $envContents;
        }

        return $this->definedVariables;
    }

    /**
     * The path set in .env, or the .env.dist one as Dotenv reads it when .env sets none.
     */
    public function getVariablePath(Variable $variable) : string
    {
        $path = $this->getDefinedPath($variable);

        return $path !== '' ? $path : $this->resolveDistValue($variable);
    }

    public function getVariablePathForEnvFile(Variable $variable): ?string
    {
        $path = $this->getDefinedPath($variable);

        return $path !== '' ? $path : $variable->getValue();
    }

    /**
     * @param string[] $variableNames
     *
     * @return string[]
     */
    public function getVariablePaths(Block $block, array $variableNames): array
    {
        return array_map(function (string $variableName) use ($block): string {
            return $this->getVariablePath($block->getVariable($variableName));
        }, $variableNames);
    }

    /**
     * @param string[] $variableNames
     */
    public function filesExistAtVariablePaths(Block $block, array $variableNames): bool
    {
        foreach ($this->getVariablePaths($block, $variableNames) as $path) {
            if (!$this->fileSystem->exists($path)) {
                return false;
            }
        }

        return true;
    }

    private function getDefinedPath(Variable $variable): string
    {
        return $this->getDefinedVariablesHash()[$variable->getName()] ?? '';
    }

    /**
     * The .env.dist value as Dotenv will read it in .env, as written when Dotenv cannot read it. A variable it
     * refers to has its .env value or, when .env does not set it and no extension skips it, its .env.dist default
     * before it, resolved the same way. When .env already has the variable, the value is written over its line and
     * gets only the .env values above that line.
     */
    public function resolveDistValue(Variable $variable) : string
    {
        $value = (string) $variable->getValue();
        if (false === strpos($value, '$')) {
            return (string) ($variable->getDotenvValue() ?? $value);
        }

        // Forgets the resolved values when .env has changed
        $this->getDefinedVariablesHash();

        $key = $variable->getName() . '=' . $value;
        if (!array_key_exists($key, $this->resolvedDistValues)) {
            $this->resolvedDistValues[$key] = $this->parseDistValue($variable);
        }

        return $this->resolvedDistValues[$key];
    }

    private function parseDistValue(Variable $variable) : string
    {
        $value = (string) $variable->getValue();
        $references = $this->getDefinedVariablesHash();
        if (array_key_exists($variable->getName(), $references)) {
            // Dotenv reads only the lines above, and the .env.dist defaults are appended below
            $references = array_slice(
                $references,
                0,
                (int) array_search($variable->getName(), array_keys($references), true),
                true
            );
        } else {
            foreach ($this->reference->getBlocks() as $block) {
                foreach ($block->getVariables() as $distVariable) {
                    if ($distVariable->getName() === $variable->getName()) {
                        break 2;
                    }

                    if (
                        !array_key_exists($distVariable->getName(), $references)
                        && !isset($this->skippedVariableNames[$distVariable->getName()])
                    ) {
                        $references[$distVariable->getName()] = $distVariable;
                    }
                }
            }
        }

        if (false === strpos($value, '$(')) {
            // Dotenv shows the variables it reads in the value by reading stand-ins, which run nothing
            $standIns = [];
            $nonce = bin2hex(random_bytes(4));
            foreach (array_keys($references) as $index => $name) {
                $standIns[$name] = sprintf('companienv-%s-%d-', $nonce, $index);
            }
            $readValue = (string) $variable->getDotenvValue($this->getDotenvLines($standIns));
            foreach ($standIns as $name => $standIn) {
                if (false === strpos($readValue, $standIn)) {
                    unset($references[$name]);
                }
            }
        } else {
            // A command gets every .env value, which runs nothing, and the .env.dist defaults written as $NAME in it
            preg_match_all('/\$\{?(_*[A-Za-z][A-Za-z0-9_]*)/', $value, $matches);
            foreach ($references as $name => $reference) {
                if ($reference instanceof Variable && !in_array($name, $matches[1], true)) {
                    unset($references[$name]);
                }
            }
        }

        foreach ($references as $name => $reference) {
            if ($reference instanceof Variable) {
                $references[$name] = $this->resolveDistValue($reference);
            }
        }

        return (string) ($variable->getDotenvValue($this->getDotenvLines($references)) ?? $value);
    }

    /**
     * @param array<string, string> $values
     */
    private function getDotenvLines(array $values) : string
    {
        $formatter = new ValueFormatter();
        $lines = '';
        foreach ($values as $name => $value) {
            $lines .= $name . '=' . $formatter->formatValue((string) $value, true) . "\n";
        }

        return $lines;
    }

    public function askConfirmation(string $question) : bool
    {
        return $this->interaction->askConfirmation($question);
    }

    public function ask(string $question, ?string $default = null) : string
    {
        return $this->interaction->ask($question, $default);
    }

    public function getFileSystem(): FileSystem
    {
        return $this->fileSystem;
    }
}
