<?php

namespace Companienv\DotEnv;

use Jackiedo\DotenvEditor\DotenvFormatter;

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

        if (strpos($value, "'") === false) {
            return "'{$value}'";
        }

        if (preg_match('/[\\\\"$]/', $value) === 0) {
            return "\"{$value}\"";
        }

        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function isReadableReferenceValue(string $value): bool
    {
        return $value === $this->referenceValue
            && (strpos($value, '$(') !== false || (new Variable('VALUE', $value))->getDotenvValue() !== null)
        ;
    }

    private function requiresQuotes(string $value): bool
    {
        return preg_match('/[\s#"\'\\\\$]/', $value) === 1;
    }
}
