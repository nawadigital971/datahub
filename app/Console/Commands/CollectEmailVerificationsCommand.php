<?php

namespace App\Console\Commands;

use App\Actions\EmailVerification\CollectEmailVerificationTaskAction;
use App\Enums\EmailVerificationTaskStatus;
use App\Models\EmailVerificationTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:collect-email-verifications')]
#[Description('Fetch finished email verifier tasks and apply their results to contacts')]
class CollectEmailVerificationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CollectEmailVerificationTaskAction $action): int
    {
        $tasks = EmailVerificationTask::query()
            ->where('status', EmailVerificationTaskStatus::Submitted)
            ->oldest('id')
            ->get();

        foreach ($tasks as $task) {
            $status = $action->handle($task);
            $this->components->info(sprintf('Task %s: %s.', $task->reoon_task_id, strtolower($status->label())));
        }

        return self::SUCCESS;
    }
}
