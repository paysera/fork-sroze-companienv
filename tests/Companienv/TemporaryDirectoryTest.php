<?php

declare(strict_types=1);

namespace Companienv;

use PHPUnit\Framework\TestCase;

final class TemporaryDirectoryTest extends TestCase
{
    use TemporaryDirectory;

    public function testLifecycle(): void
    {
        $directory = $this->createTemporaryDirectory();
        $createdEmpty = is_dir($directory) && $this->readTemporaryDirectory() === [];
        file_put_contents($directory . '/second', '2');
        file_put_contents($directory . '/.first', '1');
        $files = $this->readTemporaryDirectory();

        $this->removeTemporaryDirectory();

        $this->assertSame(
            [
                'inside the system temporary directory' => true,
                'created empty' => true,
                'files' => ['.first' => '1', 'second' => '2'],
                'removed' => true,
            ],
            [
                'inside the system temporary directory' => dirname($directory) === sys_get_temp_dir(),
                'created empty' => $createdEmpty,
                'files' => $files,
                'removed' => !file_exists($directory),
            ]
        );
    }
}
