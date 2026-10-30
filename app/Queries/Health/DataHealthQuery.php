<?php

namespace App\Queries\Health;

use App\Enums\EmailStatus;
use App\Enums\ImportBatchStatus;
use App\Queries\Audience\AudienceCache;
use App\Settings\DataSettings;
use Illuminate\Support\Facades\DB;

/**
 * Live counts of data-quality issues across all contacts, in one aggregate scan (cached; busted with
 * the Audience cache after every import commit).
 *
 * @phpstan-type Issue array{key: string, severity: 'high'|'med'|'low', title: string, description: string, rule: string, count: int, audience: array<string, string>|null}
 */
class DataHealthQuery
{
    public function __construct(private AudienceCache $cache, private DataSettings $settings) {}

    /**
     * @return array{total: int, issues: list<Issue>}
     */
    public function handle(): array
    {
        $staleBefore = $this->settings->stale_data_year_threshold;

        return $this->cache->remember('health', (string) $staleBefore, function () use ($staleBefore): array {
            $row = (array) DB::selectOne(<<<'SQL'
                SELECT count(*) AS total,
                    count(*) FILTER (WHERE NOT has_mobile AND NOT has_landline AND NOT has_email) AS unreachable,
                    count(*) FILTER (WHERE email_status = :invalid) AS invalid_email,
                    count(*) FILTER (WHERE is_junk) AS junk,
                    count(*) FILTER (WHERE name_en IS NOT NULL AND NOT name_en_verified) AS unverified,
                    count(*) FILTER (WHERE last_seen_year < :stale) AS stale,
                    count(*) FILTER (WHERE region_id IS NULL AND country_id = (SELECT id FROM countries WHERE iso2 = 'AE')) AS no_region,
                    count(*) FILTER (WHERE region_id IS NOT NULL AND area_id IS NULL) AS no_area,
                    count(*) FILTER (WHERE gender IS NOT NULL AND NOT gender_trusted) AS untrusted_gender,
                    count(*) FILTER (WHERE do_not_contact) AS dnc
                FROM contacts
                SQL, ['invalid' => EmailStatus::Invalid->value, 'stale' => $staleBefore]);

            $count = fn (string $key): int => (int) ($row[$key] ?? 0);
            $waiting = DB::table('import_batches')->where('status', ImportBatchStatus::Ready->value)->count();
            $suppressed = DB::table('suppressions')->count();

            return [
                'total' => $count('total'),
                'issues' => [
                    ['key' => 'unreachable', 'severity' => 'high', 'count' => $count('unreachable'), 'audience' => null,
                        'title' => 'No way to reach them',
                        'description' => 'Contacts with no mobile, landline or email. They cannot be used for any channel.',
                        'rule' => 'New imports flag these as errors and skip them; complete or remove the old ones.'],
                    ['key' => 'invalid_email', 'severity' => 'med', 'count' => $count('invalid_email'), 'audience' => null,
                        'title' => 'Invalid email addresses',
                        'description' => 'Emails that are not valid addresses. They are never counted for email or ad audiences.',
                        'rule' => 'Imports suggest fixes for common typos (gamil.com → gmail.com); accept them before committing.'],
                    ['key' => 'junk', 'severity' => 'med', 'count' => $count('junk'), 'audience' => null,
                        'title' => 'Junk or placeholder names',
                        'description' => 'Names like “test”, “.”, device names or a single letter.',
                        'rule' => 'Hide them in the Audience for personalised messages; they still work for phone-only ad matching.'],
                    ['key' => 'unverified', 'severity' => 'med', 'count' => $count('unverified'), 'audience' => null,
                        'title' => 'Machine-transliterated English names',
                        'description' => 'English names generated from Arabic (Mhnd, Nsryn) or tagged “Verify transliteration”.',
                        'rule' => 'Greet these people in Arabic; correct the English spelling when you can.'],
                    ['key' => 'stale', 'severity' => 'med', 'count' => $count('stale'), 'audience' => ['year_to' => (string) ($staleBefore - 1)],
                        'title' => "Not seen since before {$staleBefore}",
                        'description' => 'Old records: numbers and emails change hands over the years and bounce more.',
                        'rule' => 'Verify numbers and emails before a big campaign; prefer fresh data for email.'],
                    ['key' => 'no_region', 'severity' => 'low', 'count' => $count('no_region'), 'audience' => ['region' => '0'],
                        'title' => 'UAE contacts without an emirate',
                        'description' => 'The address did not name an emirate and the landline gave no clue.',
                        'rule' => 'Fix the address in a future import, or set the emirate in bulk in the import preview.'],
                    ['key' => 'no_area', 'severity' => 'low', 'count' => $count('no_area'), 'audience' => ['area' => '0'],
                        'title' => 'Emirate known, district unknown',
                        'description' => 'The address did not match any district spelling in the areas dictionary.',
                        'rule' => 'Add missing spellings to database/data/areas.json and run app:import-areas.'],
                    ['key' => 'untrusted_gender', 'severity' => 'low', 'count' => $count('untrusted_gender'), 'audience' => null,
                        'title' => 'Gender from an untrusted source',
                        'description' => 'Some lists mark gender unreliably (male names marked female and the reverse).',
                        'rule' => 'Avoid targeting by gender on these contacts.'],
                    ['key' => 'dnc', 'severity' => 'low', 'count' => $count('dnc'), 'audience' => null,
                        'title' => 'On the do-not-contact list',
                        'description' => "Never exported or counted for any channel. The global list holds {$suppressed} numbers and emails.",
                        'rule' => 'Keep the list current; imports can never bring these back.'],
                    ['key' => 'waiting_imports', 'severity' => $waiting > 0 ? 'med' : 'low', 'count' => $waiting, 'audience' => null,
                        'title' => 'Imports waiting for review',
                        'description' => 'Uploaded or Claude-staged batches that nobody has committed or discarded yet.',
                        'rule' => 'Review and commit them in Imports; nothing reaches the contacts until then.'],
                ],
            ];
        });
    }
}
