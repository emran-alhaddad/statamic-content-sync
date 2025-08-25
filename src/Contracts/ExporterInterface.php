<?php

namespace EmranAlhaddad\ContentSync\Contracts;

interface ExporterInterface
{
    /**
     * @param string[] $handles
     * @param string[] $sites
     * @return array{exported_at:string,type:string,handles:array,sites:array,since:?string,items:array}
     */
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array;
}
