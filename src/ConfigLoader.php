<?php

declare(strict_types=1);

namespace PhpConfig;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Exception\FieldError;
use PhpConfig\Exception\ValidationException;
use PhpConfig\Reader\ReaderInterface;
use PhpConfig\Schema\FieldDefinition;
use PhpConfig\Schema\SchemaResolver;
use ReflectionClass;
use ReflectionNamedType;
use Result\Result;

final class ConfigLoader
{
    /** @param ReaderInterface[] $readers */
    public function __construct(
        private readonly array $readers = [],
        private readonly SchemaResolver $schemaResolver = new SchemaResolver(),
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return Result<T, ValidationException>
     */
    public function load(string $path, string $dtoClass): Result
    {
        return Result::try(function () use ($path, $dtoClass) {
            $format = Format::fromExtension($path);
            $reader = $this->resolveReader($format);
            $data = $reader->read($path);

            return $this->hydrate($data, $dtoClass);
        })->mapError(fn(\Throwable $e) => (
            $e instanceof ValidationException ? $e : new ValidationException([new FieldError('', $e->getMessage())])
        ));
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return Result<T, ValidationException>
     */
    public function loadFromString(string $content, Format $format, string $dtoClass): Result
    {
        return Result::try(function () use ($content, $format, $dtoClass) {
            $data = match ($format) {
                Format::JSON => $this->parseJson($content),
                Format::YAML => $this->parseYaml($content),
                default => throw new \InvalidArgumentException('Only JSON and YAML are supported for string loading'),
            };

            return $this->hydrate($data, $dtoClass);
        })->mapError(fn(\Throwable $e) => (
            $e instanceof ValidationException ? $e : new ValidationException([new FieldError('', $e->getMessage())])
        ));
    }

    /**
     * @return Result<array<string, mixed>, \Throwable>
     */
    public function loadRaw(string $path): Result
    {
        return Result::try(function () use ($path) {
            $format = Format::fromExtension($path);
            $reader = $this->resolveReader($format);

            return $reader->read($path);
        });
    }

    private function resolveReader(Format $format): ReaderInterface
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($format)) {
                return $reader;
            }
        }

        throw new \RuntimeException(sprintf('No reader found for format: %s', $format->value));
    }

    /**
     * @param array<string, mixed> $data
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    private function hydrate(array $data, string $dtoClass): object
    {
        $fields = $this->schemaResolver->resolve($dtoClass);
        $errors = [];
        $values = [];

        foreach ($fields as $field) {
            $key = $field->name;
            $hasValue = \array_key_exists($key, $data);
            $value = $hasValue ? $data[$key] : null;

            $fieldErrors = $this->validateField($field, $value, $hasValue);

            foreach ($fieldErrors as $error) {
                $errors[] = $error;
            }

            if ($fieldErrors === []) {
                if ($hasValue) {
                    if ($field->isNested && \is_array($value)) {
                        $values[$key] = $this->hydrateNested($value, $field, $key, $errors);
                    } else {
                        $values[$key] = $value;
                    }
                } elseif ($field->hasDefault) {
                    $values[$key] = $field->default;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->instantiate($dtoClass, $values);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<FieldError> $errors
     */
    private function hydrateNested(array $data, FieldDefinition $field, string $parentPath, array &$errors): ?object
    {
        $nestedClass = $field->nestedClass;

        if ($nestedClass === null) {
            $errors[] = new FieldError($parentPath, 'Nested field has no class type hint');
            return null;
        }

        $nestedFields = $this->schemaResolver->resolve($nestedClass);
        $nestedValues = [];

        foreach ($nestedFields as $nestedField) {
            $key = $nestedField->name;
            $nestedPath = $parentPath . '.' . $key;
            $hasValue = \array_key_exists($key, $data);
            $value = $hasValue ? $data[$key] : null;

            $fieldErrors = $this->validateField($nestedField, $value, $hasValue, $nestedPath);

            foreach ($fieldErrors as $error) {
                $errors[] = $error;
            }

            if ($fieldErrors === []) {
                if ($hasValue) {
                    if ($nestedField->isNested && \is_array($value)) {
                        $nestedValues[$key] = $this->hydrateNested($value, $nestedField, $nestedPath, $errors);
                    } else {
                        $nestedValues[$key] = $value;
                    }
                } elseif ($nestedField->hasDefault) {
                    $nestedValues[$key] = $nestedField->default;
                }
            }
        }

        return $this->instantiate($nestedClass, $nestedValues);
    }

    /**
     * @return list<FieldError>
     */
    private function validateField(
        FieldDefinition $field,
        mixed $value,
        bool $hasValue,
        ?string $overridePath = null,
    ): array {
        $path = $overridePath ?? $field->path;
        $errors = [];

        if (!$hasValue) {
            if ($field->isRequired && !$field->hasDefault) {
                $errors[] = new FieldError($path, 'Required field is missing');
            }
            return $errors;
        }

        if ($value === null) {
            if ($field->isRequired) {
                $errors[] = new FieldError($path, 'Required field cannot be null', $value);
            }
            return $errors;
        }

        if ($field->isNested) {
            if (!\is_array($value)) {
                $errors[] = new FieldError(
                    $path,
                    sprintf('Expected an object, got %s', get_debug_type($value)),
                    $value,
                );
            }
            return $errors;
        }

        $errors = [...$errors, ...$this->validateType($field, $value, $path)];

        if ($errors === []) {
            $errors = [...$errors, ...$this->validateConstraints($field, $value, $path)];
        }

        return $errors;
    }

    /**
     * @return list<FieldError>
     */
    private function validateType(FieldDefinition $field, mixed $value, string $path): array
    {
        $actual = get_debug_type($value);

        $matches = match ($field->phpType) {
            'string' => \is_string($value),
            'int' => \is_int($value),
            'float' => \is_float($value),
            'bool' => \is_bool($value),
            'array' => \is_array($value),
            'mixed' => true,
            default => true,
        };

        if (!$matches) {
            return [new FieldError($path, sprintf('Expected %s, got %s', $field->phpType, $actual), $value)];
        }

        return [];
    }

    /**
     * @return list<FieldError>
     */
    private function validateConstraints(FieldDefinition $field, mixed $value, string $path): array
    {
        $errors = [];

        if ($field->range !== null && \is_int($value)) {
            if ($value < $field->range->min || $value > $field->range->max) {
                $errors[] = new FieldError(
                    $path,
                    sprintf('Must be between %s and %s', $field->range->min, $field->range->max),
                    $value,
                );
            }
        }

        if ($field->length !== null && \is_string($value)) {
            $len = mb_strlen($value);
            if ($len < $field->length->min) {
                $errors[] = new FieldError(
                    $path,
                    sprintf('Must be at least %d characters long', $field->length->min),
                    $value,
                );
            }
            if ($field->length->max !== null && $len > $field->length->max) {
                $errors[] = new FieldError(
                    $path,
                    sprintf('Must be at most %d characters long', $field->length->max),
                    $value,
                );
            }
        }

        if ($field->regex !== null && \is_string($value)) {
            if (preg_match($field->regex->pattern, $value) !== 1) {
                $errors[] = new FieldError(
                    $path,
                    sprintf('Does not match required pattern: %s', $field->regex->pattern),
                    $value,
                );
            }
        }

        if ($field->isEmail && \is_string($value)) {
            if (!filter_var($value, \FILTER_VALIDATE_EMAIL)) {
                $errors[] = new FieldError($path, 'Must be a valid email address', $value);
            }
        }

        if ($field->isUrl && \is_string($value)) {
            if (!filter_var($value, \FILTER_VALIDATE_URL)) {
                $errors[] = new FieldError($path, 'Must be a valid URL', $value);
            }
        }

        return $errors;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param array<string, mixed> $values
     * @return T
     */
    private function instantiate(string $className, array $values): object
    {
        $reflection = new ReflectionClass($className);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($values as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setValue($instance, $value);
        }

        return $instance;
    }

    private function parseJson(string $content): array
    {
        $decoded = json_decode($content, true);

        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf('Invalid JSON: %s', json_last_error_msg()));
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('JSON root must be an object');
        }

        return $decoded;
    }

    private function parseYaml(string $content): array
    {
        $data = \Symfony\Component\Yaml\Yaml::parse($content);

        if (!\is_array($data)) {
            throw new \RuntimeException('YAML root must be a mapping');
        }

        return $data;
    }
}
