<?php

namespace App\Actions\Imports;

use App\DTOs\Imports\ImportUploadData;
use App\DTOs\Imports\MappedRow;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Stores an upload, reads its header row and proposes a column mapping. The batch then waits on the
 * mapping screen; analysis starts when the mapping is confirmed (ConfirmMappingAction).
 */
class CreateImportBatchAction
{
    public function __construct(
        private ReadImportFileAction $read,
        private SuggestColumnMapAction $suggest,
    ) {}

    public function handle(ImportUploadData $data): ImportBatch
    {
        $headers = $this->headers($data);
        $suggestion = $this->suggest->handle($headers);

        return DB::transaction(function () use ($data, $headers, $suggestion): ImportBatch {
            $batch = ImportBatch::query()->create([
                'source_id' => $data->sourceId,
                'source_name' => $data->sourceId === null ? ($data->sourceName ?? $this->nameFromFile($data->file->getClientOriginalName())) : null,
                'origin' => ImportOrigin::Upload,
                'user_id' => $data->userId,
                'mapping_template_id' => $suggestion['template']?->id,
                'status' => ImportBatchStatus::Mapping,
                'original_filename' => $data->file->getClientOriginalName(),
                'headers' => $headers,
                'column_map' => $suggestion['column_map'],
            ]);

            $batch->addMedia($data->file)
                ->usingFileName($this->safeFileName($data->file->getClientOriginalName()))
                ->toMediaCollection('file');

            return $batch;
        });
    }

    /**
     * The first non-empty row, with blank and repeated headers made unique.
     *
     * @return list<string>
     */
    private function headers(ImportUploadData $data): array
    {
        try {
            foreach ($this->read->handle($data->file->getRealPath(), $data->file->getClientOriginalExtension()) as $cells) {
                $cells = array_map(MappedRow::cell(...), $cells);
                if (array_filter($cells, fn (string $cell): bool => $cell !== '') === []) {
                    continue;
                }

                while ($cells !== [] && end($cells) === '') {
                    array_pop($cells);
                }

                $headers = [];
                foreach ($cells as $index => $cell) {
                    $header = $cell === '' ? 'Column '.($index + 1) : ltrim($cell, "\u{FEFF}");
                    $unique = $header;
                    for ($n = 2; in_array($unique, $headers, true); $n++) {
                        $unique = "{$header} ({$n})";
                    }
                    $headers[] = $unique;
                }

                return $headers;
            }
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        throw ValidationException::withMessages(['file' => 'The file looks empty: no header row was found.']);
    }

    /**
     * "Layalina_AD_Subscribers 2013.xlsx" → "Layalina AD Subscribers 2013" (default source for rows without one).
     */
    private function nameFromFile(string $name): string
    {
        $base = trim((string) preg_replace('/[\s_\-.]+/u', ' ', pathinfo($name, PATHINFO_FILENAME)));

        return mb_substr($base === '' ? 'Imported file' : $base, 0, 120);
    }

    private function safeFileName(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = (string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', pathinfo($name, PATHINFO_FILENAME));

        return mb_substr(trim($base) === '' ? 'import' : trim($base), 0, 120).'.'.$extension;
    }
}
