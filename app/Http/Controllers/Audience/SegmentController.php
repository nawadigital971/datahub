<?php

namespace App\Http\Controllers\Audience;

use App\Actions\Segments\CreateSegmentAction;
use App\Actions\Segments\DeleteSegmentAction;
use App\DTOs\SegmentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audience\DestroySegmentRequest;
use App\Http\Requests\Audience\StoreSegmentRequest;
use App\Models\Segment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SegmentController extends Controller
{
    public function store(StoreSegmentRequest $request, CreateSegmentAction $action): RedirectResponse
    {
        $segment = $action->handle(SegmentData::fromRequest($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Saved segment “{$segment->name}”."]);

        return back();
    }

    public function destroy(DestroySegmentRequest $request, Segment $segment, DeleteSegmentAction $action): RedirectResponse
    {
        $action->handle($segment);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Deleted segment “{$segment->name}”."]);

        return back();
    }
}
