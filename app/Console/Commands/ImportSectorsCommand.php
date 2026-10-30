<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportSectorsAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\SlugLookupData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-sectors')]
#[Description('Import sectors from database/data/sectors.json')]
class ImportSectorsCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportSectorsAction $action): int
    {
        $rows = array_map(SlugLookupData::fromArray(...), $this->readReferenceData('sectors.json'));

        $this->components->info(sprintf('Imported %d sectors.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
