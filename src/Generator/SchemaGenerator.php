<?php

declare(strict_types=1);

namespace PhpConfig\Generator;

use PhpConfig\ConfigLoader;
use PhpConfig\Format;
use PhpConfig\Reader\JsonReader;
use PhpConfig\Reader\PhpReader;
use PhpConfig\Reader\XmlReader;
use PhpConfig\Reader\YamlReader;

final class SchemaGenerator
{
    private ConfigLoader $loader;

    public function __construct()
    {
        $this->loader = new ConfigLoader([
            new YamlReader(),
            new JsonReader(),
            new XmlReader(),
            new PhpReader(),
        ]);
    }

    /**
     * Generate DTO class code from a config file.
     *
     * @return array<string, string> filename => code
     */
    public function generateFromFile(string $path, string $className, string $namespace = 'App\\Config'): array
    {
        $result = $this->loader->loadRaw($path);

        return $this->generate($result->unwrapOr([]), $className, $namespace);
    }

    /**
     * Generate DTO class code from a config array.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> filename => code
     */
    public function generate(
        array $data,
        string $className,
        string $namespace = 'App\\Config',
        string $parentPath = '',
    ): array {
        $files = [];
        $properties = [];
        $usedNested = [];

        foreach ($data as $key => $value) {
            $type = $this->inferType($value);
            $attributes = [];

            if ($type === 'object') {
                $nestedClassName = $this->nestedClassName($key, $className, $parentPath);
                $usedNested[] = $nestedClassName;
                $attributes[] = '#[Nested]';

                $nestedNamespace = $namespace;
                $nestedFiles = $this->generate($value, $nestedClassName, $nestedNamespace, $className);

                foreach ($nestedFiles as $nestedFile => $nestedCode) {
                    $files[$nestedFile] = $nestedCode;
                }

                $properties[] = $this->buildProperty($key, $nestedClassName, $attributes);
            } elseif ($type === 'array') {
                $properties[] = $this->buildProperty($key, 'array', ['#[Required]']);
            } elseif ($type === 'mixed') {
                $properties[] = $this->buildProperty($key, 'mixed');
            } else {
                $attributes[] = '#[Required]';
                $properties[] = $this->buildProperty($key, $type, $attributes, $value);
            }
        }

        $files[$className . '.php'] = $this->renderClass($className, $namespace, $properties, $usedNested);

        return $files;
    }

    private function buildProperty(string $name, string $type, array $attributes = [], mixed $default = null): string
    {
        $code = '';

        foreach ($attributes as $attr) {
            $code .= '    ' . $attr . "\n";
        }

        $code .= '    public ' . $type . ' $' . $name;

        if ($default !== null && \is_scalar($default)) {
            $code .= ' = ' . var_export($default, true) . ';';
        } elseif ($default !== null && \is_array($default)) {
            // For array defaults, keep it simple
            $code .= ' = [];';
        } else {
            $code .= ';';
        }

        return $code;
    }

    private function renderClass(string $className, string $namespace, array $properties, array $usedNested): string
    {
        $imports = [];

        if (\in_array(true, array_map(fn(string $p) => str_contains($p, '#[Nested]'), $properties), true)) {
            $imports[] = 'use PhpConfig\\Attribute\\Nested;';
        }

        if (\in_array(true, array_map(fn(string $p) => str_contains($p, '#[Required]'), $properties), true)) {
            $imports[] = 'use PhpConfig\\Attribute\\Required;';
        }

        $code = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n";
        $code .= implode("\n", $imports) . "\n\n";
        $code .= "class {$className}\n{\n";

        foreach ($properties as $prop) {
            $code .= $prop . "\n\n";
        }

        $code = rtrim($code, "\n") . "\n}\n";

        return $code;
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            \is_string($value) => 'string',
            \is_int($value) => 'int',
            \is_float($value) => 'float',
            \is_bool($value) => 'bool',
            \is_array($value) && $this->isIndexedArray($value) => 'array',
            \is_array($value) => 'object',
            $value === null => 'mixed',
            default => 'mixed',
        };
    }

    private function isIndexedArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, \count($value) - 1);
    }

    private function nestedClassName(string $key, string $parentClass, string $parentPath): string
    {
        $parts = explode('_', $key);
        $parts = array_map('ucfirst', $parts);

        $name = implode('', $parts);

        if ($parentPath === '') {
            return $name . 'Config';
        }

        return $name . 'Config';
    }

    /**
     * Write generated files to disk.
     *
     * @param array<string, string> $files
     */
    public function writeFiles(array $files, string $outputDir): void
    {
        foreach ($files as $filename => $code) {
            $path = rtrim($outputDir, '/') . '/' . $filename;
            file_put_contents($path, $code);
        }
    }
}
