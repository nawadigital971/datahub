<?php

namespace App\DTOs\Imports;

use App\Http\Requests\Imports\StoreImportBatchRequest;
use Illuminate\Http\UploadedFile;

final readonly class ImportUploadData
{
    /**
     * @param  int|null  $sourceId  Existing source for rows without a Source column value.
     * @param  string|null  $sourceName  Or the name of a new source for those rows (created at commit).
     */
    public function __construct(
        public int $userId,
        public ?int $sourceId,
        public ?string $sourceName,
        public UploadedFile $file,
    ) {}

    public static function fromRequest(StoreImportBatchRequest $request): self
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        $sourceName = trim($request->string('source_name')->toString());

        return new self(
            (int) $request->user()?->id,
            $request->filled('source_id') ? $request->integer('source_id') : null,
            $sourceName === '' ? null : $sourceName,
            $file,
        );
    }
}
