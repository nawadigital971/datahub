<?php

namespace App\Http\Controllers\Imports;

use App\Actions\Imports\CommitImportBatchAction;
use App\Actions\Imports\CreateImportBatchAction;
use App\Actions\Imports\DiscardImportBatchAction;
use App\Actions\Imports\RecheckImportBatchAction;
use App\DTOs\Imports\ImportUploadData;
use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\CommitImportBatchRequest;
use App\Http\Requests\Imports\DiscardImportBatchRequest;
use App\Http\Requests\Imports\RecheckImportBatchRequest;
use App\Http\Requests\Imports\ShowImportBatchRequest;
use App\Http\Requests\Imports\StoreImportBatchRequest;
use App\Http\Resources\ImportBatchResource;
use App\Http\Resources\StagedRowResource;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Queries\Imports\BatchSourcesQuery;
use App\Queries\Imports\ImportBatchListQuery;
use App\Queries\Imports\ImportReviewOptionsQuery;
use App\Queries\Imports\StagedRowListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportBatchController extends Controller
{
    public function index(ShowImportBatchRequest $request, ImportBatchListQuery $batches): Response
    {
        return Inertia::render('imports/Index', [
            'batches' => ImportBatchResource::collection($batches->handle($request)),
            'sources' => Source::query()->orderBy('id')->get()
                ->map(fn (Source $source): array => ['value' => $source->id, 'label' => $source->name])->all(),
        ]);
    }

    public function store(StoreImportBatchRequest $request, CreateImportBatchAction $action): RedirectResponse
    {
        $batch = $action->handle(ImportUploadData::fromRequest($request));

        return to_route('imports.mapping.edit', $batch);
    }

    /**
     * The preview: summary, filters and the editable rows (rows only once the analysis is done).
     */
    public function show(ShowImportBatchRequest $request, ImportBatch $batch, StagedRowListQuery $rows, ImportReviewOptionsQuery $options, BatchSourcesQuery $fileSources): Response
    {
        $batch->load('source', 'user:id,name', 'mappingTemplate');
        $hasRows = in_array($batch->status, [ImportBatchStatus::Ready, ImportBatchStatus::Failed], true);

        return Inertia::render('imports/Show', [
            'batch' => (new ImportBatchResource($batch))->resolve($request),
            'rows' => $hasRows
                ? StagedRowResource::collection($rows->handle($batch, $request))
                : null,
            'filters' => $request->stagedRowFilters(),
            'issueCounts' => fn (): array => $hasRows ? $rows->issueCounts($batch) : [],
            'fileSources' => fn (): array => $hasRows ? $fileSources->handle($batch) : [],
            'options' => $options->handle(),
        ]);
    }

    public function commit(CommitImportBatchRequest $request, ImportBatch $batch, CommitImportBatchAction $action): RedirectResponse
    {
        $action->handle($batch, $request->user());

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Committing… contacts are being created and merged.'])->back();
    }

    public function recheck(RecheckImportBatchRequest $request, ImportBatch $batch, RecheckImportBatchAction $action): RedirectResponse
    {
        $action->handle($batch);

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Re-checking every row with the current rules…'])->back();
    }

    public function discard(DiscardImportBatchRequest $request, ImportBatch $batch, DiscardImportBatchAction $action): RedirectResponse
    {
        $action->handle($batch);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Import discarded. Nothing was imported.']);

        return to_route('imports.index');
    }
}
