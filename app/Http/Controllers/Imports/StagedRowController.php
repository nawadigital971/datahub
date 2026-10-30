<?php

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\BulkUpdateStagedRowsAction;
use App\Actions\Imports\SaveMergeChoicesAction;
use App\Actions\Imports\UpdateStagedRowAction;
use App\DTOs\Imports\BulkStagedRowsData;
use App\DTOs\Imports\MergeChoicesData;
use App\DTOs\Imports\StagedRowEditData;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\BulkUpdateStagedRowsRequest;
use App\Http\Requests\Imports\SaveMergeChoicesRequest;
use App\Http\Requests\Imports\ShowMergeComparisonRequest;
use App\Http\Requests\Imports\UpdateStagedRowRequest;
use App\Http\Resources\ImportBatchResource;
use App\Http\Resources\StagedRowResource;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use App\Queries\Imports\MergeComparisonQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StagedRowController extends Controller
{
    /**
     * Inline edit (JSON): the server re-normalizes and re-checks the row and returns it with fresh counts.
     */
    public function update(UpdateStagedRowRequest $request, ImportBatch $batch, StagedRow $row, UpdateStagedRowAction $action): JsonResponse
    {
        $row = $action->handle($row, StagedRowEditData::fromRequest($request));

        return response()->json([
            'row' => new StagedRowResource($row->load('dupOf:id,row_number')),
            'batch' => new ImportBatchResource($batch->refresh()->load('source', 'user:id,name', 'mappingTemplate')),
        ]);
    }

    /**
     * Change many rows at once (selected rows, or every row matching the current filters).
     */
    public function bulk(BulkUpdateStagedRowsRequest $request, ImportBatch $batch, BulkUpdateStagedRowsAction $action): RedirectResponse
    {
        $changed = $action->handle($batch, BulkStagedRowsData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => number_format($changed).' rows updated.'])->back();
    }

    /**
     * Incoming row vs existing contact for the merge panel (JSON).
     */
    public function mergeComparison(ShowMergeComparisonRequest $request, ImportBatch $batch, StagedRow $row, MergeComparisonQuery $comparison): JsonResponse
    {
        return response()->json($comparison->handle(
            $row,
            $request->integer('contact_id') ?: null,
            mask: ! $request->user()?->can(Permission::ContactsViewUnmasked->value),
        ));
    }

    public function saveMergeChoices(SaveMergeChoicesRequest $request, ImportBatch $batch, StagedRow $row, SaveMergeChoicesAction $action): JsonResponse
    {
        $row = $action->handle($row, MergeChoicesData::fromRequest($request));

        return response()->json([
            'row' => new StagedRowResource($row->refresh()->load('dupOf:id,row_number')),
            'batch' => new ImportBatchResource($batch->refresh()->load('source', 'user:id,name', 'mappingTemplate')),
        ]);
    }
}
