<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\CallOutcome;
use App\Enums\ContactEventType;
use App\Enums\Role;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ContactSource;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create(['name' => 'Sara Admin'])->assignRole(Role::Admin->value);

    $this->contact = Contact::factory()->create([
        'name_en' => 'Ahmad Ali',
        'name_ar' => 'أحمد علي',
        'other_names' => ['Abu Khalid'],
        'mobile_e164' => '+971501234567',
        'email' => 'ahmad@example.com',
    ]);
    DB::table('contact_phones')->insert([
        ['contact_id' => $this->contact->id, 'e164' => '+971501234567', 'type' => 1, 'is_primary' => true],
        ['contact_id' => $this->contact->id, 'e164' => '+97142223333', 'type' => 2, 'is_primary' => false],
    ]);
    DB::table('contact_emails')->insert([
        ['contact_id' => $this->contact->id, 'email' => 'ahmad@example.com', 'is_primary' => true],
        ['contact_id' => $this->contact->id, 'email' => 'abu.khalid@work.ae', 'is_primary' => false],
    ]);
    $batch = ImportBatch::factory()->create(['original_filename' => 'expo-2026.xlsx', 'headers' => ['Full Name', 'Mobile', 'Mail']]);
    ContactSource::query()->create([
        'contact_id' => $this->contact->id,
        'source_id' => Source::query()->value('id'),
        'import_batch_id' => $batch->id,
        'row_number' => 12,
        'seen_year' => 2026,
        'raw' => ['Full Name' => 'Abu Khalid', 'Mobile' => '050 123 4567', 'Mail' => 'abu.khalid@work.ae'],
    ]);
    ContactEvent::query()->create([
        'contact_id' => $this->contact->id, 'type' => ContactEventType::Edit, 'occurred_at' => now(), 'user_id' => $this->admin->id,
        'meta' => ['field' => 'name_en', 'from' => 'Ahmed Ali', 'to' => 'Ahmad Ali', 'via' => 'mcp'],
    ]);
    ContactEvent::query()->create([
        'contact_id' => $this->contact->id, 'type' => ContactEventType::Call, 'outcome' => CallOutcome::CallBack, 'occurred_at' => now()->subDay(),
        'meta' => ['notes' => 'Call again on 0509998877'],
    ]);
});

it('shows every name, phone, email, source row and history entry', function () {
    $this->actingAs($this->admin)->getJson("/contacts/{$this->contact->id}")
        ->assertOk()
        ->assertJsonPath('other_names', ['Abu Khalid'])
        ->assertJsonPath('masked', false)
        ->assertJsonPath('phones.0', ['value' => '+971501234567', 'type' => 'Mobile', 'is_primary' => true])
        ->assertJsonPath('phones.1.value', '+97142223333')
        ->assertJsonPath('emails.1.value', 'abu.khalid@work.ae')
        ->assertJsonPath('sources.0.file', 'expo-2026.xlsx')
        ->assertJsonPath('sources.0.row_number', 12)
        ->assertJsonPath('sources.0.raw.1', ['column' => 'Mobile', 'value' => '050 123 4567'])
        ->assertJsonPath('history.0.text', 'English name: Ahmed Ali → Ahmad Ali (via Claude)')
        ->assertJsonPath('history.0.by', 'Sara Admin')
        ->assertJsonPath('history.1.text', 'Call back — Call again on 0509998877');
});

it('masks phones and emails everywhere for users who may not see them', function () {
    $viewer = User::factory()->create()->assignRole(Role::Viewer->value);

    $response = $this->actingAs($viewer)->getJson("/contacts/{$this->contact->id}")
        ->assertOk()
        ->assertJsonPath('masked', true)
        ->assertJsonPath('phones.0.value', ContactResource::maskPhone('+971501234567'))
        ->assertJsonPath('emails.1.value', 'ab•••@work.ae')
        ->assertJsonPath('sources.0.raw.0.value', 'Abu Khalid')
        ->assertJsonPath('sources.0.raw.1.value', '050 ••• ••67')
        ->assertJsonPath('history.1.text', 'Call back — Call again on 050•••••77');

    expect($response->getContent())->not->toContain('1234567')
        ->not->toContain('abu.khalid@')
        ->not->toContain('9998877');
});

it('is not available without contacts.view', function () {
    $this->actingAs(User::factory()->create())->getJson("/contacts/{$this->contact->id}")->assertForbidden();
});
