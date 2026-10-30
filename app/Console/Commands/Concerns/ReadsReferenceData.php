<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait ReadsReferenceData
{
    /**
     * Read a JSON list of rows from database/data/{file}.
     *
     * @return list<array<string, mixed>>
     */
    protected function readReferenceData(string $file): array
    {
        $path = database_path("data/{$file}");

        if (! File::exists($path)) {
            throw new RuntimeException("Reference data file [{$path}] not found.");
        }

        $rows = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException("Reference data file [{$path}] must contain a JSON list.");
        }

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }
}
