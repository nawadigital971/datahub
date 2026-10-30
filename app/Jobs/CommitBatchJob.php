<?php

namespace App\Jobs;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Actions\Contacts\RefreshFacetCountsAction;
use App\Actions\ReferenceData\ResolveBatchReferenceDataAction;
use App\Actions\Sources\ResolveBatchSourcesAction;
use App\Enums\ContactEventType;
use App\Enums\ImportBatchStatus;
use App\Enums\IssueCode;
use App\Enums\StagedRowAction;
use App\Models\ImportBatch;
use App\Queries\Imports\MergeComparisonQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Commits a reviewed batch with set-based SQL, 5,000 staged rows per transaction:
 * create new contacts, merge into existing ones (honouring per-field merge choices), add phones, emails
 * and the source link, write call/merge events, then delete the processed staged rows. Because processed
 * rows are deleted chunk by chunk, a failed commit can simply be run again. Afterwards the facet counts
 * are refreshed and the Audience cache is busted.
 */
class CommitBatchJob implements ShouldQueue
{
    use Queueable;

    public const int CHUNK = 5_000;

    public int $tries = 1;

    public int $timeout = 3600;

    /** Keys in staged_rows.extra that are working data, not contact information. */
    private const string WORKING_KEYS = "ARRAY['other_phones','other_emails','verify_transliteration','invalid_phones','email_original']";

    public function __construct(public int $batchId, public int $userId) {}

    public function handle(RefreshFacetCountsAction $refreshFacetCounts, BustAudienceCacheAction $bustCache, ResolveBatchSourcesAction $resolveSources, ResolveBatchReferenceDataAction $resolveReferenceData): void
    {
        $batch = ImportBatch::query()->findOrFail($this->batchId);
        $bindings = ['batch' => $batch->id];

        $resolveSources->handle($batch);
        $resolveReferenceData->handle($batch);
        $this->recheckBeforeCommit($bindings);

        $bounds = DB::selectOne('SELECT min(id) AS lo, max(id) AS hi FROM staged_rows WHERE import_batch_id = :batch', $bindings);
        $lo = (int) ($bounds->lo ?? 0);
        $hi = (int) ($bounds->hi ?? -1);

        for ($start = $lo; $start <= $hi; $start += self::CHUNK) {
            DB::transaction(fn () => $this->commitChunk([
                ...$bindings,
                'lo' => $start,
                'hi' => $start + self::CHUNK - 1,
                'user' => $this->userId,
            ]));
        }

        $batch->update([
            'status' => ImportBatchStatus::Committed,
            'committed_by' => $this->userId,
            'committed_at' => now(),
            'error' => null,
        ]);

        $refreshFacetCounts->handle();
        $bustCache->handle();
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => ImportBatchStatus::Failed,
            'error' => 'The commit stopped: '.($exception?->getMessage() ?? 'unknown error').' Rows already committed were removed from the preview; commit again to finish.',
        ]);
    }

    /**
     * Things may have changed since the analysis: skip anything now on the do-not-contact list, and turn
     * "create" into "merge" when another import has meanwhile added the same phone or email.
     *
     * @param  array{batch: int}  $bindings
     */
    private function recheckBeforeCommit(array $bindings): void
    {
        DB::update(<<<'SQL'
            UPDATE staged_rows s SET action = :skip
            WHERE s.import_batch_id = :batch AND s.action <> :skip
              AND EXISTS (SELECT 1 FROM suppressions p WHERE p.value IN (s.mobile_e164, s.landline_e164, s.email))
            SQL, [...$bindings, 'skip' => StagedRowAction::Skip->value]);

        DB::update(<<<'SQL'
            UPDATE staged_rows s SET action = :merge, matched_contact_id = m.contact_id
            FROM (
                SELECT s2.id, coalesce(
                    (SELECT p.contact_id FROM contact_phones p WHERE p.e164 IN (s2.mobile_e164, s2.landline_e164) LIMIT 1),
                    (SELECT e.contact_id FROM contact_emails e WHERE e.email = s2.email LIMIT 1)
                ) AS contact_id
                FROM staged_rows s2 WHERE s2.import_batch_id = :batch AND s2.action = :create
            ) m
            WHERE s.id = m.id AND m.contact_id IS NOT NULL
            SQL, [...$bindings, 'merge' => StagedRowAction::Merge->value, 'create' => StagedRowAction::Create->value]);
    }

    /**
     * @param  array{batch: int, lo: int, hi: int, user: int}  $b
     */
    private function commitChunk(array $b): void
    {
        $create = StagedRowAction::Create->value;
        $merge = StagedRowAction::Merge->value;
        $chunk = 's.import_batch_id = :batch AND s.id BETWEEN :lo AND :hi';
        $junk = IssueCode::JunkName->value;
        $unverified = IssueCode::UnverifiedTransliteration->value;
        $typo = IssueCode::EmailTypo->value;
        $working = self::WORKING_KEYS;
        $emailStatus = "CASE WHEN s.email IS NULL THEN 0 WHEN '{$typo}' = ANY(s.issue_codes) THEN 0 WHEN s.extra->>'email_original' IS NOT NULL THEN 3 ELSE 1 END";

        // 1. New contacts (their new id is written back to matched_contact_id for the steps below).
        DB::update(<<<SQL
            WITH src AS (
                SELECT s.*, nextval(pg_get_serial_sequence('contacts', 'id')) AS new_id
                FROM staged_rows s WHERE {$chunk} AND s.action = {$create}
            ), inserted AS (
                INSERT INTO contacts (
                    id, type, name_en, name_ar, company_name, name_en_verified, gender, nationality_country_id, country_id,
                    region_id, area_id, sector_id, address_raw, mobile_e164, landline_e164, email, email_status,
                    source_ids, source_count, first_seen_year, last_seen_year, last_call_outcome, is_junk, extra, created_at, updated_at
                )
                SELECT new_id, coalesce(type, 1), name_en, name_ar, company_name, NOT ('{$unverified}' = ANY(issue_codes)), gender,
                    nationality_country_id, country_id, region_id, area_id, sector_id, address_raw, mobile_e164, landline_e164, email,
                    {$this->unprefixed($emailStatus)}, ARRAY[source_id]::smallint[], 1, seen_year, seen_year, call_outcome,
                    '{$junk}' = ANY(issue_codes), nullif(coalesce(extra, '{}'::jsonb) - {$working}, '{}'::jsonb), now(), now()
                FROM src
                RETURNING id
            )
            UPDATE staged_rows s SET matched_contact_id = src.new_id FROM src WHERE s.id = src.id
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        // 2. Merges into existing contacts.
        $choice = fn (string $field): string => "coalesce(s.merge_choices->>'{$field}', CASE WHEN c.{$field} IS NULL THEN 'incoming' ELSE 'existing' END)";
        $assignments = [];
        foreach (array_keys(MergeComparisonQuery::FIELDS) as $field) {
            $assignments[] = "{$field} = CASE {$choice($field)} WHEN 'incoming' THEN coalesce(s.{$field}, c.{$field}) ELSE c.{$field} END";
        }
        $assignments = implode(",\n", $assignments);

        DB::update(<<<SQL
            UPDATE contacts c SET
                {$assignments},
                name_en_verified = CASE WHEN {$choice('name_en')} = 'incoming' AND s.name_en IS NOT NULL
                    THEN NOT ('{$unverified}' = ANY(s.issue_codes)) ELSE c.name_en_verified END,
                is_junk = CASE WHEN {$choice('name_en')} = 'incoming' AND s.name_en IS NOT NULL
                    THEN '{$junk}' = ANY(s.issue_codes) ELSE c.is_junk END,
                email_status = CASE WHEN {$choice('email')} = 'incoming' AND s.email IS NOT NULL THEN {$emailStatus} ELSE c.email_status END,
                landline_e164 = coalesce(c.landline_e164, s.landline_e164),
                country_id = coalesce(c.country_id, s.country_id),
                source_ids = ARRAY(SELECT DISTINCT x FROM unnest(c.source_ids || s.source_id) x ORDER BY x),
                source_count = cardinality(ARRAY(SELECT DISTINCT x FROM unnest(c.source_ids || s.source_id) x)),
                first_seen_year = LEAST(c.first_seen_year, s.seen_year),
                last_seen_year = GREATEST(c.last_seen_year, s.seen_year),
                last_call_outcome = coalesce(s.call_outcome, c.last_call_outcome),
                extra = nullif(coalesce(c.extra, '{}'::jsonb) || (coalesce(s.extra, '{}'::jsonb) - {$working}), '{}'::jsonb),
                updated_at = now()
            FROM staged_rows s
            WHERE {$chunk} AND s.action = {$merge} AND s.matched_contact_id = c.id
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        $committed = "{$chunk} AND s.action IN ({$create}, {$merge}) AND s.matched_contact_id IS NOT NULL";

        // 3. Every phone and email (added, never replacing) and the link to this source.
        DB::insert(<<<SQL
            INSERT INTO contact_phones (contact_id, e164, type, country_id, is_primary, created_at)
            SELECT DISTINCT ON (p.e164) s.matched_contact_id, p.e164, p.type, s.country_id, p.is_primary, now()
            FROM staged_rows s
            CROSS JOIN LATERAL (
                SELECT s.mobile_e164 AS e164, 1 AS type, s.action = {$create} AS is_primary
                UNION ALL SELECT s.landline_e164, 2, s.action = {$create} AND s.mobile_e164 IS NULL
                UNION ALL SELECT x, CASE WHEN x LIKE '+971%' AND x NOT LIKE '+9715%' THEN 2 ELSE 1 END, false
                    FROM jsonb_array_elements_text(coalesce(s.extra->'other_phones', '[]'::jsonb)) x
            ) p
            WHERE {$committed} AND p.e164 IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM suppressions x WHERE x.value = p.e164)
            ORDER BY p.e164, s.id
            ON CONFLICT (e164) DO NOTHING
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        DB::insert(<<<SQL
            INSERT INTO contact_emails (contact_id, email, is_primary, created_at)
            SELECT DISTINCT ON (e.email) s.matched_contact_id, e.email, e.is_primary, now()
            FROM staged_rows s
            CROSS JOIN LATERAL (
                SELECT s.email AS email, s.action = {$create} AS is_primary
                UNION ALL SELECT x, false FROM jsonb_array_elements_text(coalesce(s.extra->'other_emails', '[]'::jsonb)) x
            ) e
            WHERE {$committed} AND e.email IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM suppressions x WHERE x.value = e.email)
            ORDER BY e.email, s.id
            ON CONFLICT (email) DO NOTHING
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        DB::insert(<<<SQL
            INSERT INTO contact_sources (contact_id, source_id, import_batch_id, row_number, seen_year, raw, created_at)
            SELECT DISTINCT ON (s.matched_contact_id, s.source_id) s.matched_contact_id, s.source_id, :batch::bigint, s.row_number, s.seen_year, s.raw, now()
            FROM staged_rows s
            WHERE {$committed}
            ORDER BY s.matched_contact_id, s.source_id, s.id
            ON CONFLICT (contact_id, source_id) DO UPDATE SET seen_year = GREATEST(contact_sources.seen_year, EXCLUDED.seen_year)
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        // 3b. Add every source this file gave a contact (one file can name several sources for the same person).
        DB::update(<<<SQL
            UPDATE contacts c SET
                source_ids = ARRAY(SELECT DISTINCT x FROM unnest(c.source_ids || agg.ids) x ORDER BY x),
                source_count = cardinality(ARRAY(SELECT DISTINCT x FROM unnest(c.source_ids || agg.ids) x))
            FROM (
                SELECT cs.contact_id, array_agg(DISTINCT cs.source_id ORDER BY cs.source_id) AS ids
                FROM contact_sources cs
                WHERE cs.contact_id IN (SELECT s.matched_contact_id FROM staged_rows s WHERE {$committed})
                GROUP BY cs.contact_id
            ) agg
            WHERE c.id = agg.contact_id
            SQL, $this->only($b, ['batch', 'lo', 'hi']));

        // 4. History: merges and call outcomes read from the notes.
        DB::insert(<<<SQL
            INSERT INTO contact_events (contact_id, type, outcome, agent, occurred_at, meta, user_id)
            SELECT s.matched_contact_id, {$this->event(ContactEventType::Merge)}, NULL, NULL, now(),
                jsonb_build_object('import_batch_id', s.import_batch_id, 'row_number', s.row_number), :user::bigint
            FROM staged_rows s WHERE {$committed} AND s.action = {$merge}
            UNION ALL
            SELECT s.matched_contact_id, {$this->event(ContactEventType::Call)}, s.call_outcome, s.extra->>'agent',
                CASE WHEN s.seen_year IS NULL THEN now() ELSE make_timestamptz(s.seen_year, 1, 1, 0, 0, 0, 'Asia/Dubai') END,
                jsonb_build_object('import_batch_id', s.import_batch_id, 'row_number', s.row_number, 'notes', s.extra->>'notes'), :user::bigint
            FROM staged_rows s WHERE {$committed} AND s.call_outcome IS NOT NULL
            SQL, $this->only($b, ['batch', 'lo', 'hi', 'user']));

        // 5. Done with these rows (the original row lives on in contact_sources.raw).
        DB::delete("DELETE FROM staged_rows s WHERE {$chunk}", $this->only($b, ['batch', 'lo', 'hi']));
    }

    private function unprefixed(string $expression): string
    {
        return str_replace('s.', '', $expression);
    }

    private function event(ContactEventType $type): int
    {
        return $type->value;
    }

    /**
     * PDO rejects bindings that are not used in the statement.
     *
     * @param  array<string, int>  $bindings
     * @param  list<string>  $keys
     * @return array<string, int>
     */
    private function only(array $bindings, array $keys): array
    {
        return array_intersect_key($bindings, array_flip($keys));
    }
}
