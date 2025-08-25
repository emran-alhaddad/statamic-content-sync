<?php

namespace EmranAlhaddad\ContentSync\Contracts;

interface ImporterInterface
{
    /**
     * @param array $diffItem A single item diff payload with keys: key, incoming, current, status
     * @return array Updated counters ['updated'=>int,'created'=>int,'skipped'=>int]
     */
    public function apply(array $diffItem, array $counters): array;
}
