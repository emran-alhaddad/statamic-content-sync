<?php

namespace EmranAlhaddad\ContentSync\Console;

use EmranAlhaddad\ContentSync\DTO\ImportOptions;
use EmranAlhaddad\ContentSync\UseCases\ImportEntriesUseCase;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'content-sync:import
        {file : JSON path (absolute, project-relative, or under configured folder)}
        {--apply : Commit all incoming changes (no UI)}';

    protected $description = 'Import content (headless)';

    public function handle(): int
    {
        $use = app(ImportEntriesUseCase::class);
        $preview = $use->previewFile((string)$this->argument('file'));
        $this->line('Type: ' . $preview['type'] . ' | Items: ' . count($preview['diffs']));

        if (!$this->option('apply')) {
            $this->info('Preview only. Use --apply to commit all as incoming.');
            return self::SUCCESS;
        }

        $decisions = array_map(fn($d) => ['key' => $d['key'], 'action' => 'incoming', 'incoming' => $d['incoming']], $preview['diffs']);
        $res = $use->commit(new ImportOptions(type: $preview['type'], decisions: $decisions));
        $this->info("Updated {$res['results']['updated']}, Created {$res['results']['created']}, Skipped {$res['results']['skipped']}");
        return self::SUCCESS;
    }
}
