<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Required;

class DeepNestedParent
{
    #[Nested]
    public DeepNestedChild $child;
}
