# php-config

Schema-based config loader for PHP. Load YAML, JSON, XML, or PHP config files and validate them against PHP 8.2 attribute-based DTOs.

## Installation

```bash
composer require denzyl/php-config
```

## Usage

Define a DTO with attributes:

```php
use PhpConfig\Attribute\Required;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Nested;

class DatabaseConfig
{
    #[Required]
    public string $host;

    #[Required]
    #[Range(1, 65535)]
    public int $port;

    #[DefaultValue('root')]
    public string $username;

    #[Required]
    public string $password;

    #[DefaultValue(false)]
    public bool $ssl;

    #[Nested]
    public LogConfig $log;
}
```

Load and validate:

```php
use PhpConfig\Config;

$result = Config::load('config/database.yaml', DatabaseConfig::class);

$result->match(
    fn(DatabaseConfig $config) => {
        echo $config->host;        // autocomplete works
        echo $config->log->level;  // nested, autocomplete works
    },
    fn(ValidationException $e) => {
        foreach ($e->getErrors() as $error) {
            echo "{$error->path}: {$error->message}";
        }
    },
);
```

## Supported formats

| Format | Extension | Reader |
|--------|-----------|--------|
| YAML | `.yaml`, `.yml` | `symfony/yaml` |
| JSON | `.json` | `ext-json` |
| XML | `.xml` | `ext-simplexml` |
| PHP | `.php` | `require` + `return` |

## Attributes

| Attribute | Description |
|-----------|-------------|
| `#[Required]` | Field must be present and non-null |
| `#[Range(min, max)]` | Numeric range constraint |
| `#[Length(min, max)]` | String length constraint |
| `#[Regex(pattern)]` | String pattern match |
| `#[Email]` | Valid email format |
| `#[Url]` | Valid URL format |
| `#[DefaultValue(value)]` | Default value when absent |
| `#[Nested]` | Nested DTO (class inferred from property type) |

## Code generation

Generate DTO classes from an existing config file — useful when migrating or scaffolding.

### CLI

```bash
vendor/bin/php-config generate:class config/database.yaml --class=DatabaseConfig
vendor/bin/php-config generate:class config/app.json --output=src/Config/
```

Options:

| Option | Description |
|--------|-------------|
| `--class=<name>` | Class name (default: `AppConfig`) |
| `--namespace=<ns>` | Namespace (default: `App\Config`) |
| `--output=<dir>` | Write files to directory (default: stdout) |

### Programmatic

```php
use PhpConfig\Generator\SchemaGenerator;

$generator = new SchemaGenerator();
$files = $generator->generateFromFile('config/database.yaml', 'DatabaseConfig');

// Inspect or write
foreach ($files as $filename => $code) {
    echo $code;
}
```

Nested objects produce separate class files. Types are inferred from values: `string`, `int`, `float`, `bool`, nested `object`, or `array`. All scalar fields get `#[Required]` — remove it on fields that are optional.

## License

MIT
