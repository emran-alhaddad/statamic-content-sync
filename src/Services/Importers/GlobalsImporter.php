<?php

namespace EmranAlhaddad\ContentSync\Services\Importers;

use EmranAlhaddad\ContentSync\Contracts\ImporterInterface;
use Statamic\Facades\GlobalSet;

class GlobalsImporter implements ImporterInterface
{
    public function apply(array $d, array $res): array
    {
        [$handle, $site] = explode('/', $d['key'], 2) + [null, null];
        if (($d['action'] ?? 'current') !== 'incoming') {
            $res['skipped']++;
            return $res;
        }
        $incoming = $d['incoming'] ?? [];

        $set = GlobalSet::findByHandle($handle);
        $loc = $set?->in($site);
        $loc->data($incoming);
        $loc->save();
        $res['updated']++;
        return $res;
    }
}
