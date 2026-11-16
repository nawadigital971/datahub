<?php

use App\Actions\EmailVerification\SubmitEmailVerificationTaskAction;
use App\DTOs\EmailVerificationData;
use App\Enums\EmailStatus;
use App\Enums\EmailVerificationTaskStatus;
use App\Exceptions\EmailVerifierException;
use App\Models\Contact;
use App\Models\EmailVerificationTask;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const REOON_BALANCE_URL = 'https://emailverifier.reoon.com/api/v1/check-account-balance/*';
const REOON_CREATE_URL = 'https://emailverifier.reoon.com/api/v1/create-bulk-verification-task/';

beforeEach(function () {
    config(['services.reoon.key' => 'test-key', 'services.reoon.daily_limit' => 1000]);
    Http::preventStrayRequests();
});

function fakeReoonSubmission(int $dailyCredits): void
{
    Http::fake([
        REOON_BALANCE_URL => Http::response(['api_status' => 'active', 'remaining_daily_credits' => $dailyCredits, 'remaining_instant_credits' => 5000, 'status' => 'success']),
        REOON_CREATE_URL => fn (Request $request) => Http::response([
            'status' => 'success', 'task_id' => 40676, 'count_submitted' => count($request['emails']),
            'count_duplicates_removed' => 0, 'count_rejected_emails' => 0, 'count_processing' => count($request['emails']),
        ], 201),
    ]);
}

it('submits only unchecked, contactable emails and records the task', function () {
    fakeReoonSubmission(1200);
    $unchecked = Contact::factory()->create(['email' => 'new@example.com']);
    Contact::factory()->create(['email' => 'done@example.com', 'email_checked_at' => now()]);
    Contact::factory()->doNotContact()->create(['email' => 'dnc@example.com']);
    Contact::factory()->create(['email' => 'junk@example.com', 'is_junk' => true]);
    Contact::factory()->create(['email' => 'bad@example.com', 'email_status' => EmailStatus::Invalid]);
    Contact::factory()->create(['email' => null]);
    $inFlight = Contact::factory()->create(['email' => 'pending@example.com']);
    EmailVerificationTask::factory()->create(['emails' => ['pending@example.com'], 'contact_ids' => [$inFlight->id]]);

    $task = app(SubmitEmailVerificationTaskAction::class)->handle(EmailVerificationData::fromArray([]));

    expect($task->reoon_task_id)->toBe('40676')
        ->and($task->status)->toBe(EmailVerificationTaskStatus::Submitted)
        ->and($task->emails)->toBe(['new@example.com'])
        ->and($task->contact_ids)->toBe([$unchecked->id]);
    Http::assertSent(fn (Request $request) => $request->url() === REOON_CREATE_URL
        && $request['key'] === 'test-key' && $request['emails'] === ['new@example.com']);
});

it('caps the task at the daily limit and at the remaining daily credits', function (int $dailyLimit, int $credits, int $expected) {
    config(['services.reoon.daily_limit' => $dailyLimit]);
    fakeReoonSubmission($credits);
    Contact::factory()->count(5)->create();

    $task = app(SubmitEmailVerificationTaskAction::class)->handle(EmailVerificationData::fromArray([]));

    expect($task->emails)->toHaveCount($expected);
})->with([
    'daily limit is lower' => [3, 1200, 3],
    'credits are lower' => [1000, 2, 2],
]);

it('sends nothing when no daily credits are left', function () {
    fakeReoonSubmission(0);
    Contact::factory()->create();

    expect(app(SubmitEmailVerificationTaskAction::class)->handle(EmailVerificationData::fromArray([])))->toBeNull()
        ->and(EmailVerificationTask::query()->exists())->toBeFalse();
    Http::assertNotSent(fn (Request $request) => $request->url() === REOON_CREATE_URL);
});

it('calls nothing when no API key is configured', function () {
    config(['services.reoon.key' => null]);
    Contact::factory()->create();

    expect(app(SubmitEmailVerificationTaskAction::class)->handle(EmailVerificationData::fromArray([])))->toBeNull();
    Http::assertNothingSent();
});

it('throws and records nothing when the verifier refuses the task', function () {
    Http::fake([
        REOON_BALANCE_URL => Http::response(['api_status' => 'active', 'remaining_daily_credits' => 1200, 'status' => 'success']),
        REOON_CREATE_URL => Http::response(['status' => 'error', 'reason' => 'Invalid API key'], 400),
    ]);
    Contact::factory()->create();

    expect(fn () => app(SubmitEmailVerificationTaskAction::class)->handle(EmailVerificationData::fromArray([])))->toThrow(EmailVerifierException::class, 'Invalid API key')
        ->and(EmailVerificationTask::query()->exists())->toBeFalse();
});

it('sends exactly the requested count on a manual run, past the daily limit and without checking credits', function () {
    config(['services.reoon.daily_limit' => 1]);
    fakeReoonSubmission(0);
    Contact::factory()->count(4)->create();

    $task = app(SubmitEmailVerificationTaskAction::class)->handle(new EmailVerificationData(3));

    expect($task->emails)->toHaveCount(3);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'check-account-balance'));
});

it('refuses a manual run when no API key is configured', function () {
    config(['services.reoon.key' => null]);
    Contact::factory()->create();

    expect(fn () => app(SubmitEmailVerificationTaskAction::class)->handle(new EmailVerificationData(10)))
        ->toThrow(EmailVerifierException::class, 'REOON_API_KEY');
    Http::assertNothingSent();
});

it('reports the submitted count from the daily command', function () {
    fakeReoonSubmission(1200);
    Contact::factory()->count(2)->create();

    $this->artisan('app:verify-emails')
        ->expectsOutputToContain('Submitted 2 emails to the email verifier (task 40676).')
        ->assertSuccessful();
});
