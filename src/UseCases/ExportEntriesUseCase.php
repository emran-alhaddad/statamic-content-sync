<?php

namespace EmranAlhaddad\ContentSync\UseCases;

use EmranAlhaddad\ContentSync\Contracts\ExportWriter;
use EmranAlhaddad\ContentSync\DTO\ExportOptions;
use EmranAlhaddad\ContentSync\DTO\ExportReport;
use EmranAlhaddad\ContentSync\Services\Exporters\AssetsExporter;
use EmranAlhaddad\ContentSync\Services\Exporters\CollectionsExporter;
use EmranAlhaddad\ContentSync\Services\Exporters\GlobalsExporter;
use EmranAlhaddad\ContentSync\Services\Exporters\NavigationExporter;
use EmranAlhaddad\ContentSync\Services\Exporters\TaxonomiesExporter;

class ExportEntriesUseCase
{
    public function __construct(private ExportWriter $writer) {}

    public function handle(ExportOptions $opt): ExportReport
    {
        $exporter = match ($opt->type) {
            'collections' => app(CollectionsExporter::class),
            'taxonomies'  => app(TaxonomiesExporter::class),
            'navigation'  => app(NavigationExporter::class),
            'globals'     => app(GlobalsExporter::class),
            'assets'      => app(AssetsExporter::class),
        };

        $payload = $exporter->export($opt->handles, $opt->sites, $opt->sinceIso);

        $relative = trim(config('content-sync.folder', 'sync'), '/') . '/' . $opt->out;
        $this->writer->write($relative, $payload);

        return new ExportReport(count($payload['items'] ?? []), $relative);
    }
}
