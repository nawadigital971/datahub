<?php

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPermissionsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-permissions')]
#[Description('Sync permissions from the Permission enum and create any missing roles')]
class SyncPermissionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissionsAction $action): int
    {
        $result = $action->handle();

        $this->components->info(sprintf(
            'Permissions synced: %d created, %d deleted. Roles created: %s.',
            count($result['created']),
            count($result['deleted']),
            $result['roles_created'] === [] ? 'none' : implode(', ', $result['roles_created']),
        ));

        return self::SUCCESS;
    }
}
