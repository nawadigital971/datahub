<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportSourceGroupsAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\SlugLookupData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-source-groups')]
#[Description('Import source groups from database/data/source_groups.json')]
class ImportSourceGroupsCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportSourceGroupsAction $action): int
    {
        $rows = array_map(SlugLookupData::fromArray(...), $this->readReferenceData('source_groups.json'));

        $this->components->info(sprintf('Imported %d source groups.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
