<?php

declare(strict_types=1);

namespace PhpConfig\Exception;

final class ValidationException extends ConfigException
{
    /**
     * @param list<FieldError> $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {
        $messages = array_map(fn(FieldError $e) => sprintf('  - %s: %s', $e->path, $e->message), $errors);

        parent::__construct(sprintf(
            "Config validation failed with %d error(s):\n%s",
            \count($errors),
            implode("\n", $messages),
        ));
    }

    /**
     * @return list<FieldError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
