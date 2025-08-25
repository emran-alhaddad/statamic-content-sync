<?php

namespace EmranAlhaddad\ContentSync\Console;

use EmranAlhaddad\ContentSync\DTO\ExportOptions;
use EmranAlhaddad\ContentSync\UseCases\ExportEntriesUseCase;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'content-sync:export
        {--type=collections : collections|taxonomies|navigation|globals|assets}
        {--handles= : CSV of handles}
        {--sites= : CSV of sites}
        {--since= : ISO8601 (optional)}
        {--out=export.json : Output filename under configured folder}';

    protected $description = 'Export content (headless, mirrors UI)';

    public function handle(): int
    {
        $handles = $this->csvToArray($this->option('handles'));
        $sites = $this->csvToArray($this->option('sites'));

        $opt = new ExportOptions(
            type: (string)$this->option('type'),
            handles: $handles,
            sites: $sites,
            sinceIso: $this->option('since') ?: null,
            out: (string)$this->option('out'),
        );

        $use = app(ExportEntriesUseCase::class);
        $report = $use->handle($opt);

        $this->info("Exported {$report->count} items → " . config('content-sync.disk', 'local') . ":{$report->outRelative}");
        return self::SUCCESS;
    }

    private function csvToArray(?string $csv): array
    {
        if (!$csv) return [];
        return array_values(array_filter(array_map('trim', explode(',', $csv)), fn($v) => $v !== ''));
    }
}
