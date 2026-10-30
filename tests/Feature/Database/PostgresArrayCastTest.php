<?php

use App\Models\Contact;
use App\Models\StagedRow;

it('stores and reads source ids as a smallint array', function () {
    $contact = Contact::factory()->create(['source_ids' => [3, 7]]);

    expect($contact->fresh()->source_ids)->toBe([3, 7])
        ->and(Contact::query()->whereRaw('source_ids && ARRAY[7]::smallint[]')->pluck('id')->all())->toBe([$contact->id]);
});

it('stores and reads issue codes as a text array', function () {
    $row = StagedRow::factory()->create(['issue_codes' => ['invalid_phone', 'email_typo']]);

    expect($row->fresh()->issue_codes)->toBe(['invalid_phone', 'email_typo'])
        ->and(StagedRow::query()->whereRaw("issue_codes @> ARRAY['email_typo']")->count())->toBe(1);
});

it('reads an empty array as an empty list', function () {
    expect(Contact::factory()->create(['source_ids' => []])->fresh()->source_ids)->toBe([]);
});
