<?php

namespace App\Actions\Sources;

use App\DTOs\SourceData;
use App\Enums\LawfulBasis;
use App\Models\ImportBatch;
use App\Queries\Sources\SourceNameMatcher;
use Illuminate\Support\Facades\DB;

/**
 * At commit: turn the source names found in a batch (per row, plus the batch default for rows without
 * one) into source records — reusing existing sources, creating the rest — and store the id on each row.
 */
class ResolveBatchSourcesAction
{
    public function __construct(private SourceNameMatcher $matcher, private SaveSourceAction $saveSource) {}

    public function handle(ImportBatch $batch): void
    {
        $names = DB::table('staged_rows')->where('import_batch_id', $batch->id)->whereNotNull('source_name')
            ->distinct()->pluck('source_name')->map(fn (mixed $name): string => (string) $name)->all();

        foreach ($names as $name) {
            DB::table('staged_rows')->where('import_batch_id', $batch->id)->where('source_name', $name)
                ->update(['source_id' => $this->sourceId($name, $batch)]);
        }

        $withoutSource = DB::table('staged_rows')->where('import_batch_id', $batch->id)->whereNull('source_name')->exists();
        if ($withoutSource || $batch->source_id === null) {
            $defaultId = $batch->source_id ?? $this->sourceId($batch->source_name ?? "Import {$batch->id}", $batch);
            $batch->update(['source_id' => $defaultId]);
            DB::table('staged_rows')->where('import_batch_id', $batch->id)->whereNull('source_name')->update(['source_id' => $defaultId]);
        }
    }

    private function sourceId(string $name, ImportBatch $batch): int
    {
        $existing = $this->matcher->find($name);
        if ($existing !== null) {
            return $existing;
        }

        $isArabic = preg_match('/\p{Arabic}/u', $name) === 1;
        $source = $this->saveSource->handle(new SourceData(
            name: $isArabic ? ['en' => $name, 'ar' => $name] : ['en' => $name],
            groupId: null,
            collectedYear: null,
            lawfulBasis: LawfulBasis::Unknown,
            notes: "Created from import #{$batch->id} ({$batch->original_filename}).",
        ));
        $this->matcher->remember($name, $source->id);

        return $source->id;
    }
}
