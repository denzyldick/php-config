<?php

declare(strict_types=1);

namespace PhpConfig\Schema;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Email;
use PhpConfig\Attribute\Length;
use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Regex;
use PhpConfig\Attribute\Required;
use PhpConfig\Attribute\Url;
use PhpConfig\Exception\ConfigException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

final class SchemaResolver
{
    /**
     * @param class-string $className
     * @return list<FieldDefinition>
     */
    public function resolve(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $fields = [];

        foreach ($reflection->getProperties() as $property) {
            $fields[] = $this->resolveProperty($property);
        }

        return $fields;
    }

    private function resolveProperty(ReflectionProperty $property): FieldDefinition
    {
        $attributes = $property->getAttributes();
        $name = $property->getName();
        $type = $property->getType();

        $phpType = 'mixed';
        $isNested = false;
        $nestedClass = null;

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($type->isBuiltin()) {
                $phpType = $typeName;
            } else {
                $phpType = 'object';
                $nestedClass = $typeName;
                $isNested = true;
            }
        }

        $range = null;
        $length = null;
        $regex = null;
        $isEmail = false;
        $isUrl = false;
        $hasDefault = false;
        $default = null;
        $isRequired = false;

        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof Required) {
                $isRequired = true;
            } elseif ($instance instanceof Range) {
                $range = $instance;
            } elseif ($instance instanceof Length) {
                $length = $instance;
            } elseif ($instance instanceof Regex) {
                $regex = $instance;
            } elseif ($instance instanceof Email) {
                $isEmail = true;
            } elseif ($instance instanceof Url) {
                $isUrl = true;
            } elseif ($instance instanceof DefaultValue) {
                $hasDefault = true;
                $default = $instance->value;
            } elseif ($instance instanceof Nested) {
                $isNested = true;
                $nestedClass ??= $this->resolveNestedClass($property);
            }
        }

        return new FieldDefinition(
            name: $name,
            path: $name,
            phpType: $phpType,
            isRequired: $isRequired,
            range: $range,
            length: $length,
            regex: $regex,
            isEmail: $isEmail,
            isUrl: $isUrl,
            hasDefault: $hasDefault,
            default: $default,
            isNested: $isNested,
            nestedClass: $nestedClass,
        );
    }

    /**
     * @return class-string|null
     */
    private function resolveNestedClass(ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $type->getName();
        }

        return null;
    }
}
