<?php

namespace EmranAlhaddad\ContentSync\Contracts;

interface ExportWriter
{
    public function write(string $relativePath, array $payload): void;
}
