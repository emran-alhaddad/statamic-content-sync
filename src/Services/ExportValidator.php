<?php

namespace EmranAlhaddad\ContentSync\Services;

use EmranAlhaddad\ContentSync\DTO\ValidationResult;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

class ExportValidator
{
    public function validateType(string $type): ValidationResult
    {
        if (!in_array($type, ['collections', 'taxonomies', 'navigation', 'globals', 'assets'], true)) {
            return ValidationResult::fail(["Unknown type: {$type}"]);
        }
        return ValidationResult::ok();
    }

    /** @param string[] $sites */
    public function validateSites(array $sites): ValidationResult
    {
        if (!$sites) return ValidationResult::ok();
        $validSites = Site::all()->map->handle()->all();
        $invalid = array_values(array_diff($sites, $validSites));
        return $invalid ? ValidationResult::fail(['Unknown sites: ' . implode(', ', $invalid)]) : ValidationResult::ok();
    }

    /** @param string[] $collections */
    public function validateCollections(array $collections): ValidationResult
    {
        if (!$collections) return ValidationResult::ok();
        $valid = Collection::all()->map->handle()->all();
        $invalid = array_values(array_diff($collections, $valid));
        return $invalid ? ValidationResult::fail(['Unknown collections: ' . implode(', ', $invalid)]) : ValidationResult::ok();
    }
}
