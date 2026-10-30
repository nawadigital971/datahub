<?php

namespace App\Console\Commands;

use App\Actions\Imports\PurgeStaleImportsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:purge-imports')]
#[Description('Delete staged rows and files of discarded or failed import batches past the retention period')]
class PurgeStaleImportsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurgeStaleImportsAction $action): int
    {
        $this->components->info(sprintf('Purged %d import batches.', $action->handle()));

        return self::SUCCESS;
    }
}
