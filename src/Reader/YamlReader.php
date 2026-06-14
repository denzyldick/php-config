<?php

declare(strict_types=1);

namespace PhpConfig\Reader;

use PhpConfig\Exception\ConfigException;
use PhpConfig\Format;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlReader implements ReaderInterface
{
    public function supports(Format $format): bool
    {
        return $format === Format::YAML;
    }

    public function read(string $path): array
    {
        $data = Yaml::parseFile($path);

        if (!\is_array($data)) {
            throw new ConfigException(sprintf('YAML root must be a mapping in %s', $path));
        }

        return $data;
    }
}
