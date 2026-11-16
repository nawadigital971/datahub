<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\EmailStatus;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
    $this->contact = Contact::factory()->create([
        'mobile_e164' => '+971501234567', 'email' => 'old@example.com',
        'email_status' => EmailStatus::Invalid, 'email_checked_at' => now(), 'email_check_result' => 'invalid',
    ]);
    DB::table('contact_phones')->insert(['contact_id' => $this->contact->id, 'e164' => '+971501234567', 'type' => 1, 'is_primary' => true]);
    DB::table('contact_emails')->insert(['contact_id' => $this->contact->id, 'email' => 'old@example.com', 'is_primary' => true]);
});

/**
 * Stage one row with the contact's phone and the given email, take the incoming email and commit.
 */
function commitIncomingEmail(User $user, Contact $contact, string $email): void
{
    $csv = "English Full Name,Mobile,Email\nAhmad Ali,0501234567,{$email}";
    test()->actingAs($user)->post('/imports', ['source_name' => 'Expo 2026', 'file' => UploadedFile::fake()->createWithContent('expo.csv', $csv)])->assertRedirect();
    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    test()->actingAs($user)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map])->assertSessionHasNoErrors();
    $row = StagedRow::query()->where('import_batch_id', $batch->id)->sole();
    test()->actingAs($user)
        ->putJson("/imports/{$batch->id}/rows/{$row->id}/merge", ['contact_id' => $contact->id, 'choices' => ['email' => 'incoming']])
        ->assertSuccessful();
    test()->actingAs($user)->post("/imports/{$batch->id}/commit")->assertRedirect();
}

it('clears the verifier result when a merge replaces the primary email', function () {
    commitIncomingEmail($this->admin, $this->contact, 'new@example.com');

    $contact = $this->contact->fresh();
    expect($contact->email)->toBe('new@example.com')
        ->and($contact->email_status)->toBe(EmailStatus::Valid)
        ->and($contact->email_checked_at)->toBeNull()
        ->and($contact->email_check_result)->toBeNull();
});

it('keeps the verifier result when the incoming email is the same address', function () {
    commitIncomingEmail($this->admin, $this->contact, 'old@example.com');

    $contact = $this->contact->fresh();
    expect($contact->email_status)->toBe(EmailStatus::Invalid)
        ->and($contact->email_check_result)->toBe('invalid')
        ->and($contact->email_checked_at)->not->toBeNull();
});
