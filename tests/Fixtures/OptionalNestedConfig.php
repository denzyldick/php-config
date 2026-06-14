<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\Required;

class OptionalNestedConfig
{
    #[Required]
    public string $key;
}
