<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Required;

class DatabaseConfig
{
    #[Required]
    public string $host;

    #[Required]
    #[Range(1, 65535)]
    public int $port;

    #[DefaultValue('root')]
    public string $username;

    #[Required]
    public string $password;

    #[DefaultValue(false)]
    public bool $ssl;

    #[Nested]
    public LogConfig $log;
}
