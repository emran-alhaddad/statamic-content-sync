<?php

namespace EmranAlhaddad\ContentSync\UseCases;

use EmranAlhaddad\ContentSync\Contracts\ImportReader;
use EmranAlhaddad\ContentSync\DTO\ImportOptions;
use EmranAlhaddad\ContentSync\Services\DiffService;
use EmranAlhaddad\ContentSync\Services\Importers\AssetsImporter;
use EmranAlhaddad\ContentSync\Services\Importers\CollectionsImporter;
use EmranAlhaddad\ContentSync\Services\Importers\GlobalsImporter;
use EmranAlhaddad\ContentSync\Services\Importers\NavigationImporter;
use EmranAlhaddad\ContentSync\Services\Importers\TaxonomiesImporter;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Term;

class ImportEntriesUseCase
{
    public function __construct(private ImportReader $reader, private DiffService $diff) {}

    /** Build diffs from uploaded JSON file contents. */
    public function previewFile(string $pathOrName): array
    {
        $payload = $this->reader->read($pathOrName);
        $type = $payload['type'] ?? null;
        $items = $payload['items'] ?? [];
        $diffs = [];

        foreach ($items as $inc) {
            $diffs[] = match ($type) {
                'collections' => $this->diffEntry($inc),
                'taxonomies'  => $this->diffTerm($inc),
                'navigation'  => $this->diffNav($inc),
                'globals'     => $this->diffGlobal($inc),
                'assets'      => $this->diffAsset($inc),
                default       => ['key' => 'unknown', 'status' => 'unknown', 'diff' => []],
            };
        }

        return ['ok' => true, 'type' => $type, 'diffs' => $diffs];
    }

    /** Apply decisions built by the UI */
    public function commit(ImportOptions $opt): array
    {
        $res = ['updated' => 0, 'created' => 0, 'skipped' => 0];

        $importer = match ($opt->type) {
            'collections' => app(CollectionsImporter::class),
            'taxonomies'  => app(TaxonomiesImporter::class),
            'navigation'  => app(NavigationImporter::class),
            'globals'     => app(GlobalsImporter::class),
            'assets'      => app(AssetsImporter::class),
        };

        foreach ($opt->decisions as $d) {
            $res = $importer->apply($d, $res);
        }

        \Artisan::call('statamic:stache:refresh');
        return ['ok' => true, 'results' => $res];
    }

    // ----- diff helpers -----
    private function diffEntry(array $inc): array
    {
        $live = Entry::query()->where('collection', $inc['collection'])->where('site', $inc['site'])->where('slug', $inc['slug'])->first();
        $key = $inc['collection'] . '/' . $inc['site'] . '/' . $inc['slug'];
        if (!$live) return ['key' => $key, 'status' => 'create', 'incoming' => $inc['data'], 'current' => null, 'diff' => $this->diff->diffArrays([], $inc['data'])];
        $cur = $live->data()->all();
        return ['key' => $key, 'status' => 'update', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diff->diffArrays($cur, $inc['data'])];
    }

    private function diffTerm(array $inc): array
    {
        $live = Term::query()->where('taxonomy', $inc['taxonomy'])->where('site', $inc['site'])->where('slug', $inc['slug'])->first();
        $key = $inc['taxonomy'] . '/' . $inc['site'] . '/' . $inc['slug'];
        if (!$live) return ['key' => $key, 'status' => 'create', 'incoming' => $inc['data'], 'current' => null, 'diff' => $this->diff->diffArrays([], $inc['data'])];
        $cur = $live->data()->all();
        return ['key' => $key, 'status' => 'update', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diff->diffArrays($cur, $inc['data'])];
    }

    private function diffNav(array $inc): array
    {
        $key = $inc['handle'] . '/' . $inc['site'];
        $tree = Nav::findByHandle($inc['handle'])?->in($inc['site']);
        $cur = $tree?->tree() ?: [];
        return ['key' => $key, 'status' => $tree ? 'update' : 'create', 'incoming' => $inc['tree'], 'current' => $cur, 'diff' => $this->diff->diffArrays($cur, $inc['tree'])];
    }

    private function diffGlobal(array $inc): array
    {
        $key = $inc['handle'] . '/' . $inc['site'];
        $set = GlobalSet::findByHandle($inc['handle']);
        $cur = $set?->in($inc['site'])?->data()->all() ?: [];
        return ['key' => $key, 'status' => $set ? 'update' : 'create', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diff->diffArrays($cur, $inc['data'])];
    }

    private function diffAsset(array $inc): array
    {
        $key = $inc['container'] . '/' . $inc['path'];
        $container = AssetContainer::findByHandle($inc['container']);
        $asset = $container?->asset($inc['path']);
        $cur = $asset?->data()->all() ?: [];
        return ['key' => $key, 'status' => $asset ? 'update' : 'create', 'incoming' => $inc['data'], 'current' => $cur, 'diff' => $this->diff->diffArrays($cur, $inc['data'])];
    }
}
