<?php

declare(strict_types=1);

namespace PhpConfig\Reader;

use PhpConfig\Exception\ConfigException;
use PhpConfig\Format;

final readonly class PhpReader implements ReaderInterface
{
    public function supports(Format $format): bool
    {
        return $format === Format::PHP;
    }

    public function read(string $path): array
    {
        $data = require $path;

        if (!\is_array($data)) {
            throw new ConfigException(sprintf('PHP config file must return an array in %s', $path));
        }

        return $data;
    }
}
