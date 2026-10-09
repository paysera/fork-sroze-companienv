<?php
namespace Companienv\DotEnv;

use Jackiedo\DotenvEditor\DotenvFormatter;

class ValueFormatter extends DotenvFormatter
{
    public function formatValue($value, $forceQuotes = false)
    {
        if (!$forceQuotes && !$this->requiresQuotes($value)) {
            return $value;
        }

        if (false === strpos($value, "'")) {
            return "'{$value}'";
        }

        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\"', $value);
        $value = "\"{$value}\"";
        return $value;
    }

    private function requiresQuotes(string $value): bool
    {
        return preg_match('/[\s#"\'\\\\]/', $value)
            && !preg_match('/^(?:"(?:[^"\\\\]|\\\\.)*"|\'[^\']*\')$/s', $value);
    }
}
