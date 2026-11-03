<?php

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use RuntimeException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

class SslCertificate implements Extension
{
    private $populatedVariables = [];

    /**
     * {@inheritdoc}
     */
    public function getVariableValue(Companion $companion, Block $block, Variable $variable)
    {
        if (null === ($attribute = $block->getAttribute('ssl-certificate', $variable))) {
            return null;
        }

        if (array_key_exists($variable->getName(), $this->populatedVariables)) {
            return $this->populatedVariables[$variable->getName()];
        }

        $pathNames = array_slice($attribute->getVariableNames(), 0, 2);

        // An existing key and certificate are kept, and declined ones not generated, for the whole run: their
        // variables are asked like any other, so that a path typed for one file does not make them look missing
        if (
            $companion->filesExistAtVariablePaths($block, $pathNames)
            || !$companion->askConfirmation(sprintf(
                'Variables %s represents an SSL certificate. Do you want to automatically generate them? (y) ',
                implode(' and ', array_map(function (string $variable): string {
                    return '<comment>' . $variable . '</comment>';
                }, $attribute->getVariableNames()))
            ))
        ) {
            foreach ($attribute->getVariableNames() as $variableName) {
                $this->populatedVariables[$variableName] = null;
            }

            return null;
        }

        $domainName = $companion->ask('Enter the domain name for which to generate the self-signed SSL certificate: ');
        [$privateKeyPath, $certificatePath] = $companion->getVariablePaths($block, $pathNames);

        try {
            (new Process([
                'openssl', 'req', '-x509', '-nodes', '-days', '3650', '-newkey', 'rsa:2048',
                '-keyout', $companion->getFileSystem()->realpath($privateKeyPath),
                '-out', $companion->getFileSystem()->realpath($certificatePath),
                '-subj', '/C=SS/ST=SS/L=SelfSignedCity/O=SelfSignedOrg/CN='.addcslashes($domainName, '\\/+'),
            ]))->mustRun();
        } catch (ProcessRuntimeException $exception) {
            throw new RuntimeException(
                'Could not have generated the SSL certificate: '.$exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        foreach ($pathNames as $pathName) {
            $this->populatedVariables[$pathName] = $companion->getVariablePathForEnvFile(
                $block->getVariable($pathName)
            );
        }
        $domainVariableName = $attribute->getVariableNames()[2];
        $this->populatedVariables[$domainVariableName] = $domainName;
        $companion->markValueAsLiteral($domainVariableName);

        return $this->populatedVariables[$variable->getName()];
    }

    /**
     * {@inheritdoc}
     */
    public function isVariableRequiringValue(
        Companion $companion,
        Block $block,
        Variable $variable,
        ?string $currentValue = null
    ) : int {
        if (null === ($attribute = $block->getAttribute('ssl-certificate', $variable))) {
            return Extension::ABSTAIN;
        }

        return $companion->filesExistAtVariablePaths($block, array_slice($attribute->getVariableNames(), 0, 2))
            ? Extension::ABSTAIN
            : Extension::VARIABLE_REQUIRED;
    }
}
