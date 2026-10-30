<?php

use App\Actions\Contacts\RefreshFacetCountsAction;
use App\Enums\Gender;
use App\Models\Contact;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

function facetTotal(string $dimension, int $valueId): ?int
{
    $total = DB::table('facet_counts')->where('dimension', $dimension)->where('value_id', $valueId)->value('total');

    return $total === null ? null : (int) $total;
}

it('counts contactable contacts per dimension and leaves out do-not-contact', function () {
    $dubai = Region::factory()->create();
    Contact::factory()->count(2)->create(['region_id' => $dubai->id, 'gender' => Gender::Female, 'source_ids' => [1, 2]]);
    Contact::factory()->create(['region_id' => null, 'gender' => null, 'source_ids' => [2]]);
    Contact::factory()->doNotContact()->create(['region_id' => $dubai->id, 'source_ids' => [1]]);

    app(RefreshFacetCountsAction::class)->handle();

    expect(facetTotal('total', 0))->toBe(3)
        ->and(facetTotal('region', $dubai->id))->toBe(2)
        ->and(facetTotal('region', 0))->toBe(1)
        ->and(facetTotal('gender', 2))->toBe(2)
        ->and(facetTotal('gender', 0))->toBe(1)
        ->and(facetTotal('source', 1))->toBe(2)
        ->and(facetTotal('source', 2))->toBe(3)
        ->and(facetTotal('has_mobile', 1))->toBe(3);
});
