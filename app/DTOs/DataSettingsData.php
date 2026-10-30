<?php

namespace App\DTOs;

use App\Http\Requests\Admin\UpdateDataSettingsRequest;

final readonly class DataSettingsData
{
    public function __construct(
        public int $staleDataYearThreshold,
        public bool $hashOnExportDefault,
        public int $exportRetentionDays,
        public int $stagedRetentionDays,
        public string $defaultPhoneRegion,
    ) {}

    public static function fromRequest(UpdateDataSettingsRequest $request): self
    {
        return new self(
            $request->integer('stale_data_year_threshold'),
            $request->boolean('hash_on_export_default'),
            $request->integer('export_retention_days'),
            $request->integer('staged_retention_days'),
            strtoupper($request->string('default_phone_region')->toString()),
        );
    }
}
