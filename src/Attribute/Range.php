<?php

declare(strict_types=1);

namespace PhpConfig\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Range
{
    public function __construct(
        public int|float $min,
        public int|float $max,
    ) {}
}
