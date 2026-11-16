<?php

namespace App\Queries\Health;

use App\Actions\EmailVerification\CheckReoonBalanceAction;
use App\Enums\EmailVerificationTaskStatus;
use App\Exceptions\EmailVerifierException;
use App\Models\EmailVerificationTask;

/**
 * The email verifier panel on Data health: whether it is set up, the account's remaining credits
 * and the most recent tasks.
 *
 * @phpstan-type Task array{id: int, reoon_task_id: string|null, status: string, status_label: string, count: int, summary: array<string, int>|null, error: string|null, created_at: string|null, completed_at: string|null}
 */
class EmailVerificationStatusQuery
{
    public function __construct(private CheckReoonBalanceAction $balance) {}

    /**
     * @return array{configured: bool, daily_limit: int, balance: array{daily: int, instant: int}|null, balance_error: string|null, tasks: list<Task>}
     */
    public function handle(): array
    {
        $configured = filled(config('services.reoon.key'));
        $balance = null;
        $balanceError = null;

        if ($configured) {
            try {
                $balance = $this->balance->handle();
            } catch (EmailVerifierException $exception) {
                $balanceError = $exception->getMessage();
            }
        }

        $tasks = EmailVerificationTask::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'reoon_task_id', 'status', 'count_submitted', 'summary', 'error', 'created_at', 'completed_at'])
            ->map(fn (EmailVerificationTask $task): array => [
                'id' => $task->id,
                'reoon_task_id' => $task->reoon_task_id,
                'status' => strtolower($task->status->name),
                'status_label' => $task->status === EmailVerificationTaskStatus::Submitted ? 'Checking' : $task->status->label(),
                'count' => $task->count_submitted,
                'summary' => $task->summary,
                'error' => $task->error,
                'created_at' => $task->created_at?->toIso8601String(),
                'completed_at' => $task->completed_at?->toIso8601String(),
            ])
            ->all();

        return [
            'configured' => $configured,
            'daily_limit' => (int) config('services.reoon.daily_limit'),
            'balance' => $balance,
            'balance_error' => $balanceError,
            'tasks' => $tasks,
        ];
    }
}
