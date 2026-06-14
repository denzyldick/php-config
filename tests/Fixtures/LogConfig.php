<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Required;

class LogConfig
{
    #[Required]
    public string $level;

    #[DefaultValue('logs/app.log')]
    public string $path;
}
