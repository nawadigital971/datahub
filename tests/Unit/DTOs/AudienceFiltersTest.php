<?php

use App\DTOs\AudienceFilters;

it('applies defaults when the URL has no filters', function () {
    $filters = AudienceFilters::fromArray([]);

    expect($filters->toggle('hide_dnc'))->toBeTrue()
        ->and($filters->toggle('has_email'))->toBeFalse()
        ->and($filters->isDefault())->toBeTrue()
        ->and($filters->toQuery())->toBe([]);
});

it('keeps only non-default values in the URL form, with sorted unique ids', function () {
    $filters = AudienceFilters::fromArray([
        'search' => '  Fatima   Ali ',
        'region' => '3,1,3',
        'gender' => ['2'],
        'hide_dnc' => 'false',
        'has_email' => 'true',
        'year_from' => '2020',
    ]);

    expect($filters->toQuery())->toBe([
        'search' => 'Fatima Ali',
        'year_from' => '2020',
        'hide_dnc' => '0',
        'has_email' => '1',
        'region' => '1,3',
        'gender' => '2',
    ]);
});

it('drops invalid ids and out-of-range years', function () {
    $filters = AudienceFilters::fromArray(['area' => '5,abc,-2', 'year_to' => '3000']);

    expect($filters->facet('area'))->toBe([5])
        ->and($filters->yearTo)->toBeNull();
});

it('swaps a reversed year range', function () {
    $filters = AudienceFilters::fromArray(['year_from' => '2024', 'year_to' => '2015']);

    expect([$filters->yearFrom, $filters->yearTo])->toBe([2015, 2024]);
});

it('produces the same hash for the same filters in a different order', function () {
    expect(AudienceFilters::fromArray(['region' => '1,2', 'has_mobile' => '1'])->hash())
        ->toBe(AudienceFilters::fromArray(['has_mobile' => 'true', 'region' => '2,1'])->hash());
});

it('drops one facet for "all filters except this one" counts', function () {
    $filters = AudienceFilters::fromArray(['region' => '1', 'gender' => '2']);

    expect($filters->withoutFacet('region')->toQuery())->toBe(['gender' => '2']);
});
