<?php

namespace EmranAlhaddad\ContentSync\Infrastructure;

use EmranAlhaddad\ContentSync\Contracts\ExportWriter;
use Illuminate\Support\Facades\Storage;

class LocalJsonExportWriter implements ExportWriter
{
    public function __construct(private string $disk = 'local') {}

    public function write(string $relativePath, array $payload): void
    {
        $dir = dirname($relativePath);
        if ($dir !== '.' && !Storage::disk($this->disk)->exists($dir)) {
            Storage::disk($this->disk)->makeDirectory($dir);
        }
        Storage::disk($this->disk)->put(
            $relativePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
