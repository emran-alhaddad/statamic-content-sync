<?php

namespace EmranAlhaddad\ContentSync\Services\Exporters;

use EmranAlhaddad\ContentSync\Contracts\ExporterInterface;
use Statamic\Facades\AssetContainer;

class AssetsExporter implements ExporterInterface
{
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => 'assets',
            'handles' => $handles,
            'sites' => $sites,
            'since' => $sinceIso,
            'items' => [],
        ];

        $containers = $handles ?: AssetContainer::all()->map->handle()->all();
        $items = collect();
        foreach ($containers as $c) {
            $container = AssetContainer::findByHandle($c);
            foreach ($container->assets() as $a) {
                $items->push([
                    'container' => $c,
                    'path' => $a->path(),
                    'data' => $a->data()->all(),
                    'updated_at' => optional($a->model()?->updated_at)->toIso8601String(),
                ]);
            }
        }
        $payload['items'] = $items->values()->all();
        return $payload;
    }
}
