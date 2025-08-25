<?php

namespace EmranAlhaddad\ContentSync\Infrastructure;

use Closure;
use EmranAlhaddad\ContentSync\Contracts\EntryProvider;
use Illuminate\Support\Facades\DB;

class DbStatamicEntryProvider implements EntryProvider
{
    public function countCandidates(array $filters): int
    {
        return $this->base($filters)->count();
    }

    public function forEachCandidate(array $filters, Closure $callback): void
    {
        $chunk = (int)($filters['chunk'] ?? 500);
        $this->base($filters)
            ->select(['id', 'collection', 'site', 'slug', 'updated_at'])
            ->orderByDesc('updated_at')
            ->chunk($chunk, function ($rows) use ($callback) {
                foreach ($rows as $row) $callback($row);
            });
    }

    private function base(array $f)
    {
        return DB::table('entries')
            ->when(!empty($f['collections']), fn($q) => $q->whereIn('collection', $f['collections']))
            ->when(!empty($f['sites']), fn($q) => $q->whereIn('site', $f['sites']))
            ->when(!empty($f['since']), fn($q) => $q->where('updated_at', '>=', $f['since']));
    }
}
