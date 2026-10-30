<?php

namespace App\Actions\Imports;

use App\DTOs\Imports\ColumnMappingData;
use App\Enums\ImportBatchStatus;
use App\Enums\MappingLayout;
use App\Jobs\AnalyzeBatchJob;
use App\Models\ImportBatch;
use App\Models\MappingTemplate;

class ConfirmMappingAction
{
    /**
     * Save the column mapping (optionally as a named template for files with the same headers)
     * and (re)start the analysis in the background.
     */
    public function handle(ImportBatch $batch, ColumnMappingData $data): ImportBatch
    {
        $templateId = $data->templateId;
        if ($data->saveAsTemplate !== null) {
            $template = MappingTemplate::query()->firstOrNew(['name' => $data->saveAsTemplate]);
            $template->layout ??= MappingLayout::Custom;
            $template->column_map = $data->columnMap;
            $template->save();
            $templateId = $template->id;
        }

        $batch->update([
            'column_map' => $data->columnMap,
            'mapping_template_id' => $templateId,
            'status' => ImportBatchStatus::Analyzing,
            'error' => null,
        ]);

        AnalyzeBatchJob::dispatch($batch->id)->afterCommit();

        return $batch;
    }
}
