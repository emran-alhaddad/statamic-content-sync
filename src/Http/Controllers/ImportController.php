<?php

namespace EmranAlhaddad\ContentSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Facades\Nav;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\AssetContainer;

class ImportController extends Controller
{
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt,application/json']);
        $json = $request->file('file')->get();
        $payload = json_decode($json, true);
        if (!is_array($payload)) return response()->json(['ok' => false, 'error' => 'Invalid JSON'], 422);

        $type = $payload['type'] ?? null;
        $items = $payload['items'] ?? [];

        $diffs = [];
        foreach ($items as $it) {
            $diffs[] = $this->diffOne($type, $it);
        }

        return response()->json(['ok' => true, 'type' => $type, 'diffs' => $diffs]);
    }

    public function commit(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:collections,taxonomies,navigation,globals,assets',
            'decisions' => 'required|array',
        ]);

        $type = $validated['type'];
        $decisions = $validated['decisions']; // [{key, action:incoming|current, fields?:{}}]

        $results = ['updated' => 0, 'created' => 0, 'skipped' => 0];

        foreach ($decisions as $d) {
            $action = $d['action'] ?? 'current';
            if ($action === 'current') {
                $results['skipped']++;
                continue;
            }

            switch ($type) {
                case 'collections':
                    $results = $this->applyCollection($d, $results);
                    break;
                case 'taxonomies':
                    $results = $this->applyTerm($d, $results);
                    break;
                case 'navigation':
                    $results = $this->applyNav($d, $results);
                    break;
                case 'globals':
                    $results = $this->applyGlobal($d, $results);
                    break;
                case 'assets':
                    $results = $this->applyAsset($d, $results);
                    break;
            }
        }

        // Refresh caches (light)
        \Artisan::call('statamic:stache:refresh');

        return response()->json(['ok' => true, 'results' => $results]);
    }

    private function diffOne(string $type, array $incoming): array
    {
        return match ($type) {
            'collections' => $this->diffEntry($incoming),
            'taxonomies'  => $this->diffTerm($incoming),
            'navigation'  => $this->diffNav($incoming),
            'globals'     => $this->diffGlobal($incoming),
            'assets'      => $this->diffAsset($incoming),
            default       => ['key' => 'unknown', 'status' => 'unknown', 'diff' => []]
        };
    }

    private function diffEntry(array $inc): array
    {
        $live = Entry::query()->where('collection', $inc['collection'])->where('site', $inc['site'])->where('slug', $inc['slug'])->first();
        $key = $inc['collection'] . '/' . $inc['site'] . '/' . $inc['slug'];
        if (!$live) {
            return ['key' => $key, 'status' => 'create', 'incoming' => $inc['data'], 'current' => null, 'diff' => $this->diffArrays([], $inc['data'])];
        }
        $cur = $live->data()->all();
        return ['key' => $key, 'status' => 'update', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diffArrays($cur, $inc['data'])];
    }

    private function diffTerm(array $inc): array
    {
        $live = Term::query()->where('taxonomy', $inc['taxonomy'])->where('site', $inc['site'])->where('slug', $inc['slug'])->first();
        $key = $inc['taxonomy'] . '/' . $inc['site'] . '/' . $inc['slug'];
        if (!$live) return ['key' => $key, 'status' => 'create', 'incoming' => $inc['data'], 'current' => null, 'diff' => $this->diffArrays([], $inc['data'])];
        $cur = $live->data()->all();
        return ['key' => $key, 'status' => 'update', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diffArrays($cur, $inc['data'])];
    }

    private function diffNav(array $inc): array
    {
        $key = $inc['handle'] . '/' . $inc['site'];
        $tree = Nav::findByHandle($inc['handle'])?->in($inc['site']);
        $cur = $tree?->tree() ?: [];
        return ['key' => $key, 'status' => $tree ? 'update' : 'create', 'incoming' => $inc['tree'], 'current' => $cur, 'diff' => $this->diffArrays($cur, $inc['tree'])];
    }

    private function diffGlobal(array $inc): array
    {
        $key = $inc['handle'] . '/' . $inc['site'];
        $set = GlobalSet::findByHandle($inc['handle']);
        $cur = $set?->in($inc['site'])?->data()->all() ?: [];
        return ['key' => $key, 'status' => $set ? 'update' : 'create', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diffArrays($cur, $inc['data'])];
    }

    private function diffAsset(array $inc): array
    {
        $key = $inc['container'] . '/' . $inc['path'];
        $container = AssetContainer::findByHandle($inc['container']);
        $asset = $container?->asset($inc['path']);
        $cur = $asset?->data()->all() ?: [];
        return ['key' => $key, 'status' => $asset ? 'update' : 'create', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diffArrays($cur, $inc['data'])];
    }

    private function diffArrays(array $cur, array $inc): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($cur), array_keys($inc))));
        $diff = [];
        foreach ($keys as $k) {
            $cv = $cur[$k] ?? null;
            $iv = $inc[$k] ?? null;
            if ($cv === $iv) continue;
            $diff[$k] = ['current' => $cv, 'incoming' => $iv];
        }
        return $diff;
    }

    private function applyCollection(array $d, array $res): array
    {
        $parts = explode('/', $d['key'], 3); // collection/site/slug
        [$collection, $site, $slug] = $parts + [null, null, null];
        $incoming = $d['incoming'] ?? [];
        $live = Entry::query()->where('collection', $collection)->where('site', $site)->where('slug', $slug)->first();
        if (!$live) {
            $entry = Entry::make()->collection($collection)->site($site)->slug($slug)->data($incoming)->published(true);
            $entry->save();
            $res['created']++;
            return $res;
        }
        $live->data($incoming);
        $live->save();
        $res['updated']++;
        return $res;
    }

    private function applyTerm(array $d, array $res): array
    {
        $parts = explode('/', $d['key'], 3); // taxonomy/site/slug
        [$taxonomy, $site, $slug] = $parts + [null, null, null];
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

    private function applyNav(array $d, array $res): array
    {
        [$handle, $site] = explode('/', $d['key'], 2) + [null, null];
        $incoming = $d['incoming'] ?? [];
        $nav = Nav::findByHandle($handle);
        $tree = $nav?->in($site) ?: $nav?->makeTree($site);
        $tree->tree($incoming);
        $tree->save();
        $res['updated']++;
        return $res;
    }

    private function applyGlobal(array $d, array $res): array
    {
        [$handle, $site] = explode('/', $d['key'], 2) + [null, null];
        $incoming = $d['incoming'] ?? [];
        $set = GlobalSet::findByHandle($handle);
        $loc = $set?->in($site);
        $loc->data($incoming);
        $loc->save();
        $res['updated']++;
        return $res;
    }

    private function applyAsset(array $d, array $res): array
    {
        [$containerHandle, $path] = explode('/', $d['key'], 2) + [null, null];
        $incoming = $d['incoming'] ?? [];
        $container = AssetContainer::findByHandle($containerHandle);
        $asset = $container?->asset($path);
        if (!$asset) {
            $res['skipped']++;
            return $res;
        }
        $asset->data($incoming);
        $asset->save();
        $res['updated']++;
        return $res;
    }
}
