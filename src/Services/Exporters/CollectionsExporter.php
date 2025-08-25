<?php

namespace EmranAlhaddad\ContentSync\Services\Exporters;

use EmranAlhaddad\ContentSync\Contracts\ExporterInterface;
use Statamic\Facades\Entry;

class CollectionsExporter implements ExporterInterface
{
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => 'collections',
            'handles' => $handles,
            'sites' => $sites,
            'since' => $sinceIso,
            'items' => [],
        ];

        $query = Entry::query()->when($handles, fn($q) => $q->whereIn('collection', $handles));
        if ($sites) $query->whereIn('site', $sites);
        if ($sinceIso) $query->where('updated_at', '>=', $sinceIso);

        $payload['items'] = $query->get()->map(function ($e) {
            return [
                'uuid' => $e->id(),
                'collection' => $e->collectionHandle(),
                'site' => is_object($e->site()) && method_exists($e->site(), 'handle') ? $e->site()->handle() : (string)$e->site(),
                'slug' => (string)$e->slug(),
                'published' => (bool)$e->published(),
                'updated_at' => optional($e->model()?->updated_at)->toIso8601String(),
                'data' => $e->data()->all(),
            ];
        })->values()->all();

        return $payload;
    }
}
