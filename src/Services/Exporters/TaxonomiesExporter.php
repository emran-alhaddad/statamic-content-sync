<?php

namespace EmranAlhaddad\ContentSync\Services\Exporters;

use EmranAlhaddad\ContentSync\Contracts\ExporterInterface;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class TaxonomiesExporter implements ExporterInterface
{
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => 'taxonomies',
            'handles' => $handles,
            'sites' => $sites,
            'since' => $sinceIso,
            'items' => [],
        ];

        $taxonomies = $handles ?: Taxonomy::all()->map->handle()->all();
        $items = collect();
        foreach ($taxonomies as $tax) {
            $terms = Term::query()->where('taxonomy', $tax)->when($sites, fn($q) => $q->whereIn('site', $sites))->get();
            $items = $items->merge($terms->map(fn($t) => [
                'id' => $t->id(),
                'taxonomy' => $tax,
                'site' => $t->site(),
                'slug' => (string)$t->slug(),
                'data' => $t->data()->all(),
                'updated_at' => optional($t->model()?->updated_at)->toIso8601String(),
            ]));
        }
        $payload['items'] = $items->values()->all();
        return $payload;
    }
}
