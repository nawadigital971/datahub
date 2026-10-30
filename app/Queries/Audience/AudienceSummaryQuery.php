<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;
use App\Enums\ContactType;
use App\Enums\ExportChannel;
use Illuminate\Support\Facades\DB;

/**
 * Audience size, channel readiness and field completeness in one aggregate scan.
 * Channel counts always leave out do-not-contact, whatever the "Hide do-not-contact" toggle says.
 *
 * @phpstan-type Summary array{
 *     total: int, with_mobile: int, uae_mobile: int, usable_email: int, with_landline: int,
 *     channels: array{email: int, whatsapp: int, sms: int, meta: int, google: int, calls: int},
 *     completeness: array{mobile: int, email: int, arabic_name: int, english_name: int, gender: int, area: int},
 *     database: array{contacts: int, raw_rows: int, sources: int},
 * }
 */
class AudienceSummaryQuery
{
    public function __construct(private AudienceFilterApplier $applier, private AudienceCache $cache) {}

    /**
     * @return Summary
     */
    public function handle(AudienceFilters $filters): array
    {
        return $this->cache->remember('summary', $filters->hash(), fn (): array => [
            ...$this->counts($filters),
            'database' => $this->databaseTotals(),
        ]);
    }

    /**
     * @return array{total: int, with_mobile: int, uae_mobile: int, usable_email: int, with_landline: int, channels: array{email: int, whatsapp: int, sms: int, meta: int, google: int, calls: int}, completeness: array{mobile: int, email: int, arabic_name: int, english_name: int, gender: int, area: int}}
     */
    private function counts(AudienceFilters $filters): array
    {
        $business = ContactType::Business->value;
        $email = ExportChannel::Email->contactCondition();
        $whatsapp = ExportChannel::Whatsapp->contactCondition();
        $sms = ExportChannel::Sms->contactCondition();
        $ads = ExportChannel::Meta->contactCondition();
        $calls = ExportChannel::Calls->contactCondition();

        $query = DB::table('contacts')->selectRaw(<<<SQL
            count(*) AS total,
            count(*) FILTER (WHERE has_mobile) AS with_mobile,
            count(*) FILTER (WHERE has_uae_mobile) AS uae_mobile,
            count(*) FILTER (WHERE has_email) AS usable_email,
            count(*) FILTER (WHERE has_landline) AS with_landline,
            count(*) FILTER (WHERE has_arabic_name) AS arabic_name,
            count(*) FILTER (WHERE name_en IS NOT NULL) AS english_name,
            count(*) FILTER (WHERE gender IS NOT NULL OR type = {$business}) AS gender_known,
            count(*) FILTER (WHERE area_id IS NOT NULL) AS area_known,
            count(*) FILTER (WHERE NOT do_not_contact AND {$email}) AS channel_email,
            count(*) FILTER (WHERE NOT do_not_contact AND {$whatsapp}) AS channel_whatsapp,
            count(*) FILTER (WHERE NOT do_not_contact AND {$sms}) AS channel_sms,
            count(*) FILTER (WHERE NOT do_not_contact AND {$ads}) AS channel_ads,
            count(*) FILTER (WHERE NOT do_not_contact AND {$calls}) AS channel_calls
            SQL);

        $row = (array) $this->applier->apply($query, $filters)->first();
        $count = fn (string $column): int => (int) ($row[$column] ?? 0);

        return [
            'total' => $count('total'),
            'with_mobile' => $count('with_mobile'),
            'uae_mobile' => $count('uae_mobile'),
            'usable_email' => $count('usable_email'),
            'with_landline' => $count('with_landline'),
            'channels' => [
                'email' => $count('channel_email'),
                'whatsapp' => $count('channel_whatsapp'),
                'sms' => $count('channel_sms'),
                'meta' => $count('channel_ads'),
                'google' => $count('channel_ads'),
                'calls' => $count('channel_calls'),
            ],
            'completeness' => [
                'mobile' => $count('with_mobile'),
                'email' => $count('usable_email'),
                'arabic_name' => $count('arabic_name'),
                'english_name' => $count('english_name'),
                'gender' => $count('gender_known'),
                'area' => $count('area_known'),
            ],
        ];
    }

    /**
     * Whole-database totals for "of N unique contacts · M raw rows across K sources".
     *
     * @return array{contacts: int, raw_rows: int, sources: int}
     */
    private function databaseTotals(): array
    {
        return $this->cache->remember('totals', 'all', function (): array {
            $row = (array) DB::selectOne(<<<'SQL'
                SELECT (SELECT count(*) FROM contacts) AS contacts,
                       (SELECT count(*) FROM contact_sources) AS raw_rows,
                       (SELECT count(*) FROM sources) AS sources
                SQL);

            return [
                'contacts' => (int) ($row['contacts'] ?? 0),
                'raw_rows' => (int) ($row['raw_rows'] ?? 0),
                'sources' => (int) ($row['sources'] ?? 0),
            ];
        });
    }
}
