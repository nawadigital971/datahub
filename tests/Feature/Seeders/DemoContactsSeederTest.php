<?php

use App\Actions\Contacts\RefreshFacetCountsAction;
use App\Models\Contact;
use Database\Seeders\DemoContactsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(DemoContactsSeeder::class)->run(app(RefreshFacetCountsAction::class), 500);
});

it('loads the requested number of contacts with unique phones and emails', function () {
    expect(Contact::query()->count())->toBe(500)
        ->and(DB::table('contact_phones')->count())->toBe(DB::table('contact_phones')->distinct()->count('e164'))
        ->and(DB::table('contact_emails')->count())->toBe(DB::table('contact_emails')->distinct()->count('email'));
});

it('keeps source_ids in step with the contact_sources rows', function () {
    $mismatches = DB::selectOne(<<<'SQL'
        SELECT count(*) AS total FROM contacts c
        WHERE c.source_count <> cardinality(c.source_ids)
           OR c.source_ids <> (SELECT array_agg(cs.source_id ORDER BY cs.source_id) FROM contact_sources cs WHERE cs.contact_id = c.id)
        SQL);

    expect((int) $mismatches->total)->toBe(0);
});

it('stores the primary mobile and email as rows in the child tables', function () {
    $missing = DB::selectOne(<<<'SQL'
        SELECT
            (SELECT count(*) FROM contacts c WHERE c.mobile_e164 IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM contact_phones p WHERE p.contact_id = c.id AND p.e164 = c.mobile_e164 AND p.is_primary)) AS phones,
            (SELECT count(*) FROM contacts c WHERE c.email IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM contact_emails e WHERE e.contact_id = c.id AND e.email = c.email)) AS emails
        SQL);

    expect((int) $missing->phones)->toBe(0)
        ->and((int) $missing->emails)->toBe(0);
});

it('refreshes the facet counts after loading', function () {
    $contactable = Contact::query()->where('do_not_contact', false)->count();

    expect((int) DB::table('facet_counts')->where('dimension', 'total')->value('total'))->toBe($contactable);
});

it('continues the id sequence after the loaded rows', function () {
    expect(Contact::factory()->create()->id)->toBe(501);
});

it('refuses to load over existing contacts without confirmation', function () {
    app(DemoContactsSeeder::class)->run(app(RefreshFacetCountsAction::class), 10);

    expect(Contact::query()->count())->toBe(500);
});
