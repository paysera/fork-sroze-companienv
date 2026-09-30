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

final class SslCertificateTest extends TestCase
{
    use TemporaryDirectory;

    private const CONFIRMATION = 'Variables CERT_KEY_PATH and CERT_PATH and CERT_DOMAIN represents an SSL certificate. '
        . 'Do you want to automatically generate them? (y)';
    private const DOMAIN_QUESTION = 'Enter the domain name for which to generate the self-signed SSL certificate:';

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
    public function testNothingGenerated(Block $block, array $answers, array $variableNames, string $expectedQuestions)
    {
        $interaction = new InMemoryInteraction($answers);
        $companion = $this->companion($interaction);
        $extension = new SslCertificate();

        $values = [];
        foreach ($variableNames as $name) {
            $values[$name] = $extension->getVariableValue($companion, $block, $block->getVariable($name));
        }

        $this->assertSame(
            ['values' => array_fill_keys($variableNames, null), 'questions' => $expectedQuestions, 'files' => ['.env.dist']],
            ['values' => $values, 'questions' => $interaction->getBuffer(), 'files' => array_keys($this->readTemporaryDirectory())]
        );
    }

    public static function nothingGeneratedDataProvider(): array
    {
        return [
            'no ssl-certificate attribute' => [new Block('Certificate', '', self::variables()), [], ['CERT_KEY_PATH'], ''],
            'generation declined' => [self::block(), [self::CONFIRMATION => ''], ['CERT_KEY_PATH'], self::CONFIRMATION . "\n"],
            'generation declined, then the rest of the pair' => [
                self::block(),
                [self::CONFIRMATION => ''],
                ['CERT_KEY_PATH', 'CERT_PATH', 'CERT_DOMAIN'],
                self::CONFIRMATION . "\n",
            ],
        ];
    }

    /**
     * @dataProvider domainDataProvider
     */
    public function testGeneratedCertificate(string $domain)
    {
        $interaction = new InMemoryInteraction([self::CONFIRMATION => 'y', self::DOMAIN_QUESTION => $domain]);
        $companion = $this->companion($interaction);
        $extension = new SslCertificate();

        $values = [];
        foreach (self::variables() as $variable) {
            $values[$variable->getName()] = $extension->getVariableValue($companion, self::block(), $variable);
        }

        $certificate = file_get_contents($this->temporaryDirectory . '/certificate.pem');
        $this->assertSame(
            [
                'values' => ['CERT_KEY_PATH' => 'key.pem', 'CERT_PATH' => 'certificate.pem', 'CERT_DOMAIN' => $domain],
                'questions' => self::CONFIRMATION . "\n" . self::DOMAIN_QUESTION . "\n",
                'files' => ['.env.dist', 'certificate.pem', 'key.pem'],
                'common name' => $domain,
                'key matches' => true,
            ],
            [
                'values' => $values,
                'questions' => $interaction->getBuffer(),
                'files' => array_keys($this->readTemporaryDirectory()),
                'common name' => openssl_x509_parse($certificate)['subject']['CN'],
                'key matches' => openssl_x509_check_private_key($certificate, file_get_contents($this->temporaryDirectory . '/key.pem')),
            ]
        );
    }

    public static function domainDataProvider(): array
    {
        return [
            'plain domain' => ['localhost'],
            'domain with spaces and quotes' => ['my "local" site\'s name'],
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
            (new SslCertificate())->isVariableRequiringValue(
                $this->companion(new InMemoryInteraction()),
                $block,
                new Variable('CERT_KEY_PATH', 'key.pem')
            )
        );
    }

    public static function existingFilesDataProvider(): array
    {
        return [
            'no ssl-certificate attribute' => [new Block('Certificate', '', self::variables()), [], Extension::ABSTAIN],
            'no file' => [self::block(), [], Extension::VARIABLE_REQUIRED],
            'only the key' => [self::block(), ['key.pem'], Extension::VARIABLE_REQUIRED],
            'key and certificate' => [self::block(), ['key.pem', 'certificate.pem'], Extension::ABSTAIN],
        ];
    }

    private static function variables(): array
    {
        return [new Variable('CERT_KEY_PATH', 'key.pem'), new Variable('CERT_PATH', 'certificate.pem'), new Variable('CERT_DOMAIN', '')];
    }

    private static function block(): Block
    {
        return new Block('Certificate', '', self::variables(), [
            new Attribute('ssl-certificate', ['CERT_KEY_PATH', 'CERT_PATH', 'CERT_DOMAIN'], []),
        ]);
    }

    private function companion(InMemoryInteraction $interaction): Companion
    {
        return new Companion(new NativePhpFileSystem($this->temporaryDirectory), $interaction, new Chained());
    }
}
