<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportRegionsAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\RegionData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-regions')]
#[Description('Import emirates/regions from database/data/regions.json')]
class ImportRegionsCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportRegionsAction $action): int
    {
        $rows = array_map(RegionData::fromArray(...), $this->readReferenceData('regions.json'));

        $this->components->info(sprintf('Imported %d regions.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
