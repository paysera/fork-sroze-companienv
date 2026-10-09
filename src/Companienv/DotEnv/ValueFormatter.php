<?php
namespace Companienv\DotEnv;

use Jackiedo\DotenvEditor\DotenvFormatter;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;

class ValueFormatter extends DotenvFormatter
{
    private $referenceValue;

    public function __construct(?string $referenceValue = null)
    {
        $this->referenceValue = $referenceValue;
    }

    public function formatValue($value, $forceQuotes = false)
    {
        if (!$forceQuotes && ($this->isReadableReferenceValue($value) || !$this->requiresQuotes($value))) {
            return $value;
        }

        if (false === strpos($value, "'")) {
            return "'{$value}'";
        }

        if (!preg_match('/[\\\\"$]/', $value)) {
            return "\"{$value}\"";
        }

        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function isReadableReferenceValue(string $value): bool
    {
        if ($value !== $this->referenceValue) {
            return false;
        }

        try {
            @(new Dotenv())->parse('VALUE=' . $value . "\n");
        } catch (FormatException $exception) {
            return false;
        }

        return true;
    }

    private function requiresQuotes(string $value): bool
    {
        return preg_match('/[\s#"\'\\\\$]/', $value)
            && !preg_match('/^(?:"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\')$/s', $value);
    }
}
