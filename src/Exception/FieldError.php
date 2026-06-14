<?php

declare(strict_types=1);

namespace PhpConfig\Exception;

final readonly class FieldError
{
    public function __construct(
        public string $path,
        public string $message,
        public mixed $value = null,
    ) {}
}
