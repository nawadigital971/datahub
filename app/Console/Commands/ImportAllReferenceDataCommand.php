<?php

namespace App\Console\Commands;

use App\Actions\Contacts\BustAudienceCacheAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-all')]
#[Description('Import all reference data (countries, regions, areas, sectors, source groups, mapping templates) in dependency order')]
class ImportAllReferenceDataCommand extends Command
{
    /**
     * Commands in the order their foreign keys need.
     *
     * @var list<string>
     */
    private const array COMMANDS = [
        'app:import-countries',
        'app:import-regions',
        'app:import-areas',
        'app:import-sectors',
        'app:import-source-groups',
        'app:import-mapping-templates',
        'app:import-cleaning-rules',
    ];

    /**
     * Execute the console command.
     */
    public function handle(BustAudienceCacheAction $bustAudienceCache): int
    {
        foreach (self::COMMANDS as $command) {
            if ($this->call($command) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $bustAudienceCache->handle();

        return self::SUCCESS;
    }
}
