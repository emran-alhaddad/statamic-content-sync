<?php

namespace EmranAlhaddad\ContentSync\Services\Importers;

use EmranAlhaddad\ContentSync\Contracts\ImporterInterface;
use Statamic\Facades\Nav;

class NavigationImporter implements ImporterInterface
{
    public function apply(array $d, array $res): array
    {
        [$handle, $site] = explode('/', $d['key'], 2) + [null, null];
        if (($d['action'] ?? 'current') !== 'incoming') {
            $res['skipped']++;
            return $res;
        }
        $incoming = $d['incoming'] ?? [];

        $nav = Nav::findByHandle($handle);
        $tree = $nav?->in($site) ?: $nav?->makeTree($site);
        $tree->tree($incoming);
        $tree->save();
        $res['updated']++;
        return $res;
    }
}
