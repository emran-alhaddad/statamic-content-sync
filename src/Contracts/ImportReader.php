<?php

namespace EmranAlhaddad\ContentSync\Contracts;

interface ImportReader
{
    /**
     * Resolve and read JSON (absolute, project-relative, or disk-relative).
     * @return array Parsed JSON
     * @throws \RuntimeException
     */
    public function read(string $pathOrName): array;
}
