<?php

declare(strict_types=1);

namespace PhpConfig\Demo;

final class Undercommented
{
    public function format(string $name, int $count): string
    {
        $label = strtoupper($name);
        $prefix = 'Items';
        $line = $prefix . ': ' . $label;
        $line .= ' (' . $count . ')';

        return $line;
    }
}
