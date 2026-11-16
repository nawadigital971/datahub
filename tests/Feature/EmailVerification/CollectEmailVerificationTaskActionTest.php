<?php

use App\Actions\EmailVerification\CollectEmailVerificationTaskAction;
use App\Enums\EmailStatus;
use App\Enums\EmailVerificationTaskStatus;
use App\Jobs\RefreshFacetCountsJob;
use App\Models\Contact;
use App\Models\EmailVerificationTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const REOON_RESULT_URL = 'https://emailverifier.reoon.com/api/v1/get-result-bulk-verification-task/*';

beforeEach(function () {
    config(['services.reoon.key' => 'test-key']);
    Http::preventStrayRequests();
});

/**
 * A submitted task covering one new contact per email.
 *
 * @param  list<string>  $emails
 */
function verificationTaskFor(array $emails): EmailVerificationTask
{
    $contacts = collect($emails)->map(fn (string $email) => Contact::factory()->create(['email' => $email, 'email_status' => EmailStatus::Unknown]));

    return EmailVerificationTask::factory()->create(['reoon_task_id' => '40676', 'emails' => $emails, 'contact_ids' => $contacts->pluck('id')->all()]);
}

it('applies every verifier result to the contacts when the task completes', function () {
    Queue::fake([RefreshFacetCountsJob::class]);
    freezeTime();
    $results = [
        'safe@example.com' => 'safe',
        'invalid@example.com' => 'invalid',
        'disposable@example.com' => 'disposable',
        'catchall@example.com' => 'catch_all',
        'role@example.com' => 'role_account',
        'unknown@example.com' => 'unknown',
    ];
    $task = verificationTaskFor([...array_keys($results), 'rejected@example.com']);
    Http::fake([REOON_RESULT_URL => Http::response([
        'status' => 'completed', 'task_id' => '40676', 'count_total' => 7, 'count_checked' => 6, 'progress_percentage' => 100.0,
        'results' => collect($results)->mapWithKeys(fn (string $status, string $email) => [$email => ['email' => $email, 'status' => $status]])->all(),
    ])]);

    $status = app(CollectEmailVerificationTaskAction::class)->handle($task);

    expect($status)->toBe(EmailVerificationTaskStatus::Completed)
        ->and(Contact::query()->pluck('email_status', 'email')->map->name->all())->toEqual([
            'safe@example.com' => 'Valid',
            'invalid@example.com' => 'Invalid',
            'disposable@example.com' => 'Invalid',
            'catchall@example.com' => 'Risky',
            'role@example.com' => 'Risky',
            'unknown@example.com' => 'Unknown',
            'rejected@example.com' => 'Invalid',
        ])
        ->and(Contact::query()->where('email', 'rejected@example.com')->sole()->email_check_result)->toBe('rejected')
        ->and(Contact::query()->whereNull('email_checked_at')->exists())->toBeFalse()
        ->and($task->fresh()->summary)->toEqual(['catch_all' => 1, 'disposable' => 1, 'invalid' => 1, 'rejected' => 1, 'role_account' => 1, 'safe' => 1, 'unknown' => 1]);
    Queue::assertPushed(RefreshFacetCountsJob::class);
    Http::assertSent(fn ($request) => $request['key'] === 'test-key' && $request['task_id'] === '40676');
});

it('leaves a contact alone when its email changed after submission', function () {
    Queue::fake([RefreshFacetCountsJob::class]);
    $task = verificationTaskFor(['old@example.com']);
    $contact = Contact::query()->sole();
    $contact->update(['email' => 'new@example.com']);
    Http::fake([REOON_RESULT_URL => Http::response([
        'status' => 'completed', 'results' => ['old@example.com' => ['status' => 'invalid']],
    ])]);

    app(CollectEmailVerificationTaskAction::class)->handle($task);

    expect($contact->fresh()->email_status)->toBe(EmailStatus::Unknown)
        ->and($contact->fresh()->email_checked_at)->toBeNull();
});

it('keeps a running task open', function () {
    $task = verificationTaskFor(['a@example.com']);
    Http::fake([REOON_RESULT_URL => Http::response(['status' => 'running', 'count_total' => 1, 'count_checked' => 0])]);

    expect(app(CollectEmailVerificationTaskAction::class)->handle($task))->toBe(EmailVerificationTaskStatus::Submitted)
        ->and(Contact::query()->sole()->email_checked_at)->toBeNull();
});

it('gives up on a task still running after three days so its emails can be picked again', function () {
    $task = verificationTaskFor(['a@example.com']);
    $this->travel(CollectEmailVerificationTaskAction::STALE_AFTER_DAYS + 1)->days();
    Http::fake([REOON_RESULT_URL => Http::response(['status' => 'waiting'])]);

    expect(app(CollectEmailVerificationTaskAction::class)->handle($task))->toBe(EmailVerificationTaskStatus::Failed)
        ->and($task->fresh()->error)->toContain('Still waiting');
});

it('fails a task the verifier reports as broken', function () {
    $task = verificationTaskFor(['a@example.com']);
    Http::fake([REOON_RESULT_URL => Http::response(['status' => 'file_not_found'])]);

    expect(app(CollectEmailVerificationTaskAction::class)->handle($task))->toBe(EmailVerificationTaskStatus::Failed)
        ->and($task->fresh()->error)->toBe('file_not_found');
});

it('collects only submitted tasks from the scheduled command', function () {
    $task = verificationTaskFor(['a@example.com']);
    EmailVerificationTask::factory()->create(['reoon_task_id' => '1', 'status' => EmailVerificationTaskStatus::Completed]);
    Http::fake([REOON_RESULT_URL => Http::response(['status' => 'running'])]);

    $this->artisan('app:collect-email-verifications')
        ->expectsOutputToContain('Task 40676: submitted.')
        ->assertSuccessful();

    Http::assertSentCount(1);
});
