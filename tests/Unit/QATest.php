<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpConfig\Config;
use PhpConfig\ConfigLoader;
use PhpConfig\Exception\ValidationException;
use PhpConfig\Format;
use PhpConfig\Reader\JsonReader;
use PhpConfig\Reader\PhpReader;
use PhpConfig\Reader\XmlReader;
use PhpConfig\Reader\YamlReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\AllConstraintsConfig;
use Tests\Fixtures\DatabaseConfig;
use Tests\Fixtures\DeepNestedParent;
use Tests\Fixtures\NoAttributesConfig;
use Tests\Fixtures\ReadonlyConfig;

final class QATest extends TestCase
{
    private string $fixturesDir;
    private ConfigLoader $loader;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../Fixtures';
        $this->loader = new ConfigLoader([
            new YamlReader(),
            new JsonReader(),
            new XmlReader(),
            new PhpReader(),
        ]);
    }

    public function testMissingFile(): void
    {
        $result = Config::load('/nonexistent/path.yaml', DatabaseConfig::class);

        $this->assertTrue($result->isErr());
    }

    public function testUnsupportedExtension(): void
    {
        $result = Config::load('config.toml', DatabaseConfig::class);

        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('Unsupported config format', $result->exception()->getMessage());
    }

    public function testEmptyYaml(): void
    {
        $path = $this->fixturesDir . '/empty.yaml';
        file_put_contents($path, '');

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());
        $this->assertStringContainsString('YAML root must be a mapping', $result->exception()->getMessage());

        unlink($path);
    }

    public function testNullValues(): void
    {
        $yaml = "host: null\nport: 3306\npassword: secret\nlog:\n  level: debug\n";
        $path = $this->fixturesDir . '/null_host.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        $errors = $result->exception()->getErrors();
        $hasHostError = false;
        foreach ($errors as $error) {
            if ($error->path === 'host') {
                $hasHostError = true;
                break;
            }
        }
        $this->assertTrue($hasHostError);

        unlink($path);
    }

    public function testExtraFieldsIgnored(): void
    {
        $yaml = "host: localhost\nport: 3306\npassword: secret\nlog:\n  level: debug\nextraField: ignored\n";
        $path = $this->fixturesDir . '/extra_fields.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertSame('localhost', $result->collect()->host);

        unlink($path);
    }

    public function testAllValidationErrorsAggregated(): void
    {
        $yaml = "name: 'UPPERCASE'\ncount: 200\nlabel: 'ab'\n";
        $path = $this->fixturesDir . '/multi_error.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, AllConstraintsConfig::class);

        $this->assertTrue($result->isErr());

        $errors = $result->exception()->getErrors();
        $this->assertGreaterThanOrEqual(2, $errors);

        $paths = array_map(fn($e) => $e->path, $errors);
        $this->assertContains('name', $paths);
        $this->assertContains('count', $paths);

        unlink($path);
    }

    public function testNoAttributesOnDto(): void
    {
        $yaml = "name: hello\ncount: 42\n";
        $path = $this->fixturesDir . '/no_attrs.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, NoAttributesConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertSame('hello', $result->collect()->name);
        $this->assertSame(42, $result->collect()->count);

        unlink($path);
    }

    public function testReadonlyProperties(): void
    {
        $yaml = "name: test\n";
        $path = $this->fixturesDir . '/readonly.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, ReadonlyConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertSame('test', $result->collect()->name);

        unlink($path);
    }

    public function testEmptyNestedObject(): void
    {
        $yaml = "host: localhost\nport: 3306\npassword: secret\nlog: {}\n";
        $path = $this->fixturesDir . '/empty_nested.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        $errors = $result->exception()->getErrors();
        $hasLevelError = false;
        foreach ($errors as $error) {
            if ($error->path === 'log.level') {
                $hasLevelError = true;
                break;
            }
        }
        $this->assertTrue($hasLevelError);

        unlink($path);
    }

    public function testNestedIsNull(): void
    {
        $yaml = "host: localhost\nport: 3306\npassword: secret\nlog: null\n";
        $path = $this->fixturesDir . '/null_nested.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        unlink($path);
    }

    public function testDeepNesting(): void
    {
        $yaml = "child:\n  name: deep\n  grandchild:\n    value: 42\n";
        $path = $this->fixturesDir . '/deep_nested.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DeepNestedParent::class);

        $this->assertTrue($result->isOk());
        $this->assertSame('deep', $result->collect()->child->name);
        $this->assertSame(42, $result->collect()->child->grandchild->value);

        unlink($path);
    }

    public function testDeepNestingValidationError(): void
    {
        $yaml = "child:\n  name: deep\n  grandchild:\n    value: 9999\n";
        $path = $this->fixturesDir . '/deep_nested_bad.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DeepNestedParent::class);

        $this->assertTrue($result->isErr());

        $errors = $result->exception()->getErrors();
        $hasValueError = false;
        foreach ($errors as $error) {
            if ($error->path === 'child.grandchild.value') {
                $hasValueError = true;
                break;
            }
        }
        $this->assertTrue($hasValueError);

        unlink($path);
    }

    public function testNonObjectJson(): void
    {
        $path = $this->fixturesDir . '/non_object.json';
        file_put_contents($path, '[1, 2, 3]');

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        unlink($path);
    }

    public function testMalformedYaml(): void
    {
        $path = $this->fixturesDir . '/malformed.yaml';
        file_put_contents($path, "<<<<< malformed >>>>\n: : broken");

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        unlink($path);
    }

    public function testStringInsteadOfInt(): void
    {
        $yaml = "host: localhost\nport: \"not-a-number\"\npassword: secret\nlog:\n  level: debug\n";
        $path = $this->fixturesDir . '/type_mismatch.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        $errors = $result->exception()->getErrors();
        $hasTypeError = false;
        foreach ($errors as $error) {
            if ($error->path === 'port' && str_contains($error->message, 'Expected int')) {
                $hasTypeError = true;
                break;
            }
        }
        $this->assertTrue($hasTypeError);

        unlink($path);
    }

    public function testXmlWithoutTypeHint(): void
    {
        $xml = <<<XML
        <?xml version="1.0"?>
        <config>
            <host>localhost</host>
            <port>3306</port>
            <password>secret</password>
            <log>
                <level>debug</level>
            </log>
        </config>
        XML;
        $path = $this->fixturesDir . '/xml_no_type.xml';
        file_put_contents($path, $xml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertSame(3306, $result->collect()->port);

        unlink($path);
    }

    public function testLoadRawNonExistentFile(): void
    {
        $result = Config::loadRaw('/nonexistent/raw.yaml');

        $this->assertTrue($result->isErr());
    }

    public function testLoadFromStringInvalidJson(): void
    {
        $result = Config::loadFromString('{invalid json}', Format::JSON, DatabaseConfig::class);

        $this->assertTrue($result->isErr());
    }

    public function testLoadFromStringNonObjectJson(): void
    {
        $result = Config::loadFromString('"just a string"', Format::JSON, DatabaseConfig::class);

        $this->assertTrue($result->isErr());
    }

    public function testValidationExceptionMessage(): void
    {
        $yaml = "host: localhost\nport: 99999\npassword: secret\nlog:\n  level: debug\n";
        $path = $this->fixturesDir . '/validate_message.yaml';
        file_put_contents($path, $yaml);

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        $exception = $result->exception();
        $message = $exception->getMessage();
        $this->assertStringContainsString('port', $message);

        unlink($path);
    }

    public function testPhpConfigSuccess(): void
    {
        $path = $this->fixturesDir . '/valid_config.php';

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isOk());
        $this->assertSame('localhost', $result->collect()->host);
    }

    public function testPhpConfigNonArray(): void
    {
        $path = $this->fixturesDir . '/bad_php.php';
        file_put_contents($path, '<?php return "not an array";');

        $result = Config::load($path, DatabaseConfig::class);

        $this->assertTrue($result->isErr());

        unlink($path);
    }

    public function testFormatDetectionOrder(): void
    {
        $result = Config::load($this->fixturesDir . '/valid_config.yaml', DatabaseConfig::class);

        $this->assertTrue($result->isOk());
    }
}
