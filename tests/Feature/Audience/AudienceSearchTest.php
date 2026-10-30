<?php

use App\DTOs\AudienceFilters;
use App\Models\Contact;
use App\Queries\Audience\AudienceFilterApplier;

function searchContacts(string $search): array
{
    return app(AudienceFilterApplier::class)
        ->apply(Contact::query(), AudienceFilters::fromArray(['search' => $search, 'hide_dnc' => '0']))
        ->orderBy('id')
        ->pluck('id')
        ->all();
}

it('finds Arabic names regardless of hamza, taa marbuta and diacritics', function () {
    $fatima = Contact::factory()->create(['name_en' => null, 'name_ar' => 'فاطمة أحمد']);
    Contact::factory()->create(['name_en' => 'Someone Else']);

    expect(searchContacts('فاطمه احمد'))->toBe([$fatima->id])
        ->and(searchContacts('فَاطِمَة'))->toBe([$fatima->id]);
});

it('finds English names case- and accent-insensitively', function () {
    $jose = Contact::factory()->create(['name_en' => 'José Ramos']);

    expect(searchContacts('jose'))->toBe([$jose->id])
        ->and(searchContacts('RAMOS'))->toBe([$jose->id]);
});

it('finds phones typed in local format', function () {
    $contact = Contact::factory()->create(['mobile_e164' => '+971501234567']);
    Contact::factory()->create(['mobile_e164' => '+971559999999']);

    expect(searchContacts('050 123 4567'))->toBe([$contact->id])
        ->and(searchContacts('+971-50-1234567'))->toBe([$contact->id]);
});

it('treats % and _ in the search text literally', function () {
    Contact::factory()->count(2)->create();

    expect(searchContacts('%%'))->toBe([])
        ->and(searchContacts('__'))->toBe([]);
});

it('ignores a one-character search', function () {
    Contact::factory()->count(2)->create();

    expect(searchContacts('a'))->toHaveCount(2);
});
