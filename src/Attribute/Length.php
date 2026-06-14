<?php

declare(strict_types=1);

namespace PhpConfig\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Length
{
    public function __construct(
        public int $min = 0,
        public ?int $max = null,
    ) {}
}
