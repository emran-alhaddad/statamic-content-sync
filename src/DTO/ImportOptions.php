<?php

namespace EmranAlhaddad\ContentSync\DTO;

class ImportOptions
{
    /** @param array<string,string> $decisions */
    public function __construct(
        public string $type,
        public array $decisions = [] // key => incoming|current
    ) {}
}
