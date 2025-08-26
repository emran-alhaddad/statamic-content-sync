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
        $type = $request->string('type')->toString();

        if ($type === 'sites') {
            $options = \Statamic\Facades\Site::all()->map->handle()->values()->all();
            return response()->json(['options' => $options]);
        }

        switch ($type) {
            case 'collections':
                $options = \Statamic\Facades\Collection::all()->map->handle()->values()->all();
                break;
            case 'taxonomies':
                $options = \Statamic\Facades\Taxonomy::all()->map->handle()->values()->all();
                break;
            case 'navigation':
                $options = \Statamic\Facades\Nav::all()->map->handle()->values()->all();
                break;
            case 'globals':
                $options = \Statamic\Facades\GlobalSet::all()->map->handle()->values()->all();
                break;
            case 'assets':
                $options = \Statamic\Facades\AssetContainer::all()->map->handle()->values()->all();
                break;
            default:
                $options = [];
        }

        return response()->json(['options' => $options]);
    }


    public function export(Request $request)
    {
        $validated = $request->validate([
            'type'      => 'required|in:collections,taxonomies,navigation,globals,assets',
            'handles'   => 'nullable',
            'sites'     => 'nullable',
            'since'     => 'nullable|string',
            'out'       => 'nullable|string',
        ]);

        $type    = $validated['type'];
        $handles = is_string($request->handles) ? json_decode($request->handles, true) : ($request->handles ?? []);
        $sites   = is_string($request->sites)   ? json_decode($request->sites, true)   : ($request->sites ?? []);
        $since   = $validated['since'] ?? null;

        $sinceAt = null;
        if ($since) {
            try {
                $sinceAt = \Carbon\Carbon::parse($since);
            } catch (\Throwable $e) {
            }
        }

        $items = collect();

        if ($type === 'collections') {
            $q = \Statamic\Facades\Entry::query()
                ->when($handles, fn($q) => $q->whereIn('collection', $handles))
                ->when($sites, fn($q) => $q->whereIn('site', $sites))
                ->when($sinceAt, fn($q) => $q->where('updated_at', '>=', $sinceAt));
            $items = $q->get()->map(function ($e) {
                $site = $e->site();
                $siteHandle = is_object($site) && method_exists($site, 'handle') ? $site->handle() : (string)$site;
                return [
                    'uuid'       => $e->id(),
                    'collection' => $e->collectionHandle(),
                    'site'       => $siteHandle,
                    'slug'       => (string)$e->slug(),
                    'published'  => (bool)$e->published(),
                    'updated_at' => optional($e->model()?->updated_at)->toIso8601String(),
                    'data'       => $e->data()->all(),
                ];
            });
        }

        if ($type === 'taxonomies') {
            $taxonomyHandles = $handles ?: \Statamic\Facades\Taxonomy::all()->map->handle()->all();
            foreach ($taxonomyHandles as $tax) {
                $terms = \Statamic\Facades\Term::query()
                    ->where('taxonomy', $tax)
                    ->when($sites, fn($q) => $q->whereIn('site', $sites))
                    ->when($sinceAt, fn($q) => $q->where('updated_at', '>=', $sinceAt))
                    ->get();
                $items = $items->merge($terms->map(fn($t) => [
                    'id'         => $t->id(),
                    'taxonomy'   => $tax,
                    'site'       => $t->site(),
                    'slug'       => (string)$t->slug(),
                    'data'       => $t->data()->all(),
                    'updated_at' => optional($t->model()?->updated_at)->toIso8601String(),
                ]));
            }
        }

        if ($type === 'navigation') {
            $navHandles = $handles ?: \Statamic\Facades\Nav::all()->map->handle()->all();
            foreach ($navHandles as $nav) {
                $trees = \Statamic\Facades\Nav::findByHandle($nav)?->trees() ?? collect();
                foreach ($trees as $tree) {
                    $items->push([
                        'handle'     => $nav,
                        'site'       => $tree->site(),
                        'tree'       => $tree->tree(),
                        'updated_at' => now()->toIso8601String(),
                    ]);
                }
            }
        }

        if ($type === 'globals') {
            $setHandles = $handles ?: \Statamic\Facades\GlobalSet::all()->map->handle()->all();
            foreach ($setHandles as $set) {
                if (! $gs = \Statamic\Facades\GlobalSet::findByHandle($set)) continue;
                foreach ($gs->localizations() as $loc) {
                    $siteHandle = method_exists($loc->site(), 'handle') ? $loc->site()->handle() : (string)$loc->site();
                    $items->push([
                        'handle'     => $set,
                        'site'       => $siteHandle,
                        'data'       => $loc->data()->all(),
                        'updated_at' => optional($loc->model()?->updated_at)->toIso8601String(),
                    ]);
                }
            }
        }

        if ($type === 'assets') {
            $containerHandles = $handles ?: \Statamic\Facades\AssetContainer::all()->map->handle()->all();
            foreach ($containerHandles as $c) {
                if (! $container = \Statamic\Facades\AssetContainer::findByHandle($c)) continue;
                foreach ($container->assets() as $a) {
                    $items->push([
                        'container'  => $c,
                        'path'       => $a->path(),
                        'data'       => $a->data()->all(),
                        'updated_at' => optional($a->model()?->updated_at)->toIso8601String(),
                    ]);
                }
            }
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'type'        => $type,
            'handles'     => array_values($handles ?: []),
            'sites'       => array_values($sites ?: []),
            'since'       => $since,
            'items'       => array_values($items->all()),
        ];

        // HMAC signature so clients can't silently edit content.
        $secret = config('app.key');
        $toSign = json_encode(['type' => $payload['type'], 'handles' => $payload['handles'], 'sites' => $payload['sites'], 'since' => $payload['since'], 'items' => $payload['items']], JSON_UNESCAPED_UNICODE);
        $sig = base64_encode(hash_hmac('sha256', $toSign, $secret, true));
        $payload['__meta'] = ['sig' => $sig, 'algo' => 'HMAC-SHA256'];

        $filename = ($request->string('out')->toString() ?: ($type . '-export-' . now()->format('Ymd-His') . '.json'));

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Sync-Count' => (string) count($payload['items']),
            'X-Content-Sync-Immutable' => '1', // hint
        ]);
    }
}
