<?php

namespace EmranAlhaddad\ContentSync\DTO;

class ExportOptions
{
    /** @param string[] $handles @param string[] $sites */
    public function __construct(
        public string $type,
        public array $handles = [],
        public array $sites = [],
        public ?string $sinceIso = null,
        public string $out = 'export.json'
    ) {}
}
