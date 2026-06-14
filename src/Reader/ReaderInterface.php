<?php

declare(strict_types=1);

namespace PhpConfig\Reader;

use PhpConfig\Format;

interface ReaderInterface
{
    public function supports(Format $format): bool;

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array;
}
