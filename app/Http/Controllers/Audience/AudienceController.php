<?php

namespace App\Http\Controllers\Audience;

use App\DTOs\AudienceFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audience\ListAudienceRequest;
use App\Http\Resources\ContactResource;
use App\Queries\Audience\AudienceOptionsQuery;
use App\Queries\Audience\AudienceSummaryQuery;
use App\Queries\Audience\ContactListQuery;
use App\Queries\Audience\SavedSegmentsQuery;
use App\Queries\FacetCountsQuery;
use App\Settings\DataSettings;
use Inertia\Inertia;
use Inertia\Response;

class AudienceController extends Controller
{
    /**
     * The Audience console. The records table renders first; counts arrive as deferred props.
     */
    public function index(
        ListAudienceRequest $request,
        ContactListQuery $contacts,
        AudienceOptionsQuery $options,
        AudienceSummaryQuery $summary,
        FacetCountsQuery $facetCounts,
        SavedSegmentsQuery $savedSegments,
        DataSettings $dataSettings,
    ): Response {
        $filters = AudienceFilters::fromRequest($request);

        return Inertia::render('audience/Index', [
            'filters' => $filters->toArray(),
            'contacts' => ContactResource::collection($contacts->handle($request)),
            'options' => $options->handle($filters),
            'segments' => $savedSegments->handle($request->user()),
            'staleYear' => $dataSettings->stale_data_year_threshold,
            'hashExportsByDefault' => $dataSettings->hash_on_export_default,
            'summary' => Inertia::defer(fn (): array => $summary->handle($filters), 'summary'),
            'facets' => Inertia::defer(fn (): array => $facetCounts->handle($filters), 'facets'),
        ]);
    }
}
