<?php

declare(strict_types=1);

namespace Companienv\DotEnv;

use PHPUnit\Framework\TestCase;

final class FileTest extends TestCase
{
    /**
     * @dataProvider blocksDataProvider
     *
     * @param list<Variable> $expected
     */
    public function testGetAllVariables(File $file, array $expected): void
    {
        $this->assertSame($expected, $file->getAllVariables());
    }

    /**
     * @return array<string, array{0: File, 1: list<Variable>}>
     */
    public static function blocksDataProvider(): array
    {
        $first = new Variable('FIRST', '1');
        $second = new Variable('SECOND', '2');
        $third = new Variable('THIRD', '3');

        return [
            'no blocks' => [new File(), []],
            'variables of every block in order' => [
                new File('', [
                    new Block('One', '', [$first, $second]),
                    new Block('Empty'),
                    new Block('Two', '', [$third]),
                ]),
                [$first, $second, $third],
            ],
        ];
    }
}
