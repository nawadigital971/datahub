<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\EmailVerificationTask;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const VERIFIER_BALANCE_URL = 'https://emailverifier.reoon.com/api/v1/check-account-balance/*';
const VERIFIER_CREATE_URL = 'https://emailverifier.reoon.com/api/v1/create-bulk-verification-task/';

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
    config(['services.reoon.key' => 'test-key', 'services.reoon.daily_limit' => 1000]);
    Http::preventStrayRequests();
});

it('sends the requested number of emails from the Verify emails button', function () {
    Http::fake([VERIFIER_CREATE_URL => fn (Request $request) => Http::response([
        'status' => 'success', 'task_id' => 555, 'count_submitted' => count($request['emails']), 'count_processing' => count($request['emails']),
    ], 201)]);
    Contact::factory()->count(3)->create();

    $this->actingAs(User::factory()->create()->assignRole(Role::Admin->value))
        ->from('/health')
        ->post('/health/email-verifications', ['count' => 2])
        ->assertRedirect('/health')
        ->assertInertiaFlash('toast.message', 'Sent 2 emails to the verifier. Results arrive within about 10 minutes of it finishing.');

    expect(EmailVerificationTask::query()->sole()->emails)->toHaveCount(2);
});

it('shows the verifier refusal as an error toast', function () {
    Http::fake([VERIFIER_CREATE_URL => Http::response(['status' => 'error', 'reason' => 'Not enough credits'], 400)]);
    Contact::factory()->create();

    $this->actingAs(User::factory()->create()->assignRole(Role::Admin->value))
        ->from('/health')
        ->post('/health/email-verifications', ['count' => 5])
        ->assertRedirect('/health')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'The email verifier refused the task: Not enough credits']);

    expect(EmailVerificationTask::query()->exists())->toBeFalse();
});

it('rejects a count outside 1 to 50,000', function (int $count) {
    $this->actingAs(User::factory()->create()->assignRole(Role::Admin->value))
        ->post('/health/email-verifications', ['count' => $count])
        ->assertSessionHasErrors('count');

    Http::assertNothingSent();
})->with([0, 50001]);

it('forbids running verification without the permission', function () {
    $this->actingAs(User::factory()->create()->assignRole(Role::DataEditor->value))
        ->post('/health/email-verifications', ['count' => 10])
        ->assertForbidden();

    Http::assertNothingSent();
});

it('loads the credits and recent tasks on Data health for users who may verify', function () {
    Http::fake([VERIFIER_BALANCE_URL => Http::response(['api_status' => 'active', 'remaining_daily_credits' => 1200, 'remaining_instant_credits' => 40, 'status' => 'success'])]);
    EmailVerificationTask::factory()->create(['reoon_task_id' => '777']);

    $this->actingAs(User::factory()->create()->assignRole(Role::Admin->value))
        ->get('/health')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('email-verification', fn (Assert $reload) => $reload
                ->where('emailVerification.configured', true)
                ->where('emailVerification.balance', ['daily' => 1200, 'instant' => 40])
                ->where('emailVerification.tasks.0.reoon_task_id', '777')
                ->where('emailVerification.tasks.0.status', 'submitted')));
});

it('hides the verification panel from users without the permission', function () {
    $this->actingAs(User::factory()->create()->assignRole(Role::Viewer->value))
        ->get('/health')
        ->assertInertia(fn (Assert $page) => $page->where('emailVerification', null));

    Http::assertNothingSent();
});
