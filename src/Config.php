<?php

declare(strict_types=1);

namespace PhpConfig;

use PhpConfig\Reader\JsonReader;
use PhpConfig\Reader\PhpReader;
use PhpConfig\Reader\XmlReader;
use PhpConfig\Reader\YamlReader;
use Result\Result;

final class Config
{
    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return Result<T, \PhpConfig\Exception\ValidationException>
     */
    public static function load(string $path, string $dtoClass): Result
    {
        $loader = self::createLoader();

        return $loader->load($path, $dtoClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return Result<T, \PhpConfig\Exception\ValidationException>
     */
    public static function loadFromString(string $content, Format $format, string $dtoClass): Result
    {
        $loader = self::createLoader();

        return $loader->loadFromString($content, $format, $dtoClass);
    }

    /**
     * @return Result<array<string, mixed>, \Throwable>
     */
    public static function loadRaw(string $path): Result
    {
        $loader = self::createLoader();

        return $loader->loadRaw($path);
    }

    /**
     * @return ConfigLoader
     */
    private static function createLoader(): ConfigLoader
    {
        return new ConfigLoader([
            new YamlReader(),
            new JsonReader(),
            new XmlReader(),
            new PhpReader(),
        ]);
    }
}
