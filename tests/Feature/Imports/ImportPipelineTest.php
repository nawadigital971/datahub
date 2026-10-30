<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\ContactEventType;
use App\Enums\DncReason;
use App\Enums\EmailStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\IssueCode;
use App\Enums\MatchStatus;
use App\Enums\Role;
use App\Enums\StagedRowAction;
use App\Enums\SuppressionKind;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\MappingTemplate;
use App\Models\Source;
use App\Models\StagedRow;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
    $this->source = Source::factory()->create(['slug' => 'layalina-subscribers', 'name' => ['en' => 'Layalina subscribers']]);
});

const MASTER_HEADER = 'Date Added,Record Type,Source,English Full Name,Arabic Full Name,Gender,Company,Title,Mobile AE,Mobile AE 2,Mobile Intl 1,Mobile Intl 2,Phone,Email,Email 2,Location,Notes';

function existingContact(string $mobile, ?string $email = null, array $attributes = []): Contact
{
    $contact = Contact::factory()->create(['mobile_e164' => $mobile, 'email' => $email, ...$attributes]);
    DB::table('contact_phones')->insert(['contact_id' => $contact->id, 'e164' => $mobile, 'type' => 1, 'is_primary' => true]);
    if ($email !== null) {
        DB::table('contact_emails')->insert(['contact_id' => $contact->id, 'email' => $email, 'is_primary' => true]);
    }

    return $contact;
}

function uploadCsv(User $user, Source $source, string $csv, string $name = 'layalina.csv'): ImportBatch
{
    test()->actingAs($user)
        ->post('/imports', ['source_id' => $source->id, 'file' => UploadedFile::fake()->createWithContent($name, $csv)])
        ->assertRedirect();

    return ImportBatch::query()->latest('id')->firstOrFail();
}

function stagedRow(ImportBatch $batch, int $rowNumber): StagedRow
{
    return StagedRow::query()->where('import_batch_id', $batch->id)->where('row_number', $rowNumber)->sole();
}

it('imports a messy file from upload to commit', function () {
    $matchByPhone = existingContact('+971502222222', null, ['name_en' => 'Old Name', 'source_ids' => [99], 'last_seen_year' => 2015]);
    $phoneContact = existingContact('+971505555555');
    $emailContact = existingContact('+971506666666', 'conflict@example.com');
    Suppression::query()->create(['kind' => SuppressionKind::Phone, 'value' => '+971503333333', 'reason' => DncReason::OptedOut]);

    $csv = implode("\n", [
        MASTER_HEADER,
        '2023-01-10,Person,Layalina subscribers,Fatima Al Mansoori,فاطمة المنصوري,F,,,0501111111,,,,,fatima@example.com,,"Deira, Dubai",',
        '2023-01-10,Person,Layalina subscribers,New Name,,M,,,0502222222,,,,,,,,CB',
        '2023-01-10,Person,Layalina subscribers,Fatima Again,,F,,,050 111 1111,,,,,,,,',
        '2023-01-10,Person,Layalina subscribers,Blocked Person,,,,,0503333333,,,,,,,,',
        '2023-01-10,Person,Layalina subscribers,Sara Typo,,F,,,0504444444,,,,,sara@gamil.com,,,',
        '2023-01-10,Person,Layalina subscribers,No Contact,,,,,,,,,,,,,',
        '2023-01-10,Person,Layalina subscribers,Conflict Person,,,,,0505555555,,,,,conflict@example.com,,,',
    ]);

    // 1. Upload: headers read, master layout detected, waiting for the mapping.
    $batch = uploadCsv($this->admin, $this->source, $csv);
    expect($batch->status)->toBe(ImportBatchStatus::Mapping)
        ->and($batch->mapping_template_id)->toBe(MappingTemplate::query()->where('name', 'Master sheet')->value('id'))
        ->and($batch->column_map['Mobile AE'])->toBe('phone');

    // 2. Confirm mapping: the analysis runs (queue is sync in tests).
    $this->actingAs($this->admin)
        ->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map, 'mapping_template_id' => $batch->mapping_template_id])
        ->assertRedirect("/imports/{$batch->id}");

    $batch->refresh();
    expect($batch->status)->toBe(ImportBatchStatus::Ready)
        ->and($batch->total_rows)->toBe(7)
        ->and(stagedRow($batch, 2)->action)->toBe(StagedRowAction::Create)
        ->and(stagedRow($batch, 3)->match_status)->toBe(MatchStatus::MatchesContact)
        ->and(stagedRow($batch, 3)->matched_contact_id)->toBe($matchByPhone->id)
        ->and(stagedRow($batch, 4)->match_status)->toBe(MatchStatus::DupInBatch)
        ->and(stagedRow($batch, 4)->action)->toBe(StagedRowAction::Skip)
        ->and(stagedRow($batch, 5)->issue_codes)->toContain('suppressed')
        ->and(stagedRow($batch, 5)->action)->toBe(StagedRowAction::Skip)
        ->and(stagedRow($batch, 6)->issue_codes)->toContain('email_typo')
        ->and(stagedRow($batch, 7)->action)->toBe(StagedRowAction::Skip)
        ->and(stagedRow($batch, 8)->match_status)->toBe(MatchStatus::Conflict)
        ->and(stagedRow($batch, 8)->matched_contact_id)->toBe($phoneContact->id);

    // 3. Inline edit: give row 7 a phone; it is re-checked and becomes a new contact.
    $this->actingAs($this->admin)
        ->patchJson("/imports/{$batch->id}/rows/".stagedRow($batch, 7)->id, ['field' => 'mobile_e164', 'value' => '055 777 7777'])
        ->assertOk()
        ->assertJsonPath('row.mobile_e164', '+971557777777')
        ->assertJsonPath('row.action', 'create')
        ->assertJsonPath('row.severity', 'clean');
    expect(DB::table('staged_row_edits')->count())->toBe(1);

    // 4. Bulk: accept the email typo suggestion.
    $this->actingAs($this->admin)
        ->post("/imports/{$batch->id}/rows/bulk", ['operation' => 'accept_suggestions', 'all_matching' => true, 'filter' => ['issue' => 'email_typo']])
        ->assertRedirect();
    expect(stagedRow($batch, 6)->email)->toBe('sara@gmail.com')
        ->and(stagedRow($batch, 6)->issue_codes)->not->toContain('email_typo');

    // 5. Merge panel: take the incoming English name for the phone match.
    $this->actingAs($this->admin)
        ->getJson("/imports/{$batch->id}/rows/".stagedRow($batch, 3)->id.'/merge')
        ->assertOk()
        ->assertJsonPath('contact_id', $matchByPhone->id)
        ->assertJsonPath('fields.0.incoming', 'New Name')
        ->assertJsonPath('fields.0.existing', 'Old Name');
    $this->actingAs($this->admin)
        ->putJson("/imports/{$batch->id}/rows/".stagedRow($batch, 3)->id.'/merge', ['contact_id' => $matchByPhone->id, 'choices' => ['name_en' => 'incoming']])
        ->assertOk()
        ->assertJsonPath('row.action', 'merge');

    // 6. Commit.
    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit")->assertRedirect();

    $batch->refresh();
    expect($batch->status)->toBe(ImportBatchStatus::Committed)
        ->and($batch->committed_by)->toBe($this->admin->id)
        ->and(StagedRow::query()->where('import_batch_id', $batch->id)->count())->toBe(0);

    $fatima = Contact::query()->where('mobile_e164', '+971501111111')->sole();
    expect($fatima->name_ar)->toBe('فاطمة المنصوري')
        ->and($fatima->source_ids)->toBe([$this->source->id])
        ->and($fatima->last_seen_year)->toBe(2023)
        ->and(DB::table('contact_emails')->where('contact_id', $fatima->id)->value('email'))->toBe('fatima@example.com')
        ->and(DB::table('contact_sources')->where('contact_id', $fatima->id)->value('row_number'))->toBe(2);

    $merged = $matchByPhone->fresh();
    expect($merged->name_en)->toBe('New Name')
        ->and($merged->source_ids)->toBe(collect([$this->source->id, 99])->sort()->values()->all())
        ->and($merged->last_seen_year)->toBe(2023)
        ->and(DB::table('contact_events')->where('contact_id', $merged->id)->pluck('type')->sort()->values()->all())
        ->toBe([ContactEventType::Call->value, ContactEventType::Merge->value]);

    expect(Contact::query()->where('mobile_e164', '+971504444444')->sole()->email_status)->toBe(EmailStatus::TypoFixed)
        ->and(Contact::query()->where('mobile_e164', '+971557777777')->exists())->toBeTrue()
        ->and(DB::table('contact_phones')->where('e164', '+971503333333')->exists())->toBeFalse()
        ->and(Contact::query()->count())->toBe(3 + 3); // 3 existing + Fatima, Sara, row 7
});

it('refuses employee files', function () {
    $this->actingAs($this->admin)
        ->post('/imports', ['source_id' => $this->source->id, 'file' => UploadedFile::fake()->createWithContent('Waseet Stuff Info for Medical Insurance Final.csv', MASTER_HEADER)])
        ->assertSessionHasErrors(['file' => 'This file cannot be imported: Employee data (medical insurance list).']);

    expect(ImportBatch::query()->count())->toBe(0);
});

it('requires a mapping that can reach people', function () {
    $batch = uploadCsv($this->admin, $this->source, "Name,City\nAli,Dubai");

    $this->actingAs($this->admin)
        ->put("/imports/{$batch->id}/mapping", ['column_map' => ['Name' => 'name', 'City' => 'address_raw']])
        ->assertSessionHasErrors('column_map');
});

it('guesses the mapping of an unknown layout from its headers', function () {
    $batch = uploadCsv($this->admin, $this->source, "الاسم,رقم الجوال,البريد الإلكتروني,ملاحظات\nعلي,0501234567,ali@example.com,NI");

    expect($batch->mapping_template_id)->toBeNull()
        ->and($batch->column_map)->toEqual(['الاسم' => 'name', 'رقم الجوال' => 'phone', 'البريد الإلكتروني' => 'email', 'ملاحظات' => 'notes']);
});

it('reads xlsx files with phones stored as numbers and real dates', function () {
    $path = storage_path('app/test-import.xlsx');
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Full Name in English', 'AE Number', 'Timestamp']));
    $writer->addRow(Row::fromValues(['Omar Saleh', 971501234567.0, new DateTimeImmutable('2022-03-04')]));
    $writer->close();

    $batch = uploadCsv($this->admin, $this->source, file_get_contents($path), 'photographers.xlsx');
    unlink($path);
    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map]);

    $row = stagedRow($batch, 2);
    expect($row->mobile_e164)->toBe('+971501234567')
        ->and($row->seen_year)->toBe(2022)
        ->and($row->raw['AE Number'])->toBe('971501234567');
});

it('lets data editors review but not commit', function () {
    $editor = User::factory()->create()->assignRole(Role::DataEditor->value);
    $batch = uploadCsv($editor, $this->source, MASTER_HEADER."\n2023-01-10,Person,x,Ali,,,,,0501234567,,,,,,,,");
    $this->actingAs($editor)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map]);

    $this->actingAs($editor)->get("/imports/{$batch->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('imports/Show')->has('rows.data', 1)->where('batch.can.commit', false));
    $this->actingAs($editor)->post("/imports/{$batch->id}/commit")->assertForbidden();
});

it('discards a batch without importing anything', function () {
    $batch = uploadCsv($this->admin, $this->source, MASTER_HEADER."\n2023-01-10,Person,x,Ali,,,,,0501234567,,,,,,,,");
    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map]);

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/discard")->assertRedirect('/imports');

    expect($batch->fresh()->status)->toBe(ImportBatchStatus::Discarded)
        ->and(Contact::query()->count())->toBe(0);
});

it('purges discarded batches after the retention period', function () {
    $batch = uploadCsv($this->admin, $this->source, MASTER_HEADER."\n2023-01-10,Person,x,Ali,,,,,0501234567,,,,,,,,");
    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map]);
    $batch->update(['status' => ImportBatchStatus::Discarded, 'discarded_at' => now()->subDays(31)]);

    $this->artisan('app:purge-imports')->assertSuccessful();

    expect(StagedRow::query()->count())->toBe(0)
        ->and($batch->fresh()->getFirstMedia('file'))->toBeNull();
});

it('rejects inline edits with an invalid phone', function () {
    $batch = uploadCsv($this->admin, $this->source, MASTER_HEADER."\n2023-01-10,Person,x,Ali,,,,,0501234567,,,,,,,,");
    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map]);

    $this->actingAs($this->admin)
        ->patchJson("/imports/{$batch->id}/rows/".stagedRow($batch, 2)->id, ['field' => 'mobile_e164', 'value' => '123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value' => 'Enter one valid phone number']);
});

it('saves the confirmed mapping as a template for files with the same headers', function () {
    $batch = uploadCsv($this->admin, $this->source, "Full Name,WhatsApp No\nAli,0501111111");

    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", [
        'column_map' => ['Full Name' => 'name', 'WhatsApp No' => 'phone'],
        'save_as_template' => 'Ad leads',
    ])->assertSessionHasNoErrors();

    $template = MappingTemplate::query()->where('name', 'Ad leads')->sole();
    expect($template->column_map)->toEqual(['Full Name' => 'name', 'WhatsApp No' => 'phone'])
        ->and($batch->refresh()->mapping_template_id)->toBe($template->id);

    $next = uploadCsv($this->admin, $this->source, "Full Name,WhatsApp No\nSara,0502222222", 'next.csv');
    expect($next->mapping_template_id)->toBe($template->id)
        ->and($next->column_map)->toEqual(['Full Name' => 'name', 'WhatsApp No' => 'phone']);
});

it('does not let importers overwrite a template another person saved', function () {
    MappingTemplate::factory()->create(['name' => 'Ad leads']);
    $editor = User::factory()->create()->assignRole(Role::DataEditor->value);
    $batch = uploadCsv($editor, $this->source, "Name,Mobile\nAli,0501111111");

    $this->actingAs($editor)->put("/imports/{$batch->id}/mapping", [
        'column_map' => ['Name' => 'name', 'Mobile' => 'phone'], 'save_as_template' => 'Ad leads',
    ])->assertSessionHasErrors(['save_as_template' => 'A template with this name already exists. Choose another name.']);
});

it('re-checks an uploaded batch with rules added after it was analysed', function () {
    $batch = uploadCsv($this->admin, $this->source, "Name,Mobile,Email\nAli,0501111111,ali@gmial.co");
    $this->actingAs($this->admin)->put("/imports/{$batch->id}/mapping", ['column_map' => ['Name' => 'name', 'Mobile' => 'phone', 'Email' => 'email']]);
    expect(stagedRow($batch, 2)->issue_codes)->not->toContain(IssueCode::EmailTypo->value);

    $this->actingAs($this->admin)->post('/admin/reference/cleaning_rules', ['values' => ['type' => 'email_domain_fix', 'key' => 'gmial.co', 'value' => 'gmail.com']]);
    app()->forgetScopedInstances();

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/recheck")->assertSessionHasNoErrors()->assertRedirect();

    expect($batch->refresh()->status)->toBe(ImportBatchStatus::Ready)
        ->and(stagedRow($batch, 2)->issue_codes)->toContain(IssueCode::EmailTypo->value);
});

it('does not re-check a batch once a commit has started', function () {
    $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Failed, 'committed_by' => $this->admin->id, 'column_map' => ['Mobile' => 'phone']]);

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/recheck")->assertForbidden();
});
