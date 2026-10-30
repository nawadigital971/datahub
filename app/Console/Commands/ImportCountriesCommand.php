<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportCountriesAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\CountryData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-countries')]
#[Description('Import countries from database/data/countries.json')]
class ImportCountriesCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportCountriesAction $action): int
    {
        $rows = array_map(CountryData::fromArray(...), $this->readReferenceData('countries.json'));

        $this->components->info(sprintf('Imported %d countries.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
