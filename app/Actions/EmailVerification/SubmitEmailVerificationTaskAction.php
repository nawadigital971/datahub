<?php

namespace App\Actions\EmailVerification;

use App\DTOs\EmailVerificationData;
use App\Enums\EmailStatus;
use App\Enums\EmailVerificationTaskStatus;
use App\Exceptions\EmailVerifierException;
use App\Models\EmailVerificationTask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubmitEmailVerificationTaskAction
{
    /** The verifier accepts up to this many emails in one bulk task. */
    public const int MAX_PER_TASK = 50000;

    public function __construct(private CheckReoonBalanceAction $balance) {}

    /**
     * Send the next unchecked contact emails to the email verifier as one bulk task. The scheduled run
     * sends up to the daily limit and never more than the remaining daily credits; a manual run sends
     * exactly the requested count. Returns null when there is nothing to send.
     *
     * @throws EmailVerifierException
     */
    public function handle(EmailVerificationData $data): ?EmailVerificationTask
    {
        if (blank(config('services.reoon.key'))) {
            if ($data->isManual()) {
                throw new EmailVerifierException('The email verifier is not set up: add REOON_API_KEY to the .env file.');
            }
            Log::info('Email verification skipped: REOON_API_KEY is not set.');

            return null;
        }

        $limit = $data->isManual()
            ? min($data->count, self::MAX_PER_TASK)
            : min((int) config('services.reoon.daily_limit'), $this->balance->handle()['daily']);

        if ($limit <= 0) {
            Log::info('Email verification skipped: no daily credits left.');

            return null;
        }

        $picked = DB::select(<<<'SQL'
            SELECT c.id, c.email
            FROM contacts c
            WHERE c.email IS NOT NULL
              AND c.email_checked_at IS NULL
              AND c.do_not_contact = false
              AND c.is_junk = false
              AND c.email_status <> ?
              AND c.id NOT IN (
                  SELECT jsonb_array_elements_text(t.contact_ids)::bigint
                  FROM email_verification_tasks t
                  WHERE t.status = ?
              )
            ORDER BY c.id
            LIMIT ?
            SQL, [EmailStatus::Invalid->value, EmailVerificationTaskStatus::Submitted->value, $limit]);

        if ($picked === []) {
            return null;
        }

        $emails = array_values(array_unique(array_map(fn (object $row): string => $row->email, $picked)));

        try {
            $response = Http::baseUrl(config('services.reoon.url'))
                ->acceptJson()
                ->timeout(300)
                ->post('create-bulk-verification-task/', [
                    'name' => 'NAWAA '.now()->format('Y-m-d H:i'),
                    'emails' => $emails,
                    'key' => config('services.reoon.key'),
                ]);
        } catch (ConnectionException $exception) {
            throw new EmailVerifierException('Could not reach the email verifier: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->status() !== 201 || $response->json('status') !== 'success') {
            throw new EmailVerifierException('The email verifier refused the task: '.($response->json('reason') ?? $response->body()));
        }

        return EmailVerificationTask::query()->create([
            'reoon_task_id' => (string) $response->json('task_id'),
            'status' => EmailVerificationTaskStatus::Submitted,
            'emails' => $emails,
            'contact_ids' => array_map(fn (object $row): int => (int) $row->id, $picked),
            'count_submitted' => (int) $response->json('count_submitted', count($emails)),
            'count_processing' => (int) $response->json('count_processing', count($emails)),
        ]);
    }
}
