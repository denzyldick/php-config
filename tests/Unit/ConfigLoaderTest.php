<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpConfig\Config;
use PhpConfig\Exception\ValidationException;
use PhpConfig\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DatabaseConfig;

final class ConfigLoaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../Fixtures';
    }

    #[DataProvider('validConfigProvider')]
    public function testLoadValidConfig(string $filename): void
    {
        $path = $this->fixturesDir . '/' . $filename;

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isOk(), 'Expected Ok result for ' . $filename);
        $this->assertInstanceOf(DatabaseConfig::class, $result->collect());

        $config = $result->collect();
        $this->assertSame('localhost', $config->host);
        $this->assertSame(3306, $config->port);
        $this->assertSame('root', $config->username);
        $this->assertSame('secret', $config->password);
        $this->assertFalse($config->ssl);
        $this->assertSame('debug', $config->log->level);
        $this->assertSame('logs/app.log', $config->log->path);
    }

    /**
     * @return list<array{string}>
     */
    public static function validConfigProvider(): array
    {
        return [
            ['valid_config.yaml'],
            ['valid_config.json'],
            ['valid_config.xml'],
            ['valid_config.php'],
        ];
    }

    public function testLoadInvalidConfigReturnsErrors(): void
    {
        $path = $this->fixturesDir . '/invalid_config.yaml';

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr(), 'Expected Error result for invalid config');

        $exception = $result->exception();
        $this->assertInstanceOf(ValidationException::class, $exception);

        $errors = $exception->getErrors();
        $this->assertGreaterThanOrEqual(1, $errors);

        $foundPortError = false;
        foreach ($errors as $error) {
            if ($error->path === 'port' && str_contains($error->message, 'Must be between')) {
                $foundPortError = true;
                break;
            }
        }
        $this->assertTrue($foundPortError, 'Expected a range error on port field');
    }

    public function testLoadFromString(): void
    {
        $json = '{"host": "localhost", "port": 5432, "password": "p4ss"}';

        $result = Config::loadFromString($json, Format::JSON, DatabaseConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertInstanceOf(DatabaseConfig::class, $result->collect());

        $config = $result->collect();
        $this->assertSame('localhost', $config->host);
        $this->assertSame(5432, $config->port);
    }

    public function testLoadRawReturnsArray(): void
    {
        $path = $this->fixturesDir . '/valid_config.yaml';

        $result = Config::loadRaw($path);

        $this->assertTrue($result->isOk());
        $this->assertIsArray($result->collect());
    }

    public function testAutocompleteWorks(): void
    {
        $result = Config::load($this->fixturesDir . '/valid_config.yaml', DatabaseConfig::class);

        $this->assertTrue($result->isOk());

        /** @var DatabaseConfig $config */
        $config = $result->collect();

        $this->assertIsString($config->host);
        $this->assertIsInt($config->port);
        $this->assertIsString($config->username);
        $this->assertIsString($config->password);
        $this->assertIsBool($config->ssl);
        $this->assertIsString($config->log->level);
        $this->assertIsString($config->log->path);
    }

    public function testMatchHappyPath(): void
    {
        $result = Config::load($this->fixturesDir . '/valid_config.yaml', DatabaseConfig::class);

        $matched = false;
        $result->match(
            function (DatabaseConfig $config) use (&$matched) {
                $matched = true;
                $this->assertSame('localhost', $config->host);
            },
            fn(ValidationException $e) => $this->fail('Should not have errors: ' . $e->getMessage()),
        );

        $this->assertTrue($matched);
    }
}
