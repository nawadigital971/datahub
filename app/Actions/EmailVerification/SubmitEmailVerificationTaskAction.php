<?php

namespace App\Actions\EmailVerification;

use App\Enums\EmailStatus;
use App\Enums\EmailVerificationTaskStatus;
use App\Models\EmailVerificationTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitEmailVerificationTaskAction
{
    public function __construct(private CheckReoonBalanceAction $balance) {}

    /**
     * Send the next unchecked contact emails (up to the daily limit and the remaining daily credits)
     * to the email verifier as one bulk task. Returns null when there is nothing to send.
     *
     * @throws RuntimeException
     */
    public function handle(): ?EmailVerificationTask
    {
        if (blank(config('services.reoon.key'))) {
            Log::info('Email verification skipped: REOON_API_KEY is not set.');

            return null;
        }

        $limit = min((int) config('services.reoon.daily_limit'), $this->balance->handle());

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

        $response = Http::baseUrl(config('services.reoon.url'))
            ->acceptJson()
            ->timeout(120)
            ->post('create-bulk-verification-task/', [
                'name' => 'NAWAA '.now()->format('Y-m-d H:i'),
                'emails' => $emails,
                'key' => config('services.reoon.key'),
            ]);

        if ($response->status() !== 201 || $response->json('status') !== 'success') {
            throw new RuntimeException('Email verifier task creation failed: '.($response->json('reason') ?? $response->body()));
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
