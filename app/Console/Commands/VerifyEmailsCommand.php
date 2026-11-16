<?php

namespace App\Console\Commands;

use App\Actions\EmailVerification\SubmitEmailVerificationTaskAction;
use App\DTOs\EmailVerificationData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:verify-emails')]
#[Description('Send the next unchecked contact emails (up to REOON_DAILY_LIMIT) to the email verifier')]
class VerifyEmailsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SubmitEmailVerificationTaskAction $action): int
    {
        $task = $action->handle(EmailVerificationData::fromArray([]));

        if ($task === null) {
            $this->components->info('Nothing submitted (no API key, no daily credits left, or no unchecked emails).');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Submitted %d emails to the email verifier (task %s).', count($task->emails), $task->reoon_task_id));

        return self::SUCCESS;
    }
}
