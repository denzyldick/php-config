<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Length;
use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Regex;
use PhpConfig\Attribute\Required;

class AllConstraintsConfig
{
    #[Required]
    #[Regex('/^[a-z]+$/')]
    public string $name;

    #[Required]
    #[Range(1, 100)]
    public int $count;

    #[DefaultValue('nope')]
    #[Length(min: 3, max: 10)]
    public string $label;

    #[Nested]
    public OptionalNestedConfig $nested;
}
