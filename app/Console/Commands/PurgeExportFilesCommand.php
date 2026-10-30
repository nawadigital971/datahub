<?php

namespace App\Console\Commands;

use App\Actions\Exports\PurgeExpiredExportFilesAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:purge-exports')]
#[Description('Delete export files older than the export retention period (the export log is kept)')]
class PurgeExportFilesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurgeExpiredExportFilesAction $action): int
    {
        $this->components->info(sprintf('Deleted %d expired export files.', $action->handle()));

        return self::SUCCESS;
    }
}
