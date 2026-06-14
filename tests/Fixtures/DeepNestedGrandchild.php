<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Required;

class DeepNestedGrandchild
{
    #[Required]
    #[Range(1, 999)]
    public int $value;
}
