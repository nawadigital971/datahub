<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\ImportBatchStatus;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Models\StagedRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

/**
 * Upload a CSV (optionally with an upload-time source) and analyse it with the suggested mapping.
 *
 * @param  array<string, mixed>  $form
 */
function uploadAndAnalyse(User $user, string $csv, string $filename = 'Layalina_AD_Subscribers 2013.csv', array $form = []): ImportBatch
{
    test()->actingAs($user)
        ->post('/imports', [...$form, 'file' => UploadedFile::fake()->createWithContent($filename, $csv)])
        ->assertRedirect();
    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    test()->actingAs($user)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map])->assertRedirect();

    return $batch->refresh();
}

it('creates the sources named in the file when the batch is committed', function () {
    $existing = Source::factory()->create(['name' => ['en' => 'Layalina subscribers']]);

    $batch = uploadAndAnalyse($this->admin, implode("\n", [
        'Name,Mobile,Source',
        'Omar Saleh,0501111111,LAYALINA SUBSCRIBERS',
        'Sara Ali,0502222222,Waseet Dubai 2016',
        'Huda Nasser,0503333333,',
    ]));

    expect(Source::query()->count())->toBe(1);

    $this->actingAs($this->admin)->get("/imports/{$batch->id}")
        ->assertInertia(fn (Assert $page) => $page->where('fileSources', fn ($sources) => collect($sources)->map(fn ($s) => [$s['name'], $s['existing']])->sortBy(0)->values()->all() === [
            ['LAYALINA SUBSCRIBERS', true],
            ['Layalina AD Subscribers 2013', false],
            ['Waseet Dubai 2016', false],
        ]));

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit")->assertRedirect();

    $waseet = Source::query()->where('slug', 'waseet-dubai-2016')->sole();
    $fromFile = Source::query()->where('slug', 'layalina-ad-subscribers-2013')->sole();
    expect(Source::query()->count())->toBe(3)
        ->and(Contact::query()->where('mobile_e164', '+971501111111')->sole()->source_ids)->toBe([$existing->id])
        ->and(Contact::query()->where('mobile_e164', '+971502222222')->sole()->source_ids)->toBe([$waseet->id])
        ->and(Contact::query()->where('mobile_e164', '+971503333333')->sole()->source_ids)->toBe([$fromFile->id])
        ->and($batch->fresh()->source_id)->toBe($fromFile->id)
        ->and($waseet->notes)->toContain("import #{$batch->id}");
});

it('uses the source chosen at upload for rows without one', function () {
    $chosen = Source::factory()->create();

    $batch = uploadAndAnalyse($this->admin, "Name,Mobile\nOmar,0501111111", form: ['source_id' => $chosen->id]);
    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit");

    expect(Contact::query()->sole()->source_ids)->toBe([$chosen->id])
        ->and(Source::query()->count())->toBe(1);
});

it('names a new upload-time source as typed', function () {
    $batch = uploadAndAnalyse($this->admin, "Name,Mobile\nOmar,0501111111", form: ['source_name' => 'Gala launch event 2026']);
    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit");

    expect(Source::query()->sole()->name)->toBe('Gala launch event 2026');
});

it('gives a contact every source it appears under in one file', function () {
    $batch = uploadAndAnalyse($this->admin, "Name,Mobile,Email,Source\nOmar,0501111111,,List A\nOmar,,omar@example.com,List B");
    StagedRow::query()->where('import_batch_id', $batch->id)->where('row_number', 3)->update(['action' => 2, 'action_locked' => true]);
    $first = Contact::factory()->create(['mobile_e164' => '+971501111111', 'email' => 'omar@example.com']);
    DB::table('contact_phones')->insert(['contact_id' => $first->id, 'e164' => '+971501111111', 'type' => 1, 'is_primary' => true]);
    DB::table('contact_emails')->insert(['contact_id' => $first->id, 'email' => 'omar@example.com', 'is_primary' => true]);
    StagedRow::query()->where('import_batch_id', $batch->id)->update(['matched_contact_id' => $first->id, 'action' => 2, 'action_locked' => true]);

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit");

    expect($first->fresh()->source_ids)->toHaveCount(2)
        ->and($first->fresh()->source_count)->toBe(2);
});

it('creates no sources for a discarded file', function () {
    $batch = uploadAndAnalyse($this->admin, "Name,Mobile,Source\nOmar,0501111111,Never Used");

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/discard");

    expect(Source::query()->count())->toBe(0)
        ->and($batch->fresh()->status)->toBe(ImportBatchStatus::Discarded);
});

it('lets reviewers change the source of rows, one by one or in bulk', function () {
    $batch = uploadAndAnalyse($this->admin, "Name,Mobile\nOmar,0501111111\nSara,0502222222");
    $rows = StagedRow::query()->where('import_batch_id', $batch->id)->orderBy('row_number')->get();

    $this->actingAs($this->admin)
        ->patchJson("/imports/{$batch->id}/rows/{$rows[0]->id}", ['field' => 'source_name', 'value' => 'Walk-in customers'])
        ->assertOk()
        ->assertJsonPath('row.source_name', 'Walk-in customers');
    $this->actingAs($this->admin)
        ->post("/imports/{$batch->id}/rows/bulk", ['operation' => 'set_source', 'value' => 'Event leads', 'ids' => [$rows[1]->id]])
        ->assertRedirect();

    expect($rows[1]->fresh()->source_name)->toBe('Event leads');
});
