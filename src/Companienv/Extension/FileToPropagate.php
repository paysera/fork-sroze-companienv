<?php

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use InvalidArgumentException;
use RuntimeException;

class FileToPropagate implements Extension
{
    /**
     * {@inheritdoc}
     */
    public function getVariableValue(Companion $companion, Block $block, Variable $variable)
    {
        if (null === ($attribute = $block->getAttribute('file-to-propagate', $variable))) {
            return null;
        }

        $fileSystem = $companion->getFileSystem();
        $filename = $companion->getVariablePath($variable);
        if ($filename === '') {
            return null;
        }

        $value = $companion->getVariablePathForEnvFile($variable);
        if ($fileSystem->exists($filename)) {
            return $value;
        }

        $downloadedFilePath = $companion->ask(
            '<comment>' . $variable->getName() . '</comment>: '
                . 'What is the path of your downloaded file? (leave empty to skip) ',
            ''
        );
        if ($downloadedFilePath === '') {
            return $value;
        }

        if (!$fileSystem->exists($downloadedFilePath, false)) {
            throw new InvalidArgumentException(sprintf('The file "%s" does not exist', $downloadedFilePath));
        }

        if ($fileSystem->write($filename, $fileSystem->getContents($downloadedFilePath, false)) === false) {
            throw new RuntimeException(sprintf(
                'Unable to write into "%s"',
                $filename
            ));
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function isVariableRequiringValue(
        Companion $companion,
        Block $block,
        Variable $variable,
        ?string $currentValue = null
    ): int {
        if (null === ($attribute = $block->getAttribute('file-to-propagate', $variable))) {
            return Extension::ABSTAIN;
        }

        $filename = $companion->getVariablePath($variable);

        return $filename === '' || $companion->getFileSystem()->exists($filename)
            ? Extension::ABSTAIN
            : Extension::VARIABLE_REQUIRED
        ;
    }
}
