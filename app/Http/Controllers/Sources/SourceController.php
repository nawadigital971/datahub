<?php

namespace App\Http\Controllers\Sources;

use App\Actions\Sources\SaveSourceAction;
use App\DTOs\SourceData;
use App\Enums\LawfulBasis;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\ShowDataHealthRequest;
use App\Http\Requests\Sources\StoreSourceRequest;
use App\Http\Requests\Sources\UpdateSourceRequest;
use App\Models\Source;
use App\Models\SourceGroup;
use App\Queries\Sources\SourceListQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SourceController extends Controller
{
    public function index(ShowDataHealthRequest $request, SourceListQuery $sources): Response
    {
        return Inertia::render('sources/Index', [
            'sources' => $sources->handle(),
            'groups' => SourceGroup::query()->orderBy('id')->get()->map(fn (SourceGroup $group): array => ['value' => $group->id, 'label' => $group->name])->all(),
            'lawfulBases' => array_map(fn (LawfulBasis $basis): array => ['value' => $basis->value, 'label' => $basis->label()], LawfulBasis::cases()),
        ]);
    }

    public function store(StoreSourceRequest $request, SaveSourceAction $action): RedirectResponse
    {
        $source = $action->handle(SourceData::fromRequest($request));

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Added source “{$source->name}”."])->back();
    }

    public function update(UpdateSourceRequest $request, Source $source, SaveSourceAction $action): RedirectResponse
    {
        $action->handle(SourceData::fromRequest($request), $source);

        return Inertia::flash('toast', ['type' => 'success', 'message' => "Saved “{$source->name}”."])->back();
    }
}
