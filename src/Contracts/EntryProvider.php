<?php

namespace EmranAlhaddad\ContentSync\Contracts;

use Closure;

interface EntryProvider
{
    public function countCandidates(array $filters): int;

    /** @param Closure(object $row):void $callback */
    public function forEachCandidate(array $filters, Closure $callback): void;
}
