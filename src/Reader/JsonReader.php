<?php

declare(strict_types=1);

namespace PhpConfig\Reader;

use PhpConfig\Exception\ConfigException;
use PhpConfig\Format;

final readonly class JsonReader implements ReaderInterface
{
    public function supports(Format $format): bool
    {
        return $format === Format::JSON;
    }

    public function read(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigException(sprintf('Unable to read file: %s', $path));
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new ConfigException(sprintf('Invalid JSON in %s: %s', $path, json_last_error_msg()));
        }

        if (!\is_array($decoded)) {
            throw new ConfigException(sprintf('JSON root must be an object in %s', $path));
        }

        return $decoded;
    }
}
