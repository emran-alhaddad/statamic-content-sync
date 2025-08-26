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
        $request->validate(['file' => 'required|file|mimetypes:application/json,text/plain']);
        $json = $request->file('file')->get();
        $data = json_decode($json, true);

        if (!is_array($data)) abort(422, 'Invalid JSON.');

        // Verify HMAC (tamper-evident)
        $meta = $data['__meta'] ?? [];
        $sig  = $meta['sig'] ?? '';
        $secret = config('app.key');
        $toSign = json_encode([
            'type'    => $data['type'] ?? null,
            'handles' => $data['handles'] ?? [],
            'sites'   => $data['sites'] ?? [],
            'since'   => $data['since'] ?? null,
            'items'   => $data['items'] ?? [],
        ], JSON_UNESCAPED_UNICODE);
        $calc = base64_encode(hash_hmac('sha256', $toSign, $secret, true));
        if (!$sig || !hash_equals($calc, $sig)) {
            return response()->json(['error' => 'File signature invalid. The file appears to be modified.'], 422);
        }

        $type  = $data['type'] ?? 'collections';
        $items = $data['items'] ?? [];

        $diffs = [];

        foreach ($items as $it) {
            // derive a unique key per type so the UI can display it
            $key = match ($type) {
                'collections' => "{$it['collection']}/{$it['site']}/{$it['slug']}",
                'taxonomies'  => "{$it['taxonomy']}/{$it['site']}/{$it['slug']}",
                'navigation'  => "{$it['handle']}/{$it['site']}",
                'globals'     => "{$it['handle']}/{$it['site']}",
                'assets'      => "{$it['container']}/{$it['path']}",
                default       => 'item'
            };

            // load current
            [$current, $incoming] = $this->resolveCurrentAndIncoming($type, $it);

            // compute field-level diff
            $diff = $this->diffAssocDeep($current, $incoming);

            if (!empty($diff)) {
                $status = $this->summarizeStatus($diff); // 'create'|'update'|'delete'
                $diffs[] = [
                    'key'      => $key,
                    'status'   => $status,
                    'diff'     => $diff,     // path => {status, current, incoming}
                    'current'  => $current,
                    'incoming' => $incoming,
                ];
            }
        }

        return response()->json([
            'type'  => $type,
            'diffs' => array_values($diffs), // only affected items
        ]);
    }

    protected function resolveCurrentAndIncoming(string $type, array $incomingItem): array
    {
        switch ($type) {
            case 'collections':
                $e = \Statamic\Facades\Entry::query()
                    ->where('collection', $incomingItem['collection'])
                    ->where('site', $incomingItem['site'])
                    ->where('slug', $incomingItem['slug'])
                    ->first();
                $current = $e ? [
                    'uuid'       => $e->id(),
                    'collection' => $e->collectionHandle(),
                    'site'       => $e->site(),
                    'slug'       => $e->slug(),
                    'published'  => (bool)$e->published(),
                    'data'       => $e->data()->all(),
                ] : [];
                return [$current, [
                    'uuid'       => $incomingItem['uuid'] ?? null,
                    'collection' => $incomingItem['collection'],
                    'site'       => $incomingItem['site'],
                    'slug'       => $incomingItem['slug'],
                    'published'  => (bool)($incomingItem['published'] ?? false),
                    'data'       => $incomingItem['data'] ?? [],
                ]];

            case 'taxonomies':
                $t = \Statamic\Facades\Term::query()
                    ->where('taxonomy', $incomingItem['taxonomy'])
                    ->where('site', $incomingItem['site'])
                    ->where('slug', $incomingItem['slug'])
                    ->first();
                $current = $t ? ['data' => $t->data()->all()] : [];
                return [$current, ['data' => $incomingItem['data'] ?? []]];

            case 'navigation':
                $tree = \Statamic\Facades\Nav::findByHandle($incomingItem['handle'])?->in($incomingItem['site']);
                $current = $tree ? ['tree' => $tree->tree()] : [];
                return [$current, ['tree' => $incomingItem['tree'] ?? []]];

            case 'globals':
                $gs = \Statamic\Facades\GlobalSet::findByHandle($incomingItem['handle']);
                $loc = $gs?->in($incomingItem['site']);
                $current = $loc ? ['data' => $loc->data()->all()] : [];
                return [$current, ['data' => $incomingItem['data'] ?? []]];

            case 'assets':
                $ac = \Statamic\Facades\AssetContainer::findByHandle($incomingItem['container']);
                $a  = $ac?->asset($incomingItem['path']);
                $current = $a ? ['data' => $a->data()->all()] : [];
                return [$current, ['data' => $incomingItem['data'] ?? []]];
        }

        return [[], $incomingItem];
    }

    /** Deep assoc diff, returns only changed paths. */
    protected function diffAssocDeep($cur, $inc, string $base = ''): array
    {
        $out = [];

        $keys = array_unique(array_merge(array_keys((array)$cur), array_keys((array)$inc)));
        foreach ($keys as $k) {
            $p = ltrim($base . '.' . $k, '.');
            $cv = $cur[$k] ?? null;
            $iv = $inc[$k] ?? null;

            $cObj = is_array($cv) || is_object($cv);
            $iObj = is_array($iv) || is_object($iv);

            if ($cObj || $iObj) {
                $nested = $this->diffAssocDeep((array)$cv, (array)$iv, $p);
                $out = $out + $nested;
                continue;
            }

            if ($cv === null && $iv !== null) {
                $out[$p] = ['status' => 'added', 'current' => $cv, 'incoming' => $iv];
            } elseif ($cv !== null && $iv === null) {
                $out[$p] = ['status' => 'removed', 'current' => $cv, 'incoming' => $iv];
            } elseif ($cv !== $iv) {
                $out[$p] = ['status' => 'changed', 'current' => $cv, 'incoming' => $iv];
            }
        }

        return $out;
    }

    protected function summarizeStatus(array $diff): string
    {
        $hasAdd = $hasDel = $hasChg = false;
        foreach ($diff as $d) {
            if ($d['status'] === 'added') $hasAdd = true;
            elseif ($d['status'] === 'removed') $hasDel = true;
            else $hasChg = true;
        }
        if ($hasAdd && !$hasDel && !$hasChg) return 'create';
        if ($hasDel && !$hasAdd && !$hasChg) return 'delete';
        return 'update';
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
