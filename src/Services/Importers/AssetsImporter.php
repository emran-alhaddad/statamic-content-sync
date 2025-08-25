<?php

namespace EmranAlhaddad\ContentSync\Services\Importers;

use EmranAlhaddad\ContentSync\Contracts\ImporterInterface;
use Statamic\Facades\AssetContainer;

class AssetsImporter implements ImporterInterface
{
    public function apply(array $d, array $res): array
    {
        [$containerHandle, $path] = explode('/', $d['key'], 2) + [null, null];
        if (($d['action'] ?? 'current') !== 'incoming') {
            $res['skipped']++;
            return $res;
        }
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
