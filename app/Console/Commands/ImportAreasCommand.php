<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportAreasAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\AreaData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-areas')]
#[Description('Import areas (with Arabic/English aliases) from database/data/areas.json')]
class ImportAreasCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportAreasAction $action): int
    {
        $rows = array_map(AreaData::fromArray(...), $this->readReferenceData('areas.json'));

        $this->components->info(sprintf('Imported %d areas.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
