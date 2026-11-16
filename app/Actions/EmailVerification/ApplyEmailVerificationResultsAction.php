<?php

namespace App\Actions\EmailVerification;

use App\Enums\EmailStatus;
use App\Models\EmailVerificationTask;
use Illuminate\Support\Facades\DB;

class ApplyEmailVerificationResultsAction
{
    /**
     * Write the verifier's results onto the task's contacts in set-based updates and return the count
     * per result. Emails the verifier did not return (rejected for bad format) count as "rejected".
     * A contact whose primary email changed since submission is left alone.
     *
     * @param  array<string, array{status?: string}>  $results  keyed by email
     * @return array<string, int>
     */
    public function handle(EmailVerificationTask $task, array $results): array
    {
        $results = array_change_key_case($results, CASE_LOWER);
        $summary = [];
        $rows = [];

        foreach ($task->emails as $email) {
            $result = substr(strtolower((string) ($results[strtolower($email)]['status'] ?? 'rejected')), 0, 20);
            $summary[$result] = ($summary[$result] ?? 0) + 1;
            $rows[] = [$email, $result, EmailStatus::fromVerifierResult($result)?->value];
        }

        $contactIds = '{'.implode(',', array_map('intval', $task->contact_ids)).'}';

        foreach (array_chunk($rows, 1000) as $chunk) {
            $values = implode(', ', array_fill(0, count($chunk), '(?::text, ?::text, ?::smallint)'));

            DB::update(<<<SQL
                UPDATE contacts c SET
                    email_check_result = v.result,
                    email_checked_at = now(),
                    email_status = coalesce(v.status, c.email_status),
                    updated_at = now()
                FROM (VALUES {$values}) AS v(email, result, status)
                WHERE c.id = ANY(?::bigint[]) AND c.email = v.email
                SQL, [...array_merge(...$chunk), $contactIds]);
        }

        ksort($summary);

        return $summary;
    }
}
