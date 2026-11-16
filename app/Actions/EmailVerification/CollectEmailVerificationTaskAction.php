<?php

namespace App\Actions\EmailVerification;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Enums\EmailVerificationTaskStatus;
use App\Jobs\RefreshFacetCountsJob;
use App\Models\EmailVerificationTask;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CollectEmailVerificationTaskAction
{
    /** A task still unfinished after this many days is given up; its emails become pickable again. */
    public const int STALE_AFTER_DAYS = 3;

    public function __construct(
        private ApplyEmailVerificationResultsAction $apply,
        private BustAudienceCacheAction $bustAudienceCache,
    ) {}

    /**
     * Poll one submitted task; when the verifier has finished, apply its results to the contacts.
     */
    public function handle(EmailVerificationTask $task): EmailVerificationTaskStatus
    {
        try {
            $response = Http::baseUrl(config('services.reoon.url'))
                ->acceptJson()
                ->timeout(120)
                ->retry(2, 2000, throw: false)
                ->get('get-result-bulk-verification-task/', [
                    'key' => config('services.reoon.key'),
                    'task_id' => $task->reoon_task_id,
                ]);
        } catch (ConnectionException $exception) {
            return $this->keepWaitingOrFail($task, $exception->getMessage());
        }

        $status = $response->json('status');

        if ($response->successful() && $status === 'completed' && is_array($response->json('results'))) {
            $summary = $this->apply->handle($task, $response->json('results'));

            $task->update([
                'status' => EmailVerificationTaskStatus::Completed,
                'summary' => $summary,
                'completed_at' => now(),
            ]);

            $this->bustAudienceCache->handle();
            RefreshFacetCountsJob::dispatch();

            return $task->status;
        }

        if ($response->successful() && in_array($status, ['waiting', 'running'], true)) {
            return $this->keepWaitingOrFail($task, 'Still '.$status.' after '.self::STALE_AFTER_DAYS.' days.');
        }

        if ($response->serverError()) {
            return $this->keepWaitingOrFail($task, 'Verifier server error '.$response->status().'.');
        }

        return $this->fail($task, (string) ($response->json('reason') ?? $status ?? $response->body()));
    }

    /**
     * Keep a young task open; give up on one older than the stale limit.
     */
    private function keepWaitingOrFail(EmailVerificationTask $task, string $reason): EmailVerificationTaskStatus
    {
        if ($task->created_at->lt(now()->subDays(self::STALE_AFTER_DAYS))) {
            return $this->fail($task, $reason);
        }

        return $task->status;
    }

    private function fail(EmailVerificationTask $task, string $reason): EmailVerificationTaskStatus
    {
        $task->update([
            'status' => EmailVerificationTaskStatus::Failed,
            'error' => substr($reason, 0, 1000),
            'completed_at' => now(),
        ]);

        return $task->status;
    }
}
