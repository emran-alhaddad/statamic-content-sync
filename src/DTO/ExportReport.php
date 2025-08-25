<?php

namespace EmranAlhaddad\ContentSync\DTO;

class ExportReport
{
    public function __construct(
        public int $count,
        public string $outRelative
    ) {}
}
