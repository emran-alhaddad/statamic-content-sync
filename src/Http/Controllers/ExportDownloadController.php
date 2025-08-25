<?php

namespace EmranAlhaddad\ContentSync\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDownloadController extends Controller
{
    public function download(string $file): StreamedResponse
    {
        $disk   = config('content-sync.disk', 'local');
        $folder = trim(config('content-sync.folder', 'sync'), '/');

        // prevent path traversal; only allow plain filenames
        $safe   = basename($file);
        $path   = "{$folder}/{$safe}";

        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $safe, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
