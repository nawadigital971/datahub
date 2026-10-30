<?php

namespace App\Actions\ReferenceData;

use App\DTOs\ReferenceData\MappingTemplateData;
use App\Models\MappingTemplate;
use Illuminate\Support\Facades\DB;

class ImportMappingTemplatesAction
{
    /**
     * Add the known spreadsheet layouts that are missing (by name); existing ones are never overwritten.
     *
     * @param  list<MappingTemplateData>  $templates
     */
    public function handle(array $templates): int
    {
        DB::transaction(function () use ($templates): void {
            foreach ($templates as $template) {
                MappingTemplate::query()->firstOrCreate(
                    ['name' => $template->name],
                    ['layout' => $template->layout, 'column_map' => $template->columnMap],
                );
            }
        });

        return count($templates);
    }
}
