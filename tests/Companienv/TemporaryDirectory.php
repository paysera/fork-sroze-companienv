<?php

declare(strict_types=1);

namespace Companienv;

trait TemporaryDirectory
{
    private $temporaryDirectory;

    private function createTemporaryDirectory(): string
    {
        $this->temporaryDirectory = sys_get_temp_dir() . '/companienv-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory);

        return $this->temporaryDirectory;
    }

    private function removeTemporaryDirectory()
    {
        foreach (array_keys($this->readTemporaryDirectory()) as $name) {
            unlink($this->temporaryDirectory . '/' . $name);
        }
        rmdir($this->temporaryDirectory);
    }

    private function readTemporaryDirectory(): array
    {
        $files = [];
        foreach (array_diff(scandir($this->temporaryDirectory), ['.', '..']) as $name) {
            $files[$name] = file_get_contents($this->temporaryDirectory . '/' . $name);
        }

        return $files;
    }
}
