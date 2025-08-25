<?php

namespace EmranAlhaddad\ContentSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Collection;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Nav;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;

class ExportController extends Controller
{
    public function options(Request $request)
    {
        $type = $request->query('type');
        return match ($type) {
            'collections' => response()->json(Collection::all()->map->handle()->values()),
            'taxonomies'  => response()->json(Taxonomy::all()->map->handle()->values()),
            'navigation'  => response()->json(Nav::all()->map->handle()->values()),
            'globals'     => response()->json(GlobalSet::all()->map->handle()->values()),
            'assets'      => response()->json(AssetContainer::all()->map->handle()->values()),
            default       => response()->json(['collections', 'taxonomies', 'navigation', 'globals', 'assets'])
        };
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:collections,taxonomies,navigation,globals,assets',
            'handles' => 'array',
            'handles.*' => 'string',
            'sites' => 'array',
            'sites.*' => 'string',
            'since' => 'nullable|string'
        ]);

        $type = $validated['type'];
        $handles = $validated['handles'] ?? [];
        $sites = $validated['sites'] ?? [];
        $since = $validated['since'] ?? null;

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type' => $type,
            'handles' => $handles,
            'sites' => $sites,
            'since' => $since,
            'items' => [],
        ];

        switch ($type) {
            case 'collections':
                $query = Entry::query()->when($handles, fn($q) => $q->whereIn('collection', $handles));
                if ($sites) $query->whereIn('site', $sites);
                if ($since) $query->where('updated_at', '>=', $since);
                $items = $query->get()->map(function ($e) {
                    return [
                        'uuid' => $e->id(),
                        'collection' => $e->collectionHandle(),
                        'site' => is_object($e->site()) && method_exists($e->site(), 'handle') ? $e->site()->handle() : (string)$e->site(),
                        'slug' => (string)$e->slug(),
                        'published' => (bool)$e->published(),
                        'updated_at' => optional($e->model()?->updated_at)->toIso8601String(),
                        'data' => $e->data()->all(),
                    ];
                });
                $payload['items'] = $items->values();
                break;

            case 'taxonomies':
                $taxonomies = $handles ?: Taxonomy::all()->map->handle();
                $items = collect();
                foreach ($taxonomies as $tax) {
                    $terms = \Statamic\Facades\Term::query()->where('taxonomy', $tax)->when($sites, fn($q) => $q->whereIn('site', $sites))->get();
                    $items = $items->merge($terms->map(fn($t) => [
                        'id' => $t->id(),
                        'taxonomy' => $tax,
                        'site' => $t->site(),
                        'slug' => (string)$t->slug(),
                        'data' => $t->data()->all(),
                        'updated_at' => optional($t->model()?->updated_at)->toIso8601String(),
                    ]));
                }
                $payload['items'] = $items->values();
                break;

            case 'navigation':
                $navs = $handles ?: Nav::all()->map->handle();
                $items = collect();
                foreach ($navs as $nav) {
                    $trees = Nav::findByHandle($nav)?->trees() ?? collect();
                    foreach ($trees as $tree) {
                        $items->push([
                            'handle' => $nav,
                            'site' => $tree->site(),
                            'tree' => $tree->tree(),
                            'updated_at' => now()->toIso8601String(),
                        ]);
                    }
                }
                $payload['items'] = $items->values();
                break;

            case 'globals':
                $sets = $handles ?: GlobalSet::all()->map->handle();
                $items = collect();
                foreach ($sets as $set) {
                    $gs = GlobalSet::findByHandle($set);
                    foreach ($gs->localizations() as $loc) {
                        $items->push([
                            'handle' => $set,
                            'site' => $loc->site()->handle(),
                            'data' => $loc->data()->all(),
                            'updated_at' => optional($loc->model()?->updated_at)->toIso8601String(),
                        ]);
                    }
                }
                $payload['items'] = $items->values();
                break;

            case 'assets':
                $containers = $handles ?: AssetContainer::all()->map->handle();
                $items = collect();
                foreach ($containers as $c) {
                    $container = AssetContainer::findByHandle($c);
                    $assets = $container->assets();
                    foreach ($assets as $a) {
                        $items->push([
                            'container' => $c,
                            'path' => $a->path(),
                            'data' => $a->data()->all(),
                            'updated_at' => optional($a->model()?->updated_at)->toIso8601String(),
                        ]);
                    }
                }
                $payload['items'] = $items->values();
                break;
        }

        $filename = sprintf('%s/%s-export-%s.json', config('content-sync.folder', 'sync'), $type, now()->format('Ymd-His'));
        Storage::disk(config('content-sync.disk', 'local'))->put($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'ok' => true,
            'disk' => config('content-sync.disk', 'local'),
            'path' => $filename,
            'download' => route('statamic.cp.assets.show', ['path' => $filename]) ?? null,
            'count' => count($payload['items']),
        ]);
    }
}
