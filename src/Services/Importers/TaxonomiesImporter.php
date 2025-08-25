<?php

namespace EmranAlhaddad\ContentSync\Services\Importers;

use EmranAlhaddad\ContentSync\Contracts\ImporterInterface;
use Statamic\Facades\Term;

class TaxonomiesImporter implements ImporterInterface
{
    public function apply(array $d, array $res): array
    {
        [$taxonomy, $site, $slug] = explode('/', $d['key'], 3) + [null, null, null];
        if (($d['action'] ?? 'current') !== 'incoming') {
            $res['skipped']++;
            return $res;
        }
        $incoming = $d['incoming'] ?? [];

        $live = Term::query()->where('taxonomy', $taxonomy)->where('site', $site)->where('slug', $slug)->first();
        if (!$live) {
            $t = Term::make()->taxonomy($taxonomy)->site($site)->slug($slug)->data($incoming);
            $t->save();
            $res['created']++;
            return $res;
        }
        $live->data($incoming);
        $live->save();
        $res['updated']++;
        return $res;
    }
}
