<?php

namespace EmranAlhaddad\ContentSync\Mappers;

class EntryToArrayMapper
{
    /** @return array{uuid:string,collection:string,site:string,slug:string,published:bool,updated_at:?string,data:array} */
    public function map(\Statamic\Entries\Entry $e): array
    {
        $site = $e->site();
        $siteHandle = is_object($site) && method_exists($site, 'handle') ? (string)$site->handle() : (string)$site;
        $slug = $e->slug();
        $slugStr = is_string($slug) ? $slug : (string)$slug;

        return [
            'uuid'       => $e->id(),
            'collection' => $e->collectionHandle(),
            'site'       => $siteHandle,
            'slug'       => $slugStr,
            'published'  => (bool)$e->published(),
            'updated_at' => optional($e->model()?->updated_at)->toIso8601String(),
            'data'       => $e->data()->all(),
        ];
    }
}
