<?php

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\ConfirmMappingAction;
use App\DTOs\Imports\ColumnMappingData;
use App\Enums\ImportTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\ConfirmMappingRequest;
use App\Http\Requests\Imports\ShowImportBatchRequest;
use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\MappingTemplate;
use App\Queries\Imports\ImportFileSampleQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportMappingController extends Controller
{
    /**
     * Map each column of the file to a contact field (prefilled from the detected layout).
     */
    public function edit(ShowImportBatchRequest $request, ImportBatch $batch, ImportFileSampleQuery $samples): Response
    {
        return Inertia::render('imports/Mapping', [
            'batch' => (new ImportBatchResource($batch->load('source', 'user:id,name', 'mappingTemplate')))->resolve($request),
            'headers' => $batch->headers ?? [],
            'columnMap' => $batch->column_map ?? [],
            'templateId' => $batch->mapping_template_id,
            'samples' => $samples->handle($batch),
            'templates' => MappingTemplate::query()->orderBy('name')->get()
                ->map(fn (MappingTemplate $template): array => ['value' => $template->id, 'label' => $template->name, 'column_map' => $template->column_map])->all(),
            'targets' => array_map(fn (ImportTarget $target): array => ['value' => $target->value, 'label' => $target->label()], ImportTarget::cases()),
        ]);
    }

    public function update(ConfirmMappingRequest $request, ImportBatch $batch, ConfirmMappingAction $action): RedirectResponse
    {
        $action->handle($batch, ColumnMappingData::fromRequest($request));

        return to_route('imports.show', $batch);
    }
}
