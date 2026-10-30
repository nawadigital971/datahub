<?php

namespace App\Actions\Exports;

use App\Enums\CallOutcome;
use App\Enums\ExportChannel;
use App\Http\Resources\ContactResource;
use App\Queries\Audience\AudienceLookups;

/**
 * Turns one contact row into the CSV cells a channel expects.
 *
 * Hashing follows the platforms' rules: values are trimmed and lowercased first, phones are
 * E.164 (Google) or digits with country code (Meta), then SHA-256 hex. Google's Country stays plain.
 * $mask is for on-screen previews of users who may not see full phones and emails.
 */
class BuildExportRowAction
{
    public function __construct(private AudienceLookups $lookups) {}

    /**
     * @return list<string>
     */
    public function handle(object $row, ExportChannel $channel, bool $hashed, bool $mask = false): array
    {
        $contact = $this->values($row);
        $hash = $hashed && $channel->supportsHashing();
        $phone = $contact['mobile'] ?? $contact['landline'];
        $maskPhone = fn (?string $value): string => $mask ? (ContactResource::maskPhone($value) ?? '') : ($value ?? '');
        $maskEmail = fn (?string $value): string => $mask ? (ContactResource::maskEmail($value) ?? '') : ($value ?? '');

        return match ($channel) {
            ExportChannel::Email => [
                $maskEmail($contact['email']),
                $this->text($contact['first_name']),
                $this->text($contact['last_name']),
                $contact['region'],
                $contact['source'],
            ],
            ExportChannel::Whatsapp => [
                $maskPhone($contact['mobile']),
                $this->text($contact['company'] ?? $contact['name']),
                $contact['name_ar'] !== null ? 'ar' : 'en',
            ],
            ExportChannel::Sms => [
                $mask ? $maskPhone($contact['mobile']) : ltrim($contact['mobile'] ?? '', '+'),
                $this->text($contact['name'] ?? $contact['company']),
            ],
            ExportChannel::Meta => [
                $this->identifier($this->lower($contact['email']), $hash, $mask, $maskEmail),
                $this->identifier($phone !== null ? ltrim($phone, '+') : null, $hash, $mask, $maskPhone),
                $this->identifier($this->metaName($contact['first_name']), $hash),
                $this->identifier($this->metaName($contact['last_name']), $hash),
                $this->identifier($this->metaName($contact['region_en']), $hash),
                $this->identifier($this->lower($contact['country_iso']), $hash),
            ],
            ExportChannel::Google => [
                $this->identifier($this->lower($contact['email']), $hash, $mask, $maskEmail),
                $this->identifier($phone, $hash, $mask, $maskPhone),
                $this->identifier($this->lower($contact['first_name']), $hash),
                $this->identifier($this->lower($contact['last_name']), $hash),
                strtoupper($contact['country_iso'] ?? ''),
            ],
            ExportChannel::Calls => [
                $this->text($contact['name']),
                $this->text($contact['company']),
                $maskPhone($contact['mobile']),
                $maskPhone($contact['landline']),
                $contact['region'],
                $contact['area'],
                $contact['outcome'],
            ],
        };
    }

    /**
     * @return array{name: string|null, name_ar: string|null, first_name: string|null, last_name: string|null, company: string|null, mobile: string|null, landline: string|null, email: string|null, region: string, region_en: string|null, area: string, source: string, outcome: string, country_iso: string|null}
     */
    private function values(object $row): array
    {
        $get = fn (string $key): ?string => isset($row->{$key}) && $row->{$key} !== '' ? (string) $row->{$key} : null;
        $id = fn (string $key): ?int => isset($row->{$key}) ? (int) $row->{$key} : null;

        $name = $get('name_en') ?? $get('name_ar');
        $parts = $name !== null ? preg_split('/\s+/u', trim($name), 2) : [];
        $sourceIds = trim((string) ($row->source_ids ?? '{}'), '{}');
        $firstSourceId = $sourceIds === '' ? null : (int) explode(',', $sourceIds)[0];
        $outcome = $id('last_call_outcome');
        $regionId = $id('region_id');

        return [
            'name' => $name,
            'name_ar' => $get('name_ar'),
            'first_name' => $parts[0] ?? null,
            'last_name' => $parts[1] ?? null,
            'company' => $get('company_name'),
            'mobile' => $get('mobile_e164'),
            'landline' => $get('landline_e164'),
            'email' => $get('email'),
            'region' => $regionId !== null ? ($this->lookups->label('region', $regionId) ?? '') : '',
            'region_en' => $regionId !== null ? $this->lookups->label('region', $regionId) : null,
            'area' => $id('area_id') !== null ? ($this->lookups->label('area', $id('area_id')) ?? '') : '',
            'source' => $firstSourceId !== null ? ($this->lookups->label('source', $firstSourceId) ?? '') : '',
            'outcome' => $outcome !== null ? (CallOutcome::tryFrom($outcome)?->label() ?? '') : '',
            'country_iso' => $id('country_id') !== null ? $this->lookups->label('country_iso', $id('country_id')) : null,
        ];
    }

    /**
     * @param  (callable(?string): string)|null  $maskWith
     */
    private function identifier(?string $value, bool $hash, bool $mask = false, ?callable $maskWith = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($hash) {
            return hash('sha256', $value);
        }

        return $mask && $maskWith !== null ? $maskWith($value) : $value;
    }

    private function lower(?string $value): ?string
    {
        return $value === null ? null : mb_strtolower(trim($value));
    }

    /**
     * Meta wants names and cities lowercase without spaces or punctuation.
     */
    private function metaName(?string $value): ?string
    {
        return $value === null ? null : (string) preg_replace('/[\s\p{P}]+/u', '', mb_strtolower($value));
    }

    /**
     * Free text from imported files: neutralize spreadsheet formulas (CSV injection).
     */
    private function text(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
