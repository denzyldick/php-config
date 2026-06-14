<?php

declare(strict_types=1);

namespace PhpConfig;

enum Format: string
{
    case YAML = 'yaml';
    case JSON = 'json';
    case XML = 'xml';
    case PHP = 'php';

    public static function fromExtension(string $path): self
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return match ($extension) {
            'yaml', 'yml' => self::YAML,
            'json' => self::JSON,
            'xml' => self::XML,
            'php' => self::PHP,
            default => throw new \InvalidArgumentException(sprintf('Unsupported config format: .%s', $extension)),
        };
    }
}
