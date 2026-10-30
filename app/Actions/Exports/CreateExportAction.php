<?php

namespace App\Actions\Exports;

use App\DTOs\ExportData;
use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\Export;

class CreateExportAction
{
    /**
     * Log the export request and queue the file generation.
     */
    public function handle(ExportData $data): Export
    {
        $export = Export::query()->create([
            'user_id' => $data->userId,
            'filters' => $data->filters->toQuery(),
            'channel' => $data->channel,
            'hashed' => $data->hashed,
            'status' => ExportStatus::Queued,
            'purpose' => $data->purpose,
        ]);

        GenerateExportJob::dispatch($export->id)->afterCommit();

        return $export;
    }
}
