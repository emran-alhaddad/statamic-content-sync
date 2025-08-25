<?php

namespace EmranAlhaddad\ContentSync\Services;

use EmranAlhaddad\ContentSync\DTO\ValidationResult;

class ImportValidator
{
    public function validateType(string $type): ValidationResult
    {
        if (!in_array($type, ['collections','taxonomies','navigation','globals','assets'], true)) {
            return ValidationResult::fail(["Unknown type: {$type}"]);
        }
        return ValidationResult::ok();
    }
}
