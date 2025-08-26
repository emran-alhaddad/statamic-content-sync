<?php

namespace EmranAlhaddad\ContentSync\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use Statamic\Facades\Entry;

class ImportController extends Controller
{
    /**
     * Upload the JSON export and return grouped preview.
     * Shape returned:
     * {
     *   type: "collections",
     *   groups: { "<handle>": { "<site>": [ { key, status, current, incoming }, ... ] } }
     * }
     * Also stores a flat map in session for commit:  session('content-sync.preview.flat')
     */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        $raw = @file_get_contents($request->file('file')->getRealPath());
        $json = json_decode($raw, true);

        if (!is_array($json) || !isset($json['type']) || !isset($json['items']) || !is_array($json['items'])) {
            return response()->json(['message' => 'Invalid import file.'], 422);
        }

        $type  = (string) $json['type'];
        $items = $json['items'];

        $groups = [];
        $flat   = [];

        if ($type === 'collections') {
            foreach ($items as $inc) {
                $collection = $inc['collection'] ?? null;
                $site       = $inc['site'] ?? null;
                $slug       = $inc['slug'] ?? null;
                if (!$collection || !$site || !$slug) {
                    continue;
                }

                $entry = null;
                if (!empty($inc['uuid'])) {
                    $entry = Entry::find($inc['uuid']);
                }
                if (!$entry) {
                    $entry = Entry::query()
                        ->where('collection', $collection)
                        ->where('site', $site)
                        ->where('slug', $slug)
                        ->first();
                }

                $curr = $entry ? [
                    'uuid'       => $entry->id(),
                    'collection' => $entry->collectionHandle(),
                    'site'       => $entry->site(),
                    'slug'       => $entry->slug(),
                    'published'  => (bool) $entry->published(),
                    'updated_at' => optional($entry->model()?->updated_at)->toIso8601String(),
                    'data'       => $entry->data()->all(),
                ] : null;

                $key    = "{$collection}/{$site}/{$slug}";
                $status = $curr ? 'update' : 'create';

                $row = [
                    'key'      => $key,
                    'status'   => $status,
                    'handle'   => $collection,
                    'site'     => $site,
                    'current'  => $curr,
                    'incoming' => $inc,
                ];

                $groups[$collection][$site][] = $row;
                $flat[$key] = $row;
            }
        } else {
            // For other types we just stage incoming as-is (commit currently no-ops for safety).
            foreach ($items as $inc) {
                $handle = $inc['collection'] ?? $inc['taxonomy'] ?? $inc['handle'] ?? 'unknown';
                $site   = $inc['site'] ?? 'default';
                $slug   = $inc['slug'] ?? ($inc['path'] ?? 'item');

                $key  = "{$handle}/{$site}/{$slug}";
                $row = [
                    'key'      => $key,
                    'status'   => 'update',
                    'handle'   => $handle,
                    'site'     => $site,
                    'current'  => null,
                    'incoming' => $inc,
                ];
                $groups[$handle][$site][] = $row;
                $flat[$key] = $row;
            }
        }

        Session::put('content-sync.preview', [
            'type' => $type,
            'flat' => $flat,
        ]);

        return response()->json([
            'type'   => $type,
            'groups' => $groups,
        ]);
    }

    /**
     * Commit staged changes.
     * Payload (manual): { type, strategy:"manual", decisions:[{key, action}] }
     * Payload (auto):   { type, strategy:"auto",   auto_action:"incoming|current|both" }
     */
    public function commit(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:collections,taxonomies,navigation,globals,assets',
            'strategy'    => 'required|in:manual,auto',
            'auto_action' => 'nullable|in:incoming,current,both',
            'decisions'   => 'nullable|array',
        ]);

        $staged = Session::get('content-sync.preview');
        if (!$staged || ($staged['type'] ?? null) !== $data['type']) {
            return response()->json(['ok' => false, 'message' => 'No staged preview. Upload the file again.'], 422);
        }
        $flat = $staged['flat'] ?? [];

        $results = ['updated' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];

        // Build decision map
        $decisions = [];
        if ($data['strategy'] === 'manual') {
            foreach ($data['decisions'] ?? [] as $row) {
                $k = $row['key'] ?? null;
                $a = $row['action'] ?? null;
                if ($k && in_array($a, ['incoming', 'current', 'both'], true)) {
                    $decisions[$k] = $a;
                }
            }
        } else {
            $apply = $data['auto_action'] ?? 'incoming';
            foreach ($flat as $k => $row) {
                if (!$this->deepEqual($this->relevant($row['current'], $data['type']), $this->relevant($row['incoming'], $data['type']))) {
                    $decisions[$k] = $apply;
                }
            }
        }

        // Execute decisions
        if ($data['type'] === 'collections') {
            foreach ($decisions as $key => $action) {
                try {
                    $row = $flat[$key] ?? null;
                    if (!$row) {
                        $results['skipped']++;
                        continue;
                    }

                    $curr = $row['current'];
                    $inc  = $row['incoming'];

                    $final = $this->mergeFinal($curr, $inc, $action);

                    // Resolve entry
                    $entry = null;
                    if (!empty($final['uuid'])) $entry = Entry::find($final['uuid']);
                    if (!$entry) {
                        $entry = Entry::query()
                            ->when(!empty($final['collection']), fn($q) => $q->where('collection', $final['collection']))
                            ->when(!empty($final['site']), fn($q) => $q->where('site', $final['site']))
                            ->when(!empty($final['slug']), fn($q) => $q->where('slug', $final['slug']))
                            ->first();
                    }

                    if (!$entry) {
                        if ($action === 'current') {
                            $results['skipped']++;
                            continue;
                        }
                        // create
                        $entry = Entry::make()
                            ->collection($final['collection'])
                            ->site($final['site'])
                            ->slug($final['slug'])
                            ->published((bool)($final['published'] ?? true))
                            ->data((array)($final['data'] ?? []));
                        $entry->save();
                        $results['created']++;
                        continue;
                    }

                    // update
                    $nextPublished = (bool)($final['published'] ?? $entry->published());
                    $nextData      = (array)($final['data'] ?? []);
                    if ($entry->published() === $nextPublished && $entry->data()->toArray() == $nextData) {
                        $results['skipped']++;
                        continue;
                    }
                    $entry->published($nextPublished);
                    $entry->data($nextData);
                    $entry->save();
                    $results['updated']++;
                } catch (\Throwable $e) {
                    report($e);
                    $results['errors']++;
                }
            }
        } else {
            // Not destructive for other types yet (safe default).
            $results['skipped'] = count($decisions);
        }

        return response()->json(['ok' => true, 'results' => $results]);
    }

    /* ===================== helpers ===================== */

    private function relevant($obj, string $type)
    {
        $obj = $obj ?? [];
        switch ($type) {
            case 'collections':
                return [
                    'data'      => Arr::get($obj, 'data', []),
                    'published' => array_key_exists('published', $obj) ? (bool)$obj['published'] : null,
                ];
            case 'taxonomies':
            case 'globals':
            case 'assets':
                return ['data' => Arr::get($obj, 'data', [])];
            case 'navigation':
                return ['tree' => Arr::get($obj, 'tree', [])];
            default:
                return $obj;
        }
    }

    private function deepEqual($a, $b): bool
    {
        return $this->stableJson($a) === $this->stableJson($b);
    }

    private function stableJson($value): string
    {
        $canon = function ($v) use (&$canon) {
            if (is_array($v)) {
                // distinguish assoc vs list
                $isAssoc = array_keys($v) !== range(0, count($v) - 1);
                if ($isAssoc) {
                    ksort($v);
                    foreach ($v as $k => $vv) $v[$k] = $canon($vv);
                    return $v;
                }
                return array_map($canon, $v);
            }
            return $v;
        };
        return json_encode($canon($value));
    }

    private function mergeFinal($current, $incoming, string $action)
    {
        if ($action === 'current')  return $current;
        if ($action === 'incoming') return $incoming;

        $merge = function ($a, $b) use (&$merge) {
            // list-arrays: take incoming wholesale
            if (is_array($a) && is_array($b)) {
                $aIsList = array_keys($a) === range(0, count($a) - 1);
                $bIsList = array_keys($b) === range(0, count($b) - 1);
                if ($aIsList && $bIsList) return $b;
                // assoc merge
                $out = $a ?? [];
                foreach ($b as $k => $v) {
                    $out[$k] = array_key_exists($k, $out) ? $merge($out[$k], $v) : $v;
                }
                return $out;
            }
            return $b ?? $a;
        };

        return $merge($current ?? [], $incoming ?? []);
    }
}
