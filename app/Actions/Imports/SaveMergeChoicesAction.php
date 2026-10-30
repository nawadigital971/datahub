<?php

namespace App\Actions\Imports;

use App\DTOs\Imports\MergeChoicesData;
use App\Enums\StagedRowAction;
use App\Models\StagedRow;

class SaveMergeChoicesAction
{
    public function __construct(private RecountBatchAction $recount) {}

    /**
     * Merge this row into the chosen contact with per-field choices (applied at commit).
     */
    public function handle(StagedRow $row, MergeChoicesData $data): StagedRow
    {
        $row->update([
            'matched_contact_id' => $data->contactId,
            'merge_choices' => $data->choices,
            'action' => StagedRowAction::Merge,
            'action_locked' => true,
            'edited_by' => $data->userId,
            'edited_at' => now(),
        ]);

        $this->recount->handle($row->importBatch);

        return $row;
    }
}
