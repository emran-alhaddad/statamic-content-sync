<?php

namespace EmranAlhaddad\ContentSync\Services\Importers;

use EmranAlhaddad\ContentSync\Contracts\ImporterInterface;
use Statamic\Facades\Entry;

class CollectionsImporter implements ImporterInterface
{
    public function apply(array $d, array $res): array
    {
        // key = collection/site/slug
        [$collection, $site, $slug] = explode('/', $d['key'], 3) + [null, null, null];
        if (($d['action'] ?? 'current') !== 'incoming') {
            $res['skipped']++;
            return $res;
        }
        $incoming = $d['incoming'] ?? [];

        $live = Entry::query()->where('collection', $collection)->where('site', $site)->where('slug', $slug)->first();
        if (!$live) {
            $entry = Entry::make()->collection($collection)->site($site)->slug($slug)->data($incoming)->published(true);
            $entry->save();
            $res['created']++;
            return $res;
        }
        $live->data($incoming);
        $live->save();
        $res['updated']++;
        return $res;
    }
}
