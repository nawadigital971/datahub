<?php

namespace App\Queries\Imports;

use App\Models\ImportBatch;
use App\Queries\Sources\SourceNameMatcher;
use Illuminate\Support\Facades\DB;

/**
 * Which sources a batch will add contacts to, with row counts and whether each source already exists
 * (new ones are created when the batch is committed).
 *
 * @phpstan-type BatchSource array{name: string, rows: int, existing: bool, default: bool}
 */
class BatchSourcesQuery
{
    public function __construct(private SourceNameMatcher $matcher) {}

    /**
     * @return list<BatchSource>
     */
    public function handle(ImportBatch $batch): array
    {
        $defaultName = $batch->source->name ?? $batch->source_name ?? "Import {$batch->id}";

        $sources = [];
        foreach (DB::table('staged_rows')->where('import_batch_id', $batch->id)
            ->selectRaw('source_name, count(*) AS total')->groupBy('source_name')->orderByDesc('total')->get() as $row) {
            $name = $row->source_name === null ? $defaultName : (string) $row->source_name;
            $isDefault = $row->source_name === null;

            $sources[] = [
                'name' => $name,
                'rows' => (int) $row->total,
                'existing' => $isDefault ? ($batch->source_id !== null || $this->matcher->find($name) !== null) : $this->matcher->find($name) !== null,
                'default' => $isDefault,
            ];
        }

        return $sources;
    }
}
