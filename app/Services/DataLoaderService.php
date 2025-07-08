<?php

namespace App\Services;

class DataLoaderService
{
    public function load(string $filename): array
    {
        $path = storage_path("app/data/{$filename}");

        if (!file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);
        return json_last_error() === JSON_ERROR_NONE ? $data : [];
    }
}
