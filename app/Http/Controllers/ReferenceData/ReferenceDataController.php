<?php

namespace App\Http\Controllers\ReferenceData;

use App\Actions\ReferenceData\DeleteReferenceItemAction;
use App\Actions\ReferenceData\SaveReferenceItemAction;
use App\DTOs\ReferenceData\ReferenceItemData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceData\DeleteReferenceItemRequest;
use App\Http\Requests\ReferenceData\ListReferenceItemsRequest;
use App\Http\Requests\ReferenceData\SaveReferenceItemRequest;
use App\Queries\ReferenceData\ReferenceItemListQuery;
use App\Queries\ReferenceData\Types\ReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceDataController extends Controller
{
    /**
     * Every list the import cleaning reads from, editable here and by Claude over MCP.
     */
    public function index(ListReferenceItemsRequest $request, ReferenceItemListQuery $items): Response
    {
        /** @var ReferenceType<Model> $type */
        $type = $request->referenceType();

        return Inertia::render('admin/reference/Index', [
            'types' => array_values(array_map(fn (ReferenceType $allowed): array => ['key' => $allowed->key(), 'label' => $allowed->label()], $request->allowedTypes())),
            'type' => $type->schema(),
            'items' => $items->handle($type, $request),
            'filters' => ['search' => $request->input('filter.search')],
            'locales' => config('app.locales'),
        ]);
    }

    public function store(SaveReferenceItemRequest $request, SaveReferenceItemAction $action): RedirectResponse
    {
        $item = $action->handle(ReferenceItemData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Added “'.$request->referenceType()?->title($item).'”.'])->back();
    }

    public function update(SaveReferenceItemRequest $request, SaveReferenceItemAction $action): RedirectResponse
    {
        $item = $action->handle(ReferenceItemData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved “'.$request->referenceType()?->title($item).'”.'])->back();
    }

    public function destroy(DeleteReferenceItemRequest $request, DeleteReferenceItemAction $action): RedirectResponse
    {
        $title = $action->handle(ReferenceItemData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted “{$title}”."])->back();
    }
}
