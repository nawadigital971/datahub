<?php

namespace App\DTOs\Imports;

use App\Http\Requests\Imports\ConfirmMappingRequest;

final readonly class ColumnMappingData
{
    /**
     * @param  array<string, string>  $columnMap  header => target
     */
    public function __construct(
        public array $columnMap,
        public ?int $templateId,
        public ?string $saveAsTemplate = null,
    ) {}

    public static function fromRequest(ConfirmMappingRequest $request): self
    {
        /** @var array<string, string> $columnMap */
        $columnMap = $request->validated('column_map');
        $templateId = $request->validated('mapping_template_id');
        $saveAs = $request->validated('save_as_template');

        return new self(
            $columnMap,
            is_numeric($templateId) ? (int) $templateId : null,
            is_string($saveAs) && trim($saveAs) !== '' ? trim($saveAs) : null,
        );
    }
}
