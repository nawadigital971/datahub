<?php

namespace App\Actions\Imports;

use App\Actions\Normalization\MatchAreaAction;
use App\Actions\Normalization\NormalizeArabicTextAction;
use App\Actions\Normalization\NormalizeEmailAction;
use App\Actions\Normalization\NormalizePhoneAction;
use App\Actions\Normalization\ParseNotesAction;
use App\DTOs\Imports\AnalysisContext;
use App\DTOs\Imports\AnalyzedRow;
use App\DTOs\Imports\Issue;
use App\DTOs\Imports\MappedRow;
use App\DTOs\Normalization\PhoneResult;
use App\Enums\CleaningRuleType;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\IssueCode;
use App\Enums\PhoneType;
use App\Queries\Imports\CleaningRulesQuery;
use App\Queries\Imports\ImportLookups;
use DateTimeImmutable;

/**
 * Normalizes one mapped row and lists its problems. Pure in-memory work (lookups are cached), so a whole
 * file is analyzed in PHP and then bulk-loaded with COPY. Duplicate and do-not-contact checks need the
 * database and are done set-based afterwards (DetectDuplicatesAction).
 */
class AnalyzeRowAction
{
    /** @var array<string, true>|null */
    private ?array $junkNames = null;

    /** @var array<string, string>|null */
    private ?array $firstNames = null;

    /** @var array<string, string>|null */
    private ?array $genderWords = null;

    /** @var list<string>|null */
    private ?array $businessWords = null;

    public function __construct(
        private NormalizePhoneAction $phones,
        private NormalizeEmailAction $emails,
        private NormalizeArabicTextAction $normalize,
        private MatchAreaAction $areas,
        private ParseNotesAction $notes,
        private ImportLookups $lookups,
        private CleaningRulesQuery $rules,
    ) {}

    public function handle(MappedRow $row, AnalysisContext $context): AnalyzedRow
    {
        $issues = [];
        $extra = $row->extra;

        /* ---------- names ---------- */
        $nameEn = $this->clean($row->nameEn);
        $nameAr = $this->clean($row->nameAr);
        foreach ($row->names as $name) {
            $name = $this->clean($name);
            if ($name === null) {
                continue;
            }
            if ($this->isArabic($name)) {
                $nameAr ??= $name;
            } else {
                $nameEn ??= $name;
            }
        }
        if ($nameEn !== null && $nameAr === null && $this->isArabic($nameEn)) {
            [$nameAr, $nameEn] = [$nameEn, null];
        }
        $company = $this->clean($row->company);

        /* ---------- notes ---------- */
        $notesText = $row->notes === [] ? null : implode(' | ', $row->notes);
        $notes = $this->notes->handle($notesText);
        if ($notesText !== null) {
            $extra['notes'] = $notesText;
        }
        if ($notes->contractNo !== null) {
            $extra['contract_no'] = $notes->contractNo;
        }
        if ($notes->agent !== null) {
            $extra['agent'] = $notes->agent;
        }

        /* ---------- phones ---------- */
        $mobiles = [];
        $landlines = [];
        $invalid = [];
        foreach ($row->phones as $value) {
            foreach ($this->phones->handle($value) as $phone) {
                if (! $phone->isValid()) {
                    $invalid[] = $phone->raw;
                } elseif ($phone->type === PhoneType::Landline) {
                    $landlines[] = $phone;
                    $issues[] = new Issue(IssueCode::LandlineInMobile, 'mobile_e164', "{$phone->raw} is a landline; moved to the landline column.");
                } else {
                    $mobiles[] = $phone;
                }
            }
        }
        foreach ([...$row->landlines, ...$notes->landlines] as $value) {
            foreach ($this->phones->handle($value) as $phone) {
                if (! $phone->isValid()) {
                    $invalid[] = $phone->raw;
                } elseif ($phone->type === PhoneType::Mobile) {
                    $mobiles[] = $phone;
                } else {
                    $landlines[] = $phone;
                }
            }
        }
        $mobiles = $this->uniquePhones($mobiles);
        $landlines = $this->uniquePhones($landlines);

        foreach ($invalid as $raw) {
            $issues[] = new Issue(IssueCode::InvalidPhone, 'mobile_e164', "“{$raw}” is not a valid phone number.");
        }
        if ($invalid !== []) {
            $extra['invalid_phones'] = array_values(array_unique($invalid));
        }

        $known = array_map(fn (PhoneResult $phone): ?string => $phone->e164, [...$mobiles, ...$landlines]);
        foreach ($notes->phones as $raw) {
            foreach ($this->phones->handle($raw) as $phone) {
                if ($phone->isValid() && ! in_array($phone->e164, $known, true)) {
                    $issues[] = new Issue(IssueCode::PhoneInNotes, $phone->type === PhoneType::Landline ? 'landline_e164' : 'mobile_e164', "Notes mention another number: {$phone->raw}.", $phone->e164);
                    $known[] = $phone->e164;
                }
            }
        }

        $mobile = $mobiles[0] ?? null;
        $landline = $landlines[0] ?? null;
        $otherPhones = array_values(array_filter(array_map(fn (PhoneResult $phone): ?string => $phone->e164, [...array_slice($mobiles, 1), ...array_slice($landlines, 1)])));
        if ($otherPhones !== []) {
            $extra['other_phones'] = $otherPhones;
        }

        /* ---------- emails ---------- */
        $email = null;
        $otherEmails = [];
        foreach ([...$row->emails, ...$notes->emails] as $value) {
            foreach ($this->emails->handle($value) as $result) {
                if ($result->website !== null) {
                    $extra['website'] = $result->website;
                } elseif (! $result->valid) {
                    $extra['invalid_email'] = $result->raw;
                    $issues[] = new Issue(IssueCode::EmailInvalid, 'email', "“{$result->raw}” is not a valid email address.");
                } elseif ($email === null) {
                    $email = $result->email;
                    if ($result->suggestion !== null) {
                        $issues[] = new Issue(IssueCode::EmailTypo, 'email', "The domain looks misspelled. Did you mean {$result->suggestion}?", $result->suggestion);
                    }
                } elseif ($result->email !== $email) {
                    $otherEmails[] = $result->email;
                }
            }
        }
        if ($otherEmails !== []) {
            $extra['other_emails'] = array_values(array_unique(array_filter($otherEmails)));
        }

        if ($mobile === null && $landline === null && $email === null) {
            $issues[] = new Issue(IssueCode::MissingContactMethod, 'mobile_e164', 'No phone or email, so this person cannot be reached.');
        }

        /* ---------- type, gender ---------- */
        $type = $row->presetType ?? $this->type($row->type, $company, $nameEn ?? $nameAr);
        $gender = $row->presetGender ?? $this->gender($row->gender);
        $firstName = $this->firstName($nameEn ?? $nameAr);
        if ($gender !== null && $firstName !== null) {
            $usual = $this->firstNameGenders()[$firstName] ?? null;
            if ($usual !== null && $usual !== $gender->value) {
                $issues[] = new Issue(IssueCode::GenderSuspect, 'gender', "Marked {$gender->label()}, but the first name usually belongs to the other gender.");
            }
        }

        /* ---------- names quality ---------- */
        if ($type === ContactType::Person && $this->isJunk($nameEn, $nameAr)) {
            $issues[] = new Issue(IssueCode::JunkName, 'name_en', 'The name looks like junk or a placeholder.');
        }
        if ($nameEn !== null && ($row->verifyTransliteration || $notes->verifyTransliteration || $this->looksMachineTransliterated($nameEn))) {
            $extra['verify_transliteration'] = true;
            $issues[] = new Issue(IssueCode::UnverifiedTransliteration, 'name_en', 'The English name looks machine-transliterated; check the spelling.');
        }

        /* ---------- location ---------- */
        $address = $this->clean($row->address);
        $regionId = $row->presetRegionId ?? $this->areas->region($row->region);
        $areaId = $row->presetAreaId;
        if ($areaId === null && $address !== null) {
            $match = $this->areas->handle($address, $regionId);
            $regionId ??= $match->regionId;
            $areaId = $match->areaId;
        }
        if ($regionId === null && $landline?->e164 !== null) {
            $regionId = $this->regionFromLandline($landline->e164);
        }
        if ($address !== null && $regionId === null) {
            $issues[] = new Issue(IssueCode::UnknownRegion, 'region_id', 'No emirate found in the address.');
        } elseif ($address !== null && $areaId === null) {
            $issues[] = new Issue(IssueCode::UnknownArea, 'area_id', 'No known district found in the address.');
        }

        /* ---------- nationality, sector, country ---------- */
        $nationalityId = $row->presetNationalityId ?? $this->lookups->countryId($row->nationality);
        if ($nationalityId === null && $row->nationality !== null) {
            $extra['nationality_raw'] = $row->nationality;
        }
        $sectorId = $row->presetSectorId ?? $this->lookups->sectorId($row->sector);
        if ($sectorId === null && $row->sector !== null) {
            $extra['sector_raw'] = $row->sector;
        }
        $countryId = $this->lookups->countryIdByIso($mobile->countryIso ?? $landline?->countryIso);

        /* ---------- year ---------- */
        $seenYear = $row->presetSeenYear ?? $this->year($row->seenYear) ?? $context->sourceYear;
        if ($seenYear !== null && $seenYear < $context->staleBefore) {
            $issues[] = new Issue(IssueCode::StaleData, 'seen_year', "Collected in {$seenYear}; numbers and emails this old often no longer work.");
        }

        return new AnalyzedRow(
            type: $type,
            nameEn: $nameEn,
            nameAr: $nameAr,
            company: $company,
            gender: $gender,
            nationalityCountryId: $nationalityId,
            mobile: $mobile?->e164,
            landline: $landline?->e164,
            email: $email,
            countryId: $countryId,
            regionId: $regionId,
            areaId: $areaId,
            sectorId: $sectorId,
            address: $address,
            seenYear: $seenYear,
            callOutcome: $notes->callOutcome,
            extra: $extra,
            issues: $issues,
            sourceName: $this->clean($row->source) === null ? null : mb_substr((string) $this->clean($row->source), 0, 120),
        );
    }

    private function clean(?string $value): ?string
    {
        $value = $value === null ? '' : trim((string) preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B-–—,.");

        return $value === '' ? null : mb_substr($value, 0, 200);
    }

    private function isArabic(string $value): bool
    {
        return preg_match('/\p{Arabic}/u', $value) === 1;
    }

    private function type(?string $raw, ?string $company, ?string $name): ContactType
    {
        $raw = $this->normalize->handle($raw);
        if ($raw !== '') {
            foreach ($this->businessWords() as $word) {
                if (str_contains($raw, $word)) {
                    return ContactType::Business;
                }
            }

            return ContactType::Person;
        }

        return $company !== null && $name === null ? ContactType::Business : ($company !== null ? ContactType::Business : ContactType::Person);
    }

    private function gender(?string $raw): ?Gender
    {
        $raw = rtrim($this->normalize->handle($raw), '.');
        $gender = $raw === '' ? null : ($this->genderWords()[$raw] ?? null);

        return $gender === null ? null : Gender::from($gender);
    }

    private function firstName(?string $name): ?string
    {
        $normalized = $this->normalize->handle($name);

        return $normalized === '' ? null : explode(' ', $normalized)[0];
    }

    private function isJunk(?string $nameEn, ?string $nameAr): bool
    {
        $names = array_filter([$nameEn, $nameAr]);
        if ($names === []) {
            return false;
        }

        foreach ($names as $name) {
            $normalized = $this->normalize->handle($name);
            $letters = (string) preg_replace('/[^\p{L}]/u', '', $normalized);
            $isJunk = isset($this->junkNames()[$normalized])
                || mb_strlen($letters) < 2
                || preg_match('/^(.)\1+$/u', $letters) === 1;

            if (! $isJunk) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gender-word rules: normalized word (without a trailing dot) => "m" / "f".
     *
     * @return array<string, string>
     */
    private function genderWords(): array
    {
        if ($this->genderWords === null) {
            $this->genderWords = [];
            foreach ($this->rules->map(CleaningRuleType::GenderWord) as $word => $gender) {
                if (in_array($gender, ['m', 'f'], true)) {
                    $this->genderWords[rtrim($this->normalize->handle($word), '.')] ??= $gender;
                }
            }
        }

        return $this->genderWords;
    }

    /**
     * Business-word rules, normalized like the record-type column.
     *
     * @return list<string>
     */
    private function businessWords(): array
    {
        return $this->businessWords ??= array_values(array_filter(array_map(
            fn (string $word): string => $this->normalize->handle($word),
            array_keys($this->rules->map(CleaningRuleType::BusinessWord)),
        ), fn (string $word): bool => $word !== ''));
    }

    /**
     * Junk-name rules, normalized like the names they are compared with.
     *
     * @return array<string, true>
     */
    private function junkNames(): array
    {
        return $this->junkNames ??= array_fill_keys(array_map(
            fn (string $key): string => $this->normalize->handle($key),
            array_keys($this->rules->map(CleaningRuleType::JunkName)),
        ), true);
    }

    /**
     * First-name rules: normalized first name => "m" / "f".
     *
     * @return array<string, string>
     */
    private function firstNameGenders(): array
    {
        if ($this->firstNames === null) {
            $this->firstNames = [];
            foreach ($this->rules->map(CleaningRuleType::FirstName) as $name => $gender) {
                if (in_array($gender, ['m', 'f'], true)) {
                    $this->firstNames[$this->normalize->handle($name)] ??= $gender;
                }
            }
        }

        return $this->firstNames;
    }

    /**
     * Romanized Arabic produced by a machine often drops the vowels ("Mhnd", "Nsryn").
     */
    private function looksMachineTransliterated(string $nameEn): bool
    {
        foreach (preg_split('/\s+/', strtolower($nameEn)) ?: [] as $word) {
            if (strlen($word) >= 3 && preg_match('/^[a-z]+$/', $word) === 1 && preg_match('/[aeiouy]/', $word) === 0) {
                return true;
            }
        }

        return false;
    }

    private function regionFromLandline(string $e164): ?int
    {
        foreach ($this->rules->map(CleaningRuleType::LandlinePrefix) as $prefix => $slug) {
            if ($slug !== null && str_starts_with($e164, $prefix)) {
                return $this->areas->region(str_replace('-', ' ', $slug));
            }
        }

        return null;
    }

    /**
     * A 4-digit year, a date or timestamp, or an Excel date serial.
     */
    private function year(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $currentYear = (int) date('Y');
        $valid = fn (int $year): ?int => $year >= 1990 && $year <= $currentYear + 1 ? $year : null;

        if (preg_match('/\b(19|20)\d{2}\b/', $raw, $match) === 1) {
            return $valid((int) $match[0]);
        }

        if (is_numeric($raw) && (float) $raw > 20000 && (float) $raw < 80000) {
            $date = (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $raw.' days');

            return $valid((int) $date->format('Y'));
        }

        return null;
    }

    /**
     * @param  list<PhoneResult>  $phones
     * @return list<PhoneResult>
     */
    private function uniquePhones(array $phones): array
    {
        $unique = [];
        foreach ($phones as $phone) {
            $unique[$phone->e164] ??= $phone;
        }

        return array_values($unique);
    }
}
