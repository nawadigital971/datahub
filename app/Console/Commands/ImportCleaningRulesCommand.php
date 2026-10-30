<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportCleaningRulesAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\CleaningRuleData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-cleaning-rules')]
#[Description('Add the starter cleaning rules from database/data/cleaning_rules.json that are missing')]
class ImportCleaningRulesCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportCleaningRulesAction $action): int
    {
        $rows = array_map(CleaningRuleData::fromArray(...), $this->readReferenceData('cleaning_rules.json'));

        $this->components->info(sprintf('Added %d cleaning rules.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
