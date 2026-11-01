<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\DTOs\AudienceFilters;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use App\Models\User;
use App\Queries\Audience\AudienceFilterApplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

/**
 * Upload a small "Name,Arabic Name,Mobile" file, confirm the mapping and return the analysed batch.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $rows
 */
function stageNamedRows(User $user, array $rows): ImportBatch
{
    $csv = implode("\n", ['English Full Name,Arabic Full Name,Mobile', ...array_map(fn (array $row): string => implode(',', $row), $rows)]);
    test()->actingAs($user)->post('/imports', ['source_name' => 'Expo 2026', 'file' => UploadedFile::fake()->createWithContent('expo.csv', $csv)])->assertRedirect();
    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    test()->actingAs($user)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map])->assertSessionHasNoErrors();

    return $batch->refresh();
}

function commitNamedRows(User $user, array $rows): ImportBatch
{
    $batch = stageNamedRows($user, $rows);
    test()->actingAs($user)->post("/imports/{$batch->id}/commit")->assertRedirect();

    return $batch;
}

function contactWithNames(string $mobile, ?string $nameEn, ?string $nameAr = null, array $otherNames = []): Contact
{
    $contact = Contact::factory()->create(['mobile_e164' => $mobile, 'email' => null, 'name_en' => $nameEn, 'name_ar' => $nameAr, 'other_names' => $otherNames]);
    DB::table('contact_phones')->insert(['contact_id' => $contact->id, 'e164' => $mobile, 'type' => 1, 'is_primary' => true]);

    return $contact;
}

it('keeps the incoming name when a row merges into a contact with the same phone', function () {
    $contact = contactWithNames('+971501234567', 'Ahmad Ali');

    commitNamedRows($this->admin, [['Mohammed Saleh', '', '0501234567']]);

    $contact->refresh();
    expect($contact->name_en)->toBe('Ahmad Ali')
        ->and($contact->other_names)->toBe(['Mohammed Saleh'])
        ->and(Contact::query()->count())->toBe(1);
});

it('keeps the old main name when the reviewer picks the incoming name', function () {
    $contact = contactWithNames('+971501234567', 'Ahmad Ali');
    $batch = stageNamedRows($this->admin, [['Mohammed Saleh', '', '0501234567']]);
    $row = StagedRow::query()->where('import_batch_id', $batch->id)->sole();

    $this->actingAs($this->admin)
        ->putJson("/imports/{$batch->id}/rows/{$row->id}/merge", ['contact_id' => $contact->id, 'choices' => ['name_en' => 'incoming']])
        ->assertSuccessful();
    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit")->assertRedirect();

    $contact->refresh();
    expect($contact->name_en)->toBe('Mohammed Saleh')
        ->and($contact->other_names)->toBe(['Ahmad Ali']);
});

it('keeps the names of every row in one file that shares a phone', function () {
    commitNamedRows($this->admin, [
        ['Ahmad Ali', '', '0501234567'],
        ['Mohammed Saleh', '', '0501234567'],
        ['Abu Khalid', 'أبو خالد', '0501234567'],
    ]);

    $contact = Contact::query()->sole();
    expect($contact->name_en)->toBe('Ahmad Ali')
        ->and($contact->other_names)->toBe(['Mohammed Saleh', 'Abu Khalid', 'أبو خالد']);
});

it('adds more names over several imports without repeating spelling variants', function () {
    $contact = contactWithNames('+971501234567', 'Ahmad Ali', 'أحمد علي', ['Mohammed Saleh']);

    commitNamedRows($this->admin, [['  mohammed   saleh ', 'احمد علي', '0501234567']]);
    commitNamedRows($this->admin, [['Khalid Omar', '', '0501234567']]);

    expect($contact->refresh()->other_names)->toBe(['Mohammed Saleh', 'Khalid Omar']);
});

it('finds a contact by any of its names', function () {
    $contact = contactWithNames('+971501234567', 'Ahmad Ali', null, ['Mohammed Saleh']);
    contactWithNames('+971509999999', 'Someone Else');

    $found = app(AudienceFilterApplier::class)
        ->apply(Contact::query(), AudienceFilters::fromArray(['search' => 'mohammed saleh', 'hide_dnc' => '0']))
        ->pluck('id')
        ->all();

    expect($found)->toBe([$contact->id]);
});

it('shows the other names in the contacts list and the merge panel', function () {
    $contact = contactWithNames('+971501234567', 'Ahmad Ali', null, ['Mohammed Saleh']);
    $batch = stageNamedRows($this->admin, [['Khalid Omar', '', '0501234567']]);
    $row = StagedRow::query()->where('import_batch_id', $batch->id)->sole();

    $this->actingAs($this->admin)
        ->getJson("/imports/{$batch->id}/rows/{$row->id}/merge")
        ->assertOk()
        ->assertJsonPath('other_names', ['Mohammed Saleh']);

    $this->actingAs($this->admin)->get('/audience')
        ->assertInertia(fn ($page) => $page->where('contacts.data.0.id', $contact->id)->where('contacts.data.0.other_names', ['Mohammed Saleh']));
});
