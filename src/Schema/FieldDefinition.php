<?php

declare(strict_types=1);

namespace PhpConfig\Schema;

use PhpConfig\Attribute\DefaultValue;
use PhpConfig\Attribute\Email;
use PhpConfig\Attribute\Length;
use PhpConfig\Attribute\Nested;
use PhpConfig\Attribute\Range;
use PhpConfig\Attribute\Regex;
use PhpConfig\Attribute\Required;
use PhpConfig\Attribute\Url;

final readonly class FieldDefinition
{
    /**
     * @param class-string|null $nestedClass
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $phpType,
        public bool $isRequired = false,
        public ?Range $range = null,
        public ?Length $length = null,
        public ?Regex $regex = null,
        public bool $isEmail = false,
        public bool $isUrl = false,
        public mixed $default = null,
        public bool $hasDefault = false,
        public bool $isNested = false,
        public ?string $nestedClass = null,
    ) {}
}
