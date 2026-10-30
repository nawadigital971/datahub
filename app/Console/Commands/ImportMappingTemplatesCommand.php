<?php

namespace App\Console\Commands;

use App\Actions\ReferenceData\ImportMappingTemplatesAction;
use App\Console\Commands\Concerns\ReadsReferenceData;
use App\DTOs\ReferenceData\MappingTemplateData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-mapping-templates')]
#[Description('Import spreadsheet mapping templates from database/data/mapping_templates.json')]
class ImportMappingTemplatesCommand extends Command
{
    use ReadsReferenceData;

    /**
     * Execute the console command.
     */
    public function handle(ImportMappingTemplatesAction $action): int
    {
        $rows = array_map(MappingTemplateData::fromArray(...), $this->readReferenceData('mapping_templates.json'));

        $this->components->info(sprintf('Imported %d mapping templates.', $action->handle($rows)));

        return self::SUCCESS;
    }
}
