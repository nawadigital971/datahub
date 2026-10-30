<?php

namespace App\Http\Controllers\Exports;

use App\Actions\Exports\CreateExportAction;
use App\Actions\Exports\DownloadExportAction;
use App\DTOs\AudienceFilters;
use App\DTOs\ExportData;
use App\Enums\ExportChannel;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exports\ListExportsRequest;
use App\Http\Requests\Exports\PreviewExportRequest;
use App\Http\Requests\Exports\ShowExportRequest;
use App\Http\Requests\Exports\StoreExportRequest;
use App\Http\Resources\ExportPreviewResource;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Queries\Exports\ExportLogQuery;
use App\Queries\Exports\ExportPreviewQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ExportController extends Controller
{
    /**
     * The export log: who exported what, when and why.
     */
    public function index(ListExportsRequest $request, ExportLogQuery $log): Response
    {
        return Inertia::render('exports/Index', [
            'exports' => ExportResource::collection($log->handle($request)),
            'filters' => [
                'channel' => $request->input('filter.channel'),
                'user' => $request->integer('filter.user') ?: null,
            ],
            'channels' => array_map(fn (ExportChannel $channel): array => ['value' => $channel->key(), 'label' => $channel->label()], ExportChannel::cases()),
            'exporters' => $log->exporters(),
        ]);
    }

    /**
     * First rows of an export as they will be written (JSON for the export dialog).
     */
    public function preview(PreviewExportRequest $request, ExportPreviewQuery $preview): ExportPreviewResource
    {
        return new ExportPreviewResource($preview->handle(
            AudienceFilters::fromRequest($request),
            ExportChannel::fromKey($request->string('channel')->toString()),
            $request->boolean('hashed'),
            mask: ! $request->user()?->can(Permission::ContactsViewUnmasked->value),
        ));
    }

    public function store(StoreExportRequest $request, CreateExportAction $action): RedirectResponse
    {
        $export = $action->handle(ExportData::fromRequest($request));

        return Inertia::flash([
            'export_id' => $export->id,
            'toast' => ['type' => 'success', 'message' => 'Export started. It is logged with your purpose.'],
        ])->back();
    }

    /**
     * Export status for progress polling (JSON).
     */
    public function show(ShowExportRequest $request, Export $export): ExportResource
    {
        return new ExportResource($export->load('user:id,name', 'media'));
    }

    public function download(ShowExportRequest $request, Export $export, DownloadExportAction $action): Media
    {
        return $action->handle($export);
    }
}
