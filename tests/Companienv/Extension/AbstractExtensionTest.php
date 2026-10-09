<?php

declare(strict_types=1);

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use Companienv\IO\InMemoryFileSystem;
use Companienv\IO\InMemoryInteraction;
use PHPUnit\Framework\TestCase;

final class AbstractExtensionTest extends TestCase
{
    public function testAbstains(): void
    {
        $fileSystem = new InMemoryFileSystem();
        $fileSystem->write('.env.dist', '');
        $companion = new Companion($fileSystem, new InMemoryInteraction(), new Chained());
        $block = new Block('Block');
        $variable = new Variable('NAME', 'value');
        $extension = new AbstractExtension();

        $this->assertSame(
            ['value' => null, 'requirement' => Extension::ABSTAIN],
            [
                'value' => $extension->getVariableValue($companion, $block, $variable),
                'requirement' => $extension->isVariableRequiringValue($companion, $block, $variable, 'current'),
            ]
        );
    }
}
