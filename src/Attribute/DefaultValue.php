<?php

declare(strict_types=1);

namespace PhpConfig\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class DefaultValue
{
    public function __construct(
        public mixed $value,
    ) {}
}
