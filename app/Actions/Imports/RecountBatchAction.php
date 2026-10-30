<?php

namespace App\Actions\Imports;

use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class RecountBatchAction
{
    /**
     * Refresh the batch summary counts in one aggregate over its rows (uses the batch indexes).
     */
    public function handle(ImportBatch $batch): ImportBatch
    {
        $row = (array) DB::selectOne(<<<'SQL'
            SELECT count(*) AS total,
                count(*) FILTER (WHERE severity = :clean) AS clean,
                count(*) FILTER (WHERE severity = :warning) AS warning,
                count(*) FILTER (WHERE severity = :error) AS error,
                count(*) FILTER (WHERE match_status = :new) AS new,
                count(*) FILTER (WHERE match_status = :matches) AS merge,
                count(*) FILTER (WHERE match_status = :dup) AS dup,
                count(*) FILTER (WHERE match_status = :conflict) AS conflict,
                count(*) FILTER (WHERE action = :skip) AS skipped
            FROM staged_rows WHERE import_batch_id = :batch
            SQL, [
            'batch' => $batch->id,
            'clean' => RowSeverity::Clean->value, 'warning' => RowSeverity::Warning->value, 'error' => RowSeverity::Error->value,
            'new' => MatchStatus::New->value, 'matches' => MatchStatus::MatchesContact->value,
            'dup' => MatchStatus::DupInBatch->value, 'conflict' => MatchStatus::Conflict->value,
            'skip' => StagedRowAction::Skip->value,
        ]);

        $batch->update([
            'total_rows' => (int) $row['total'],
            'clean_rows' => (int) $row['clean'],
            'warning_rows' => (int) $row['warning'],
            'error_rows' => (int) $row['error'],
            'new_rows' => (int) $row['new'],
            'merge_rows' => (int) $row['merge'],
            'dup_in_batch_rows' => (int) $row['dup'],
            'conflict_rows' => (int) $row['conflict'],
            'skipped_rows' => (int) $row['skipped'],
        ]);

        return $batch;
    }
}
