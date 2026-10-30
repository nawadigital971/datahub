<?php

namespace App\Queries\Imports;

use App\Actions\Imports\ReadImportFileAction;
use App\DTOs\Imports\MappedRow;
use App\Models\ImportBatch;
use Throwable;

/**
 * The first few data rows of an uploaded file, shown under each column on the mapping screen.
 */
class ImportFileSampleQuery
{
    public const int ROWS = 4;

    public function __construct(private ReadImportFileAction $read) {}

    /**
     * @return list<list<string>>
     */
    public function handle(ImportBatch $batch): array
    {
        $file = $batch->getFirstMedia('file');
        if ($file === null) {
            return [];
        }

        $samples = [];
        try {
            foreach ($this->read->handle($file->getPath(), pathinfo($file->file_name, PATHINFO_EXTENSION)) as $index => $cells) {
                if ($index === 0) {
                    continue;
                }
                $row = array_map(MappedRow::cell(...), $cells);
                if (array_filter($row) !== []) {
                    $samples[] = $row;
                }
                if (count($samples) >= self::ROWS) {
                    break;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $samples;
    }
}
