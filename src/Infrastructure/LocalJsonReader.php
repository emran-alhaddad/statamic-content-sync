<?php

namespace EmranAlhaddad\ContentSync\Infrastructure;

use EmranAlhaddad\ContentSync\Contracts\ImportReader;
use Illuminate\Support\Facades\Storage;

class LocalJsonReader implements ImportReader
{
    public function __construct(private string $disk = 'local', private string $folder = 'sync') {}

    public function read(string $pathOrName): array
    {
        if (str_starts_with($pathOrName, '/')) {
            if (!is_file($pathOrName)) throw new \RuntimeException("Import file not found: {$pathOrName}");
            return $this->decode(file_get_contents($pathOrName) ?: false, $pathOrName);
        }
        $project = base_path($pathOrName);
        if (is_file($project)) {
            return $this->decode(file_get_contents($project) ?: false, $project);
        }
        $relative = trim($this->folder, '/') . '/' . ltrim($pathOrName, '/');
        if (!Storage::disk($this->disk)->exists($relative)) {
            $root = Storage::disk($this->disk)->path($relative);
            throw new \RuntimeException("Import file not found: {$root}");
        }
        $json = Storage::disk($this->disk)->get($relative);
        return $this->decode($json, Storage::disk($this->disk)->path($relative));
    }

    private function decode(string|false $json, string $where): array
    {
        if ($json === false) throw new \RuntimeException("Failed reading JSON from {$where}");
        $data = json_decode($json, true);
        if (!is_array($data)) throw new \RuntimeException("Invalid JSON in {$where}");
        return $data;
    }
}
