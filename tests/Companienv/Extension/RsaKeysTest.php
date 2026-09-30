<?php

declare(strict_types=1);

namespace Companienv\Extension;

use Companienv\Companion;
use Companienv\DotEnv\Attribute;
use Companienv\DotEnv\Block;
use Companienv\DotEnv\Variable;
use Companienv\Extension;
use Companienv\IO\FileSystem\NativePhpFileSystem;
use Companienv\IO\InMemoryInteraction;
use Companienv\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class RsaKeysTest extends TestCase
{
    use TemporaryDirectory;

    private const CONFIRMATION = 'Variables KEY_PATH and PUB_PATH and KEY_PASS represents an RSA public/private key. '
        . 'Do you want to automatically generate them? (y)';
    private const PASS_PHRASE_QUESTION = 'Enter pass phrase to protect the keys:';

    protected function setUp(): void
    {
        file_put_contents($this->createTemporaryDirectory() . '/.env.dist', '');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    /**
     * @dataProvider nothingGeneratedDataProvider
     */
    public function testNothingGenerated(Block $block, array $answers, string $expectedQuestions)
    {
        $interaction = new InMemoryInteraction($answers);

        $value = (new RsaKeys())->getVariableValue($this->companion($interaction), $block, new Variable('KEY_PATH', 'private.pem'));

        $this->assertSame(
            ['value' => null, 'questions' => $expectedQuestions, 'files' => ['.env.dist']],
            ['value' => $value, 'questions' => $interaction->getBuffer(), 'files' => array_keys($this->readTemporaryDirectory())]
        );
    }

    public static function nothingGeneratedDataProvider(): array
    {
        return [
            'no rsa-pair attribute' => [new Block('Keys', '', self::variables()), [], ''],
            'generation declined' => [self::block(), [self::CONFIRMATION => ''], self::CONFIRMATION . "\n"],
        ];
    }

    /**
     * @dataProvider passPhraseDataProvider
     */
    public function testGeneratedKeyPair(string $passPhrase)
    {
        $interaction = new InMemoryInteraction([self::CONFIRMATION => 'y', self::PASS_PHRASE_QUESTION => $passPhrase]);
        $companion = $this->companion($interaction);
        $extension = new RsaKeys();

        $values = [];
        foreach (self::variables() as $variable) {
            $values[$variable->getName()] = $extension->getVariableValue($companion, self::block(), $variable);
        }

        $privateKey = openssl_pkey_get_private(file_get_contents($this->temporaryDirectory . '/private.pem'), $passPhrase);
        $this->assertSame(
            [
                'values' => ['KEY_PATH' => 'private.pem', 'PUB_PATH' => 'public.pem', 'KEY_PASS' => $passPhrase],
                'questions' => self::CONFIRMATION . "\n" . self::PASS_PHRASE_QUESTION . "\n",
                'files' => ['.env.dist', 'private.pem', 'public.pem'],
                'public key' => openssl_pkey_get_details($privateKey)['key'],
            ],
            [
                'values' => $values,
                'questions' => $interaction->getBuffer(),
                'files' => array_keys($this->readTemporaryDirectory()),
                'public key' => file_get_contents($this->temporaryDirectory . '/public.pem'),
            ]
        );
    }

    public static function passPhraseDataProvider(): array
    {
        return [
            'plain pass phrase' => ['secret-phrase'],
            'pass phrase with spaces and quotes' => ['it\'s my "secret" phrase'],
        ];
    }

    /**
     * @dataProvider existingFilesDataProvider
     */
    public function testIsVariableRequiringValue(Block $block, array $existingFiles, int $expected)
    {
        foreach ($existingFiles as $name) {
            touch($this->temporaryDirectory . '/' . $name);
        }

        $this->assertSame(
            $expected,
            (new RsaKeys())->isVariableRequiringValue($this->companion(new InMemoryInteraction()), $block, new Variable('KEY_PATH', 'private.pem'))
        );
    }

    public static function existingFilesDataProvider(): array
    {
        return [
            'no rsa-pair attribute' => [new Block('Keys', '', self::variables()), [], Extension::ABSTAIN],
            'no key file' => [self::block(), [], Extension::VARIABLE_REQUIRED],
            'only the private key' => [self::block(), ['private.pem'], Extension::VARIABLE_REQUIRED],
            'both key files' => [self::block(), ['private.pem', 'public.pem'], Extension::ABSTAIN],
        ];
    }

    private static function variables(): array
    {
        return [new Variable('KEY_PATH', 'private.pem'), new Variable('PUB_PATH', 'public.pem'), new Variable('KEY_PASS', '')];
    }

    private static function block(): Block
    {
        return new Block('Keys', '', self::variables(), [new Attribute('rsa-pair', ['KEY_PATH', 'PUB_PATH', 'KEY_PASS'], [])]);
    }

    private function companion(InMemoryInteraction $interaction): Companion
    {
        return new Companion(new NativePhpFileSystem($this->temporaryDirectory), $interaction, new Chained());
    }
}
