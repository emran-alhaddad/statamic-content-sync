<?php

namespace EmranAlhaddad\ContentSync\Services\Exporters;

use EmranAlhaddad\ContentSync\Contracts\ExporterInterface;
use Statamic\Facades\GlobalSet;

class GlobalsExporter implements ExporterInterface
{
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => 'globals',
            'handles' => $handles,
            'sites' => $sites,
            'since' => $sinceIso,
            'items' => [],
        ];

        $sets = $handles ?: GlobalSet::all()->map->handle()->all();
        $items = collect();
        foreach ($sets as $set) {
            $gs = GlobalSet::findByHandle($set);
            foreach ($gs->localizations() as $loc) {
                if ($sites && !in_array($loc->site()->handle(), $sites, true)) continue;
                $items->push([
                    'handle' => $set,
                    'site' => $loc->site()->handle(),
                    'data' => $loc->data()->all(),
                    'updated_at' => optional($loc->model()?->updated_at)->toIso8601String(),
                ]);
            }
        }
        $payload['items'] = $items->values()->all();
        return $payload;
    }
}
