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
    /**
     * @var string|false|null
     */
    private $envContents;
    /**
     * @var array<string, string>
     */
    private $definedVariables;
    /**
     * @var array<string, string>
     */
    private $resolvedDistValues;
    /**
     * @var array<string, array<string, string>>
     */
    private $resolvedEnvValues;
    /**
     * @var array<string, string>
     */
    private $generatedValues;
    /**
     * @var array<string, true>
     */
    private $skippedVariableNames;
    /**
     * @var array<string, true>
     */
    private $literalVariableNames;

    public function __construct(FileSystem $fileSystem, Interaction $interaction, Extension $extension, string $envFileName = '.env', string $distFileName = '.env.dist')
    {
        $this->fileSystem = $fileSystem;
        $this->interaction = $interaction;
        $this->extension = $extension;
        $this->reference = (new Parser())->parse($fileSystem, $distFileName);
        $this->envFileName = $envFileName;
        $this->envContents = false;
        $this->definedVariables = [];
        $this->resolvedDistValues = [];
        $this->resolvedEnvValues = [];
        $this->generatedValues = [];
        $this->skippedVariableNames = [];
        $this->literalVariableNames = [];
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
                $this->writeGeneratedValues($block);
            }
        }
    }

    /**
     * @param array<string, string> $values
     */
    public function recordGeneratedValues(array $values): void
    {
        $this->generatedValues = array_replace($this->generatedValues, $values);
    }

    private function writeGeneratedValues(Block $block): void
    {
        foreach ($block->getVariables() as $variable) {
            $name = $variable->getName();
            if (array_key_exists($name, $this->generatedValues)) {
                $this->writeVariable($variable, $this->generatedValues[$name]);
                unset($this->generatedValues[$name]);
            }
        }
    }

    private function writeVariable(Variable $variable, string $value): void
    {
        $name = $variable->getName();

        if (!$this->fileSystem->exists($this->envFileName)) {
            $this->fileSystem->write($this->envFileName, '');
        }

        $variablesInFileHash = $this->getDefinedVariablesHash();
        if (($variablesInFileHash[$name] ?? null) === $value) {
            return;
        }

        $referenceValue = isset($this->literalVariableNames[$name]) ? null : $variable->getValue();
        $writer = new DotenvWriter(new ValueFormatter($referenceValue));
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
            $this->resolvedEnvValues = [];
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
            : null
        ;

        if ($envContents !== $this->envContents) {
            $this->definedVariables = $envContents === null
                ? []
                : @(new Dotenv())->parse($envContents, $this->envFileName)
            ;
            $this->resolvedDistValues = [];
            $this->resolvedEnvValues = [];
            $this->envContents = $envContents;
        }

        return $this->definedVariables;
    }

    public function getVariablePath(Variable $variable): string
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
     * @param array<string, string> $generatedValues
     *
     * @return string[]
     */
    public function getVariablePaths(Block $block, array $variableNames, array $generatedValues = []): array
    {
        $pathDefaults = [];
        foreach ($variableNames as $variableName) {
            $pathDefaults[$variableName] = $block->getVariable($variableName);
        }

        $replacementValues = $generatedValues + $pathDefaults;

        return array_map(function (string $variableName) use ($pathDefaults, $replacementValues): string {
            $variable = $pathDefaults[$variableName];
            $path = $this->getDefinedPath($variable);

            return $path !== '' ? $path : $this->resolveDistValueWithReplacements($variable, $replacementValues);
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

    public function markValueAsLiteral(string $variableName): void
    {
        $this->literalVariableNames[$variableName] = true;
    }

    private function getDefinedPath(Variable $variable): string
    {
        return $this->getDefinedVariablesHash()[$variable->getName()] ?? '';
    }

    public function resolveDistValue(Variable $variable): string
    {
        return $this->resolveDistValueWithReplacements($variable, []);
    }

    /**
     * @param array<string, string|Variable> $replacementValues
     */
    private function resolveDistValueWithReplacements(Variable $variable, array $replacementValues): string
    {
        $definedVariables = $this->getDefinedVariablesHash();
        if (
            isset($replacementValues[$variable->getName()])
            && ($definedVariables[$variable->getName()] ?? null) === ''
        ) {
            return $this->getDefinedVariablesWithReplacements($replacementValues)[$variable->getName()];
        }

        $value = (string) $variable->getValue();
        if (strpos($value, '$') === false) {
            return (string) ($variable->getDotenvValue() ?? $value);
        }

        return $this->parseDistValue($variable, $replacementValues);
    }

    /**
     * @param array<string, string|Variable> $replacementValues
     */
    private function parseDistValue(Variable $variable, array $replacementValues): string
    {
        $value = (string) $variable->getValue();
        $references = $this->getPrecedingReferences($variable, $replacementValues);
        foreach ($references as $name => $reference) {
            if (
                isset($replacementValues[$name])
                && ($reference === '' || is_string($replacementValues[$name]))
            ) {
                $references[$name] = $replacementValues[$name];
            }
        }
        $selectedReferences = $this->selectReferences($variable, $references);
        foreach ($selectedReferences as $name => $reference) {
            if ($reference instanceof Variable) {
                $selectedReferences[$name] = $this->resolveDistValueWithReplacements($reference, $replacementValues);
            }
        }

        $precedingLines = $this->getDotenvLines($selectedReferences);
        $key = serialize([$variable->getName(), $value, $precedingLines]);
        if (!array_key_exists($key, $this->resolvedDistValues)) {
            $this->resolvedDistValues[$key] = (string) ($variable->getDotenvValue($precedingLines) ?? $value);
        }

        return $this->resolvedDistValues[$key];
    }

    /**
     * @param array<string, string|Variable> $replacementValues
     *
     * @return array<string, string>
     */
    private function getDefinedVariablesWithReplacements(array $replacementValues): array
    {
        $references = $this->getDefinedVariablesHash();
        $replacements = [];
        foreach ($replacementValues as $name => $replacement) {
            if (!array_key_exists($name, $references)) {
                continue;
            }
            if ($replacement instanceof Variable) {
                if ($references[$name] === '' && $replacement->getValue() !== '') {
                    $replacements[$name] = $replacement;
                }
            } elseif ($replacement !== $references[$name]) {
                $replacements[$name] = $replacement;
            }
        }
        if (count($replacements) === 0) {
            return $references;
        }

        $key = serialize($replacements);
        if (!array_key_exists($key, $this->resolvedEnvValues)) {
            $buffer = (string) $this->envContents;
            foreach ($replacements as $name => $replacement) {
                $referenceValue = $replacement instanceof Variable ? (string) $replacement->getValue() : null;
                $value = $referenceValue ?? $replacement;
                $writer = new DotenvWriter(new ValueFormatter($referenceValue));
                $writer->setBuffer($buffer);
                $writer->updateSetter($name, $value, '');
                $buffer = $writer->getBuffer();
            }
            $this->resolvedEnvValues[$key] = @(new Dotenv())->parse($buffer, $this->envFileName);
        }

        return $this->resolvedEnvValues[$key];
    }

    /**
     * @param array<string, string|Variable> $replacementValues
     *
     * @return array<string, string|Variable>
     */
    private function getPrecedingReferences(Variable $variable, array $replacementValues): array
    {
        $references = $this->getDefinedVariablesWithReplacements($replacementValues);
        if (array_key_exists($variable->getName(), $references)) {
            return array_slice(
                $references,
                0,
                (int) array_search($variable->getName(), array_keys($references), true),
                true
            );
        }

        foreach ($this->reference->getBlocks() as $block) {
            foreach ($block->getVariables() as $distVariable) {
                if ($distVariable->getName() === $variable->getName()) {
                    return $references;
                }

                if (
                    !array_key_exists($distVariable->getName(), $references)
                    && !isset($this->skippedVariableNames[$distVariable->getName()])
                ) {
                    $references[$distVariable->getName()] = $distVariable;
                }
            }
        }

        return $references;
    }

    /**
     * @param array<string, string|Variable> $references
     *
     * @return array<string, string|Variable>
     */
    private function selectReferences(Variable $variable, array $references): array
    {
        $expandableValue = $this->getExpandableValue((string) $variable->getValue());
        if (strpos($expandableValue, '$(') !== false) {
            preg_match_all('/\$\{?(_*[A-Za-z][A-Za-z0-9_]*)/', $expandableValue, $matches);
            foreach ($references as $name => $reference) {
                if ($reference instanceof Variable && !in_array($name, $matches[1], true)) {
                    unset($references[$name]);
                }
            }

            return $references;
        }

        $standIns = [];
        $nonce = bin2hex(random_bytes(4));
        foreach (array_keys($references) as $index => $name) {
            $standIns[$name] = sprintf('companienv-%s-%d-', $nonce, $index);
        }
        $readValue = (string) $variable->getDotenvValue($this->getDotenvLines($standIns));
        foreach ($standIns as $name => $standIn) {
            if (strpos($readValue, $standIn) === false) {
                unset($references[$name]);
            }
        }

        return $references;
    }

    private function getExpandableValue(string $value): string
    {
        $expandableValue = '';
        $doubleQuoted = false;
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            if ($character === '\\') {
                $index++;
                continue;
            }
            if ($character === '"') {
                $doubleQuoted = !$doubleQuoted;
                continue;
            }
            if (!$doubleQuoted && $character === "'") {
                $closingQuote = strpos($value, "'", $index + 1);
                $index = $closingQuote !== false ? $closingQuote : $length;
                continue;
            }
            if (!$doubleQuoted && $character === '#' && ($index === 0 || ctype_space($value[$index - 1]))) {
                break;
            }
            if (
                $character === '$'
                && preg_match('/\$(?<command>\((?:[^()]|(?&command))*\))/A', $value, $command, 0, $index) === 1
            ) {
                $expandableValue .= $command[0];
                $index += strlen($command[0]) - 1;
                continue;
            }
            $expandableValue .= $character;
        }

        return $expandableValue;
    }

    /**
     * @param array<string, string> $values
     */
    private function getDotenvLines(array $values): string
    {
        $formatter = new ValueFormatter();
        $lines = '';
        foreach ($values as $name => $value) {
            $lines .= $name . '=' . $formatter->formatValue((string) $value, true) . "\n";
        }

        return $lines;
    }

    public function askConfirmation(string $question): bool
    {
        return $this->interaction->askConfirmation($question);
    }

    public function ask(string $question, ?string $default = null): string
    {
        return $this->interaction->ask($question, $default);
    }

    public function getFileSystem(): FileSystem
    {
        return $this->fileSystem;
    }
}
