<?php

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\AudienceFilters;
use App\Models\Contact;
use App\Queries\Audience\AudienceSummaryQuery;

it('serves cached counts until the cache is busted', function () {
    $filters = AudienceFilters::fromArray(['hide_junk' => '1']);
    Contact::factory()->create();
    expect(app(AudienceSummaryQuery::class)->handle($filters)['total'])->toBe(1);

    Contact::factory()->create();
    expect(app(AudienceSummaryQuery::class)->handle($filters)['total'])->toBe(1);

    app(BustAudienceCacheAction::class)->handle();
    expect(app(AudienceSummaryQuery::class)->handle($filters)['total'])->toBe(2);
});
