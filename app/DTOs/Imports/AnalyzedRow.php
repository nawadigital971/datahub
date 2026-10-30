<?php

namespace App\DTOs\Imports;

use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\IssueCode;
use App\Enums\RowSeverity;

/**
 * The parsed, editable columns of a staged row plus the problems found in it.
 */
final readonly class AnalyzedRow
{
    /** Column order used for COPY into staged_rows (import_batch_id, row_number and raw come first). */
    public const array COPY_COLUMNS = [
        'import_batch_id', 'row_number', 'raw', 'source_name', 'type', 'name_en', 'name_ar', 'company_name', 'gender',
        'nationality_country_id', 'mobile_e164', 'landline_e164', 'email', 'country_id', 'region_id', 'area_id',
        'sector_id', 'address_raw', 'seen_year', 'call_outcome', 'extra', 'issue_codes', 'issues', 'severity',
    ];

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<Issue>  $issues
     */
    public function __construct(
        public ContactType $type,
        public ?string $nameEn,
        public ?string $nameAr,
        public ?string $company,
        public ?Gender $gender,
        public ?int $nationalityCountryId,
        public ?string $mobile,
        public ?string $landline,
        public ?string $email,
        public ?int $countryId,
        public ?int $regionId,
        public ?int $areaId,
        public ?int $sectorId,
        public ?string $address,
        public ?int $seenYear,
        public ?CallOutcome $callOutcome,
        public array $extra,
        public array $issues,
        public ?string $sourceName = null,
    ) {}

    public function severity(): RowSeverity
    {
        $severity = RowSeverity::Clean;
        foreach ($this->issues as $issue) {
            if ($issue->code->severity()->value > $severity->value) {
                $severity = $issue->code->severity();
            }
        }

        return $severity;
    }

    /**
     * @return list<string>
     */
    public function issueCodes(): array
    {
        return array_values(array_unique(array_map(fn (Issue $issue): string => $issue->code->value, $this->issues)));
    }

    public function has(IssueCode $code): bool
    {
        return in_array($code->value, $this->issueCodes(), true);
    }

    /**
     * Values for the parsed columns, as Eloquent attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'source_name' => $this->sourceName,
            'type' => $this->type,
            'name_en' => $this->nameEn,
            'name_ar' => $this->nameAr,
            'company_name' => $this->company,
            'gender' => $this->gender,
            'nationality_country_id' => $this->nationalityCountryId,
            'mobile_e164' => $this->mobile,
            'landline_e164' => $this->landline,
            'email' => $this->email,
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'area_id' => $this->areaId,
            'sector_id' => $this->sectorId,
            'address_raw' => $this->address,
            'seen_year' => $this->seenYear,
            'call_outcome' => $this->callOutcome,
            'extra' => $this->extra === [] ? null : $this->extra,
            'issue_codes' => $this->issueCodes(),
            'issues' => array_map(fn (Issue $issue): array => $issue->toArray(), $this->issues),
            'severity' => $this->severity(),
        ];
    }

    /**
     * One COPY row, in COPY_COLUMNS order.
     *
     * @param  array<string, string>  $raw
     * @return list<int|string|null>
     */
    public function copyValues(int $batchId, int $rowNumber, array $raw): array
    {
        $json = fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $codes = $this->issueCodes();

        return [
            $batchId,
            $rowNumber,
            $json((object) $raw),
            $this->sourceName,
            $this->type->value,
            $this->nameEn,
            $this->nameAr,
            $this->company,
            $this->gender?->value,
            $this->nationalityCountryId,
            $this->mobile,
            $this->landline,
            $this->email,
            $this->countryId,
            $this->regionId,
            $this->areaId,
            $this->sectorId,
            $this->address,
            $this->seenYear,
            $this->callOutcome?->value,
            $this->extra === [] ? null : $json($this->extra),
            '{'.implode(',', $codes).'}',
            $json(array_map(fn (Issue $issue): array => $issue->toArray(), $this->issues)),
            $this->severity()->value,
        ];
    }
}
