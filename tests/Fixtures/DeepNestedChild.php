<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Required;

class DeepNestedChild
{
    #[Required]
    public string $name;

    #[Nested]
    public DeepNestedGrandchild $grandchild;
}
