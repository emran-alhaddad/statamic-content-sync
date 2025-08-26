<?php

namespace EmranAlhaddad\ContentSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Facades\Nav;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\AssetContainer;
use Illuminate\Support\Facades\Cache;

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

        // after you build $diffs[] = [key,status,diff,current,incoming]
        $grouped = []; // [handle][site] => [items...]

        foreach ($diffs as $d) {
            // derive handle & site from key by type
            $handle = $site = 'default';
            switch ($type) {
                case 'collections':
                    [$handle, $site] = explode('/', $d['key'], 3); // collection/site/slug
                    break;
                case 'taxonomies':
                    [$handle, $site] = explode('/', $d['key'], 3); // taxonomy/site/slug
                    break;
                case 'navigation':
                    [$handle, $site] = explode('/', $d['key'], 2); // handle/site
                    break;
                case 'globals':
                    [$handle, $site] = explode('/', $d['key'], 2); // handle/site
                    break;
                case 'assets':
                    [$handle, $rest] = explode('/', $d['key'], 2);
                    $site = '-';
                    break;
            }
            $grouped[$handle] = $grouped[$handle] ?? [];
            $grouped[$handle][$site] = $grouped[$handle][$site] ?? [];
            $grouped[$handle][$site][] = $d;
        }

        return response()->json([
            'type'   => $type,
            'groups' => $grouped,        // nested structure for UI accordions
            'count'  => array_sum(array_map(fn($sites) => array_sum(array_map('count', $sites)), $grouped)),
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
        $request->validate([
            'strategy'    => 'nullable|in:manual,auto',
            'auto_action' => 'nullable|in:incoming,current,both',
            'decisions'   => 'array',
            'decisions.*.key'    => 'required_with:decisions|string',
            'decisions.*.action' => 'required_with:decisions|in:incoming,current,both',
            'token'       => 'nullable|string',
            // optional fallbacks if you do not use token/cached preview:
            'type'        => 'nullable|string|in:collections,taxonomies,navigation,globals,assets',
            'diffs'       => 'array',
            'groups'      => 'array',
        ]);

        $strategy   = $request->string('strategy')->toString() ?: 'manual';
        $autoAction = $request->string('auto_action')->toString() ?: 'incoming';

        // --- Load preview context (prefer token; else groups/diffs payload) ---
        $context = null;
        if ($tok = $request->string('token')->toString()) {
            $context = Cache::get("content-sync:preview:{$tok}");
        }
        if (! $context) {
            // accept grouped or flat diffs directly
            $type = $request->string('type')->toString();
            if (! $type) {
                return response()->json(['error' => 'Missing preview token or type+diffs/groups payload.'], 422);
            }
            $changes = [];
            if ($groups = $request->input('groups')) {
                foreach ($groups as $handle => $sites) {
                    foreach ($sites as $site => $items) {
                        foreach ($items as $it) {
                            $changes[] = $it;
                        }
                    }
                }
            } elseif ($diffs = $request->input('diffs')) {
                $changes = $diffs;
            }
            $context = ['type' => $type, 'changes' => $changes];
        }

        if (! $context || empty($context['type'])) {
            return response()->json(['error' => 'Preview context not found or expired. Re-run preview.'], 410);
        }

        $type    = $context['type'];
        $changes = $context['changes'] ?? [];

        // index by key for quick lookup
        $byKey = [];
        foreach ($changes as $c) {
            $byKey[$c['key']] = $c;
        }

        // --- Build the decision list ---
        if ($strategy === 'auto') {
            $decisions = $this->buildDecisionsForAllChanged($changes, $autoAction);
        } else {
            $decisions = $request->input('decisions', []);
            // filter to only keys that actually changed
            $decisions = array_values(array_filter($decisions, fn($d) => isset($byKey[$d['key']])));
        }

        // deep merge helper (incoming wins on conflict)
        $merge = function ($a, $b) use (&$merge) {
            if (is_array($a) && is_array($b)) {
                $out = $a;
                foreach ($b as $k => $v) {
                    $out[$k] = array_key_exists($k, $a) ? $merge($a[$k], $v) : $v;
                }
                return $out;
            }
            return $b !== null ? $b : $a;
        };

        $results = [
            'updated' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors'  => 0,
            'details' => ['updated' => [], 'created' => [], 'skipped' => [], 'errors' => []],
        ];

        // parseKey by type
        $parseKey = function (string $type, string $key): array {
            switch ($type) {
                case 'collections':
                case 'taxonomies':
                    // e.g. "news/english/some-slug"
                    $parts = explode('/', $key, 3);
                    return [$parts[0] ?? '', $parts[1] ?? '', $parts[2] ?? '']; // [handle, site, slug]
                case 'navigation':
                case 'globals':
                    // e.g. "main/english" or "globals_handle/english"
                    $parts = explode('/', $key, 2);
                    return [$parts[0] ?? '', $parts[1] ?? ''];
                case 'assets':
                    // e.g. "assets_container/path/to/file.jpg"
                    $firstSlash = strpos($key, '/');
                    return [
                        $firstSlash === false ? $key : substr($key, 0, $firstSlash),
                        $firstSlash === false ? ''   : substr($key, $firstSlash + 1)
                    ];
            }
            return [];
        };

        foreach ($decisions as $dec) {
            $key     = (string) $dec['key'];
            $action  = (string) $dec['action']; // incoming|current|both
            $change  = $byKey[$key] ?? null;

            if (! $change) {
                $results['skipped']++;
                $results['details']['skipped'][] = ['key' => $key, 'reason' => 'Not in preview set'];
                continue;
            }

            $current  = $change['current']  ?? [];
            $incoming = $change['incoming'] ?? [];
            $final    = $action === 'incoming' ? $incoming
                : ($action === 'current' ? $current : $merge($current, $incoming));

            try {
                switch ($type) {
                    case 'collections': {
                            [$collection, $site, $slug] = $parseKey($type, $key);
                            if (! $collection || ! $site || ! $slug) {
                                throw new \RuntimeException('Invalid entry key.');
                            }

                            $entry = Entry::query()
                                ->where('collection', $collection)
                                ->where('site', $site)
                                ->where('slug', $slug)
                                ->first();

                            if ($entry) {
                                $entry->data($final['data'] ?? []);
                                if (array_key_exists('published', $final)) {
                                    $entry->published((bool) $final['published']);
                                }
                                $entry->save();
                                $results['updated']++;
                                $results['details']['updated'][] = $key;
                            } else {
                                // Create
                                $entry = Entry::make()
                                    ->collection($collection)
                                    ->site($site)
                                    ->slug($slug)
                                    ->data($final['data'] ?? []);
                                if (array_key_exists('published', $final)) {
                                    $entry->published((bool) $final['published']);
                                }
                                $entry->save();
                                $results['created']++;
                                $results['details']['created'][] = $key;
                            }
                            break;
                        }

                    case 'taxonomies': {
                            [$taxonomy, $site, $slug] = $parseKey($type, $key);
                            if (! $taxonomy || ! $site || ! $slug) {
                                throw new \RuntimeException('Invalid term key.');
                            }

                            $term = Term::query()
                                ->where('taxonomy', $taxonomy)
                                ->where('site', $site)
                                ->where('slug', $slug)
                                ->first();

                            if ($term) {
                                $term->data($final['data'] ?? []);
                                $term->save();
                                $results['updated']++;
                                $results['details']['updated'][] = $key;
                            } else {
                                // Create localized term
                                $base = Term::make()->taxonomy($taxonomy)->slug($slug);
                                $loc  = $base->in($site);
                                $loc->data($final['data'] ?? []);
                                $loc->save();
                                $results['created']++;
                                $results['details']['created'][] = $key;
                            }
                            break;
                        }

                    case 'navigation': {
                            [$handle, $site] = $parseKey($type, $key);
                            if (! $handle || ! $site) {
                                throw new \RuntimeException('Invalid nav key.');
                            }

                            $nav = Nav::findByHandle($handle);
                            if (! $nav) {
                                throw new \RuntimeException("Nav '{$handle}' not found.");
                            }

                            $tree = $nav->in($site);
                            if (! $tree) {
                                // make a new tree for this site
                                $tree = $nav->makeTree($site);
                            }
                            $tree->tree($final['tree'] ?? []);
                            $tree->save();
                            // Treat as update (create tree counts as created if you want)
                            $results['updated']++;
                            $results['details']['updated'][] = $key;
                            break;
                        }

                    case 'globals': {
                            [$handle, $site] = $parseKey($type, $key);
                            if (! $handle || ! $site) {
                                throw new \RuntimeException('Invalid globals key.');
                            }
                            $set = GlobalSet::findByHandle($handle);
                            if (! $set) {
                                throw new \RuntimeException("Global set '{$handle}' not found.");
                            }
                            $loc = $set->in($site);
                            $existsBefore = (bool) $loc;
                            if (! $loc) $loc = $set->makeLocalization($site);

                            $loc->data($final['data'] ?? []);
                            $loc->save();

                            $results[$existsBefore ? 'updated' : 'created']++;
                            $results['details'][$existsBefore ? 'updated' : 'created'][] = $key;
                            break;
                        }

                    case 'assets': {
                            [$containerHandle, $path] = $parseKey($type, $key);
                            if (! $containerHandle || ! $path) {
                                throw new \RuntimeException('Invalid asset key.');
                            }
                            $container = AssetContainer::findByHandle($containerHandle);
                            if (! $container) {
                                throw new \RuntimeException("Asset container '{$containerHandle}' not found.");
                            }
                            $asset = $container->asset($path);
                            if (! $asset) {
                                // we only manage metadata; if file doesn't exist, skip
                                $results['skipped']++;
                                $results['details']['skipped'][] = ['key' => $key, 'reason' => 'Asset file not found'];
                                break;
                            }
                            $asset->data($final['data'] ?? []);
                            $asset->save();
                            $results['updated']++;
                            $results['details']['updated'][] = $key;
                            break;
                        }
                }
            } catch (\Throwable $e) {
                $results['errors']++;
                $results['details']['errors'][] = ['key' => $key, 'error' => $e->getMessage()];
            }
        }

        // Optional: write a report file for audit
        try {
            $report = [
                'at'      => now()->toIso8601String(),
                'type'    => $type,
                'strategy' => $strategy,
                'results' => $results,
            ];
            Storage::disk(config('content-sync.disk', 'local'))
                ->put('sync/import-report-' . now()->format('Ymd-His') . '.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // ignore report failures
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Build a decisions array for every changed item with a single action.
     *
     * @param  array|mixed  $changes  Either a flat array of change items or grouped as [handle=>[site=>[items...]]]
     * @param  string       $action   incoming|current|both
     * @return array<int, array{key:string, action:string}>
     */
    protected function buildDecisionsForAllChanged($changes, string $action): array
    {
        $action = in_array($action, ['incoming', 'current', 'both'], true) ? $action : 'incoming';

        // If grouped structure, flatten it.
        if (is_array($changes) && isset(array_values($changes)[0]) && is_array(array_values($changes)[0])) {
            // Heuristically detect grouped format: ['handle' => ['site' => [items...]]]
            $flat = [];
            foreach ($changes as $sites) {
                if (!is_array($sites)) continue;
                foreach ($sites as $items) {
                    if (!is_array($items)) continue;
                    foreach ($items as $it) {
                        if (!empty($it['key'])) $flat[] = $it;
                    }
                }
            }
            $changes = $flat;
        }

        $out = [];
        if (is_array($changes)) {
            foreach ($changes as $it) {
                if (!empty($it['key'])) {
                    $out[] = ['key' => $it['key'], 'action' => $action];
                }
            }
        }
        return $out;
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
