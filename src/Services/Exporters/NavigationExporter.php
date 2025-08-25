<?php

namespace EmranAlhaddad\ContentSync\Services\Exporters;

use EmranAlhaddad\ContentSync\Contracts\ExporterInterface;
use Statamic\Facades\Nav;

class NavigationExporter implements ExporterInterface
{
    public function export(array $handles = [], array $sites = [], ?string $sinceIso = null): array
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => 'navigation',
            'handles' => $handles,
            'sites' => $sites,
            'since' => $sinceIso,
            'items' => [],
        ];

        $navs = $handles ?: Nav::all()->map->handle()->all();
        $items = collect();
        foreach ($navs as $nav) {
            $trees = Nav::findByHandle($nav)?->trees() ?? collect();
            foreach ($trees as $tree) {
                if ($sites && !in_array($tree->site(), $sites, true)) continue;
                $items->push([
                    'handle' => $nav,
                    'site' => $tree->site(),
                    'tree' => $tree->tree(),
                    'updated_at' => now()->toIso8601String(),
                ]);
            }
        }
        $payload['items'] = $items->values()->all();
        return $payload;
    }
}
