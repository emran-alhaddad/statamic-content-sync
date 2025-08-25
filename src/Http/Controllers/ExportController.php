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
            'type'        => 'required|in:collections,taxonomies,navigation,globals,assets',
            'handles'     => 'array',
            'handles.*'   => 'string',
            'sites'       => 'array',
            'sites.*'     => 'string',
            'since'       => 'nullable|string',
            'out'         => 'nullable|string', // optional custom filename
        ]);

        $type    = $validated['type'];
        $handles = $validated['handles'] ?? [];
        $sites   = $validated['sites'] ?? [];
        $since   = $validated['since'] ?? null;

        // Parse 'since' safely (supports ISO8601 or Y-m-d H:i:s)
        $sinceAt = null;
        if ($since) {
            try {
                $sinceAt = \Carbon\Carbon::parse($since);
            } catch (\Throwable $e) {
                $sinceAt = null;
            }
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type'        => $type,
            'handles'     => $handles,
            'sites'       => $sites,
            'since'       => $since,
            'items'       => [],
        ];

        switch ($type) {
            case 'collections': {
                    $query = \Statamic\Facades\Entry::query()
                        ->when($handles, fn($q) => $q->whereIn('collection', $handles))
                        ->when($sites,   fn($q) => $q->whereIn('site', $sites))
                        ->when($sinceAt, fn($q) => $q->where('updated_at', '>=', $sinceAt));

                    $items = $query->get()->map(function ($e) {
                        $site = $e->site();
                        $siteHandle = is_object($site) && method_exists($site, 'handle') ? $site->handle() : (string) $site;

                        return [
                            'uuid'       => $e->id(),
                            'collection' => $e->collectionHandle(),
                            'site'       => $siteHandle,
                            'slug'       => (string) $e->slug(),
                            'published'  => (bool) $e->published(),
                            'updated_at' => optional($e->model()?->updated_at)->toIso8601String(),
                            'data'       => $e->data()->all(),
                        ];
                    });

                    $payload['items'] = $items->values();
                    break;
                }

            case 'taxonomies': {
                    $taxonomyHandles = $handles ?: \Statamic\Facades\Taxonomy::all()->map->handle()->all();
                    $items = collect();

                    foreach ($taxonomyHandles as $tax) {
                        $terms = \Statamic\Facades\Term::query()
                            ->where('taxonomy', $tax)
                            ->when($sites,   fn($q) => $q->whereIn('site', $sites))
                            ->when($sinceAt, fn($q) => $q->where('updated_at', '>=', $sinceAt))
                            ->get();

                        $items = $items->merge($terms->map(function ($t) use ($tax) {
                            return [
                                'id'         => $t->id(),
                                'taxonomy'   => $tax,
                                'site'       => $t->site(),
                                'slug'       => (string) $t->slug(),
                                'data'       => $t->data()->all(),
                                'updated_at' => optional($t->model()?->updated_at)->toIso8601String(),
                            ];
                        }));
                    }

                    $payload['items'] = $items->values();
                    break;
                }

            case 'navigation': {
                    $navHandles = $handles ?: \Statamic\Facades\Nav::all()->map->handle()->all();
                    $items = collect();

                    foreach ($navHandles as $nav) {
                        $trees = \Statamic\Facades\Nav::findByHandle($nav)?->trees() ?? collect();
                        foreach ($trees as $tree) {
                            // Tree content (array) is returned by ->tree()
                            $items->push([
                                'handle'     => $nav,
                                'site'       => $tree->site(),
                                'tree'       => $tree->tree(),
                                'updated_at' => now()->toIso8601String(),
                            ]);
                        }
                    }

                    $payload['items'] = $items->values();
                    break;
                }

            case 'globals': {
                    $setHandles = $handles ?: \Statamic\Facades\GlobalSet::all()->map->handle()->all();
                    $items = collect();

                    foreach ($setHandles as $set) {
                        $gs = \Statamic\Facades\GlobalSet::findByHandle($set);
                        if (! $gs) {
                            continue;
                        }

                        foreach ($gs->localizations() as $loc) {
                            $items->push([
                                'handle'     => $set,
                                'site'       => $loc->site()->handle(),
                                'data'       => $loc->data()->all(),
                                'updated_at' => optional($loc->model()?->updated_at)->toIso8601String(),
                            ]);
                        }
                    }

                    $payload['items'] = $items->values();
                    break;
                }

            case 'assets': {
                    $containerHandles = $handles ?: \Statamic\Facades\AssetContainer::all()->map->handle()->all();
                    $items = collect();

                    foreach ($containerHandles as $c) {
                        $container = \Statamic\Facades\AssetContainer::findByHandle($c);
                        if (! $container) {
                            continue;
                        }

                        $assets = $container->assets(); // iterable of Asset objects
                        foreach ($assets as $a) {
                            // Note: we export meta, not binaries.
                            $items->push([
                                'container'  => $c,
                                'path'       => $a->path(),
                                'data'       => $a->data()->all(),
                                'updated_at' => optional($a->model()?->updated_at)->toIso8601String(),
                            ]);
                        }
                    }

                    $payload['items'] = $items->values();
                    break;
                }
        }

        // -------- persist file ----------
        $disk   = config('content-sync.disk', 'local');
        $folder = trim(config('content-sync.folder', 'sync'), '/');

        // Optional custom filename; fallback to timestamped default
        $custom = $validated['out'] ?? null;
        $file   = $custom ? basename($custom) : sprintf('%s-export-%s.json', $type, now()->format('Ymd-His'));

        // Ensure folder exists and write
        if (! Storage::disk($disk)->exists($folder)) {
            Storage::disk($disk)->makeDirectory($folder);
        }

        $relativePath = $folder . '/' . $file;
        Storage::disk($disk)->put(
            $relativePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json([
            'ok'           => true,
            'disk'         => $disk,
            'path'         => Storage::disk($disk)->path($relativePath),
            'download_url' => route('content-sync.download', ['file' => $file]), // ✅ our CP download route
            'count'        => count($payload['items']),
        ]);
    }
}
