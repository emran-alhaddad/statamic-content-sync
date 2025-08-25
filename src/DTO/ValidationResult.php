<?php

namespace EmranAlhaddad\ContentSync\DTO;

class ValidationResult
{
    /** @param string[] $errors */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }
    public static function ok(): self
    {
        return new self(true);
    }
    /** @param string[] $errors */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}
