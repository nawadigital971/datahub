<?php

namespace App\DTOs\Imports;

use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\ImportTarget;
use App\Models\StagedRow;
use DateTimeInterface;

/**
 * One row's values grouped by meaning, before normalization. Built from spreadsheet cells and the
 * column mapping, or from an already parsed staged row when a reviewer edits it (then ids are preset).
 */
final readonly class MappedRow
{
    /** Keys in staged_rows.extra that the analyzer fills itself (rebuilt on every analysis). */
    public const array DERIVED_EXTRA_KEYS = ['notes', 'other_phones', 'other_emails', 'invalid_phones', 'invalid_email', 'website', 'contract_no', 'agent', 'nationality_raw', 'sector_raw', 'verify_transliteration'];

    /**
     * @param  list<string>  $names  Names whose language must be detected.
     * @param  list<string>  $phones
     * @param  list<string>  $landlines
     * @param  list<string>  $emails
     * @param  list<string>  $notes
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public ?string $nameEn = null,
        public ?string $nameAr = null,
        public array $names = [],
        public ?string $company = null,
        public ?string $type = null,
        public ?string $gender = null,
        public array $phones = [],
        public array $landlines = [],
        public array $emails = [],
        public ?string $nationality = null,
        public ?string $address = null,
        public ?string $region = null,
        public ?string $area = null,
        public ?string $sector = null,
        public ?string $seenYear = null,
        public array $notes = [],
        public ?string $source = null,
        public array $extra = [],
        public bool $verifyTransliteration = false,
        public ?ContactType $presetType = null,
        public ?Gender $presetGender = null,
        public ?int $presetRegionId = null,
        public ?int $presetAreaId = null,
        public ?int $presetSectorId = null,
        public ?int $presetNationalityId = null,
        public ?int $presetSeenYear = null,
    ) {}

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $columnMap  header => target
     * @param  list<mixed>  $cells
     */
    public static function fromCells(array $headers, array $columnMap, array $cells): self
    {
        $values = [];
        $extra = [];
        $verify = false;

        foreach ($headers as $index => $header) {
            $value = self::cell($cells[$index] ?? null);
            if ($value === '') {
                continue;
            }

            $verify = $verify || preg_match('/verify\s+translit/i', $value) === 1;
            $target = $columnMap[$header] ?? ImportTarget::Extra->value;

            if ($target === ImportTarget::Ignore->value) {
                continue;
            }
            if ($target === ImportTarget::Extra->value || str_starts_with($target, 'extra.')) {
                $key = $target === ImportTarget::Extra->value ? self::extraKey($header) : substr($target, 6);
                $extra[$key] = isset($extra[$key]) ? $extra[$key].' | '.$value : $value;

                continue;
            }

            $values[$target][] = $value;
        }

        $first = fn (string $target): ?string => isset($values[$target]) ? implode(' ', $values[$target]) : null;

        return new self(
            nameEn: $first(ImportTarget::NameEn->value),
            nameAr: $first(ImportTarget::NameAr->value),
            names: $values[ImportTarget::Name->value] ?? [],
            company: $first(ImportTarget::Company->value),
            type: $first(ImportTarget::Type->value),
            gender: $first(ImportTarget::Gender->value),
            phones: $values[ImportTarget::Phone->value] ?? [],
            landlines: $values[ImportTarget::Landline->value] ?? [],
            emails: $values[ImportTarget::Email->value] ?? [],
            nationality: $first(ImportTarget::Nationality->value),
            address: isset($values[ImportTarget::Address->value]) ? implode(', ', $values[ImportTarget::Address->value]) : null,
            region: $first(ImportTarget::Region->value),
            area: $first(ImportTarget::Area->value),
            sector: $first(ImportTarget::Sector->value),
            seenYear: $values[ImportTarget::SeenYear->value][0] ?? null,
            notes: $values[ImportTarget::Notes->value] ?? [],
            source: $values[ImportTarget::Source->value][0] ?? null,
            extra: $extra,
            verifyTransliteration: $verify,
        );
    }

    /**
     * A row sent as a JSON object (MCP stage_contacts). Keys are the mapping targets
     * (name_en, name_ar, name, company_name, type, gender, phone, landline, email, nationality,
     * address, region, area, sector, seen_year, notes) plus "extra" for anything else to keep.
     * phone, landline, email and notes may be a string or a list of strings.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $string = fn (string $key): ?string => isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '' ? trim((string) $row[$key]) : null;
        $list = fn (string $key): array => array_values(array_filter(array_map(
            fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            is_array($row[$key] ?? null) ? $row[$key] : [$row[$key] ?? null],
        ), fn (string $value): bool => $value !== ''));
        $extra = is_array($row['extra'] ?? null) ? array_filter($row['extra'], fn (mixed $value): bool => is_scalar($value) || is_array($value)) : [];
        $text = json_encode($row, JSON_UNESCAPED_UNICODE) ?: '';

        return new self(
            nameEn: $string('name_en'),
            nameAr: $string('name_ar'),
            names: $list('name'),
            company: $string('company_name'),
            type: $string('type'),
            gender: $string('gender'),
            phones: $list('phone'),
            landlines: $list('landline'),
            emails: $list('email'),
            nationality: $string('nationality'),
            address: $string('address'),
            region: $string('region'),
            area: $string('area'),
            sector: $string('sector'),
            seenYear: $string('seen_year'),
            notes: $list('notes'),
            source: $string('source'),
            extra: $extra,
            verifyTransliteration: preg_match('/verify\s+translit/i', $text) === 1,
        );
    }

    /**
     * Rebuild the input from a reviewed staged row (with $overrides applied on the parsed columns).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fromStagedRow(StagedRow $row, array $overrides = []): self
    {
        $value = fn (string $column): mixed => array_key_exists($column, $overrides) ? $overrides[$column] : $row->{$column};
        $extra = $row->extra ?? [];
        $list = fn (string $key): array => array_values(array_filter(array_map(strval(...), (array) ($extra[$key] ?? []))));
        $string = fn (mixed $v): ?string => $v === null || $v === '' ? null : (string) $v;
        $int = fn (mixed $v): ?int => $v === null || $v === '' ? null : (int) $v;

        $type = $value('type');
        $gender = $value('gender');

        return new self(
            nameEn: $string($value('name_en')),
            nameAr: $string($value('name_ar')),
            company: $string($value('company_name')),
            phones: array_values(array_filter([$string($value('mobile_e164')), ...$list('other_phones'), ...$list('invalid_phones')])),
            landlines: array_filter([$string($value('landline_e164'))]),
            emails: array_values(array_filter([$string($value('email')) ?? $string($extra['invalid_email'] ?? null), ...$list('other_emails')])),
            nationality: $string($extra['nationality_raw'] ?? null) ?? $string($value('nationality_iso')),
            address: $string($value('address_raw')),
            region: $string($value('region_name')),
            area: $string($value('area_name')),
            sector: $string($value('sector_name')) ?? $string($extra['sector_raw'] ?? null),
            notes: array_filter([$string($extra['notes'] ?? null)]),
            source: $string($value('source_name')),
            extra: array_diff_key($extra, array_flip(self::DERIVED_EXTRA_KEYS)),
            verifyTransliteration: (bool) ($extra['verify_transliteration'] ?? false),
            presetType: $type instanceof ContactType ? $type : ContactType::tryFrom((int) $type),
            presetGender: $gender instanceof Gender ? $gender : Gender::tryFrom((string) $gender),
            presetRegionId: $int($value('region_id')),
            presetAreaId: $int($value('area_id')),
            presetSectorId: $int($value('sector_id')),
            presetNationalityId: $int($value('nationality_country_id')),
            presetSeenYear: $int($value('seen_year')),
        );
    }

    /**
     * A spreadsheet cell as text: dates as Y-m-d, whole numbers without exponent (phones stored as numbers).
     */
    public static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value && abs($value) < 1e15 => sprintf('%.0f', $value),
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => trim((string) preg_replace('/\s+/u', ' ', (string) $value)),
            default => '',
        };
    }

    private static function extraKey(string $header): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($header)), '_');

        return $key === '' ? 'column' : substr($key, 0, 40);
    }
}
