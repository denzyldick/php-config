<?php

declare(strict_types=1);

namespace PhpConfig\Reader;

use PhpConfig\Exception\ConfigException;
use PhpConfig\Format;
use SimpleXMLElement;

final readonly class XmlReader implements ReaderInterface
{
    public function supports(Format $format): bool
    {
        return $format === Format::XML;
    }

    public function read(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new ConfigException(sprintf('Unable to read file: %s', $path));
        }

        $xml = simplexml_load_string($content);

        if ($xml === false) {
            throw new ConfigException(sprintf('Invalid XML in %s', $path));
        }

        return $this->xmlToArray($xml);
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->children() as $key => $child) {
            if ($child->count() > 0) {
                $result[$key] = $this->xmlToArray($child);
            } else {
                $value = (string) $child;

                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }

                $result[$key] = $value;

                $attrs = $child->attributes();
                if ($attrs && isset($attrs['type'])) {
                    $result[$key] = match ((string) $attrs['type']) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'bool' => $value === 'true',
                        'null' => null,
                        default => $value,
                    };
                }
            }
        }

        return $result;
    }
}
