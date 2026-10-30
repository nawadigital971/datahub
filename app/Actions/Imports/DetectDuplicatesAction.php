<?php

namespace App\Actions\Imports;

use App\Enums\IssueCode;
use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Set-based checks that need the database, for a whole batch or a few rows:
 * do-not-contact list, duplicates inside the batch, matches with existing contacts
 * (phone first, then email; two different contacts = conflict), severity and the default action.
 * Actions a reviewer chose by hand (action_locked) are never changed.
 */
class DetectDuplicatesAction
{
    /**
     * @param  list<int>|null  $rowIds  Only re-check these rows (inline edits); null = whole batch.
     */
    public function handle(ImportBatch $batch, ?array $rowIds = null): void
    {
        $scope = $rowIds === null ? '' : ' AND s.id = ANY(:ids::bigint[])';
        $bindings = ['batch' => $batch->id] + ($rowIds === null ? [] : ['ids' => '{'.implode(',', array_map('intval', $rowIds)).'}']);
        $suppressed = IssueCode::Suppressed->value;

        DB::transaction(function () use ($scope, $bindings, $suppressed): void {
            DB::update(<<<SQL
                UPDATE staged_rows s SET
                    match_status = :new::smallint, matched_contact_id = NULL, dup_of_row_id = NULL,
                    issue_codes = array_remove(s.issue_codes, '{$suppressed}'),
                    issues = coalesce((SELECT jsonb_agg(i) FROM jsonb_array_elements(s.issues) i WHERE i->>'code' <> '{$suppressed}'), '[]'::jsonb)
                WHERE s.import_batch_id = :batch{$scope}
                SQL, [...$bindings, 'new' => MatchStatus::New->value]);

            DB::update(<<<SQL
                UPDATE staged_rows s SET
                    issue_codes = array_append(s.issue_codes, '{$suppressed}'),
                    issues = s.issues || jsonb_build_array(jsonb_build_object(
                        'field', 'mobile_e164', 'code', '{$suppressed}', 'severity', :warning::int,
                        'message', 'On the do-not-contact list; it will not be imported.', 'suggestion', NULL))
                WHERE s.import_batch_id = :batch{$scope}
                  AND EXISTS (SELECT 1 FROM suppressions p WHERE p.value IN (s.mobile_e164, s.landline_e164, s.email))
                SQL, [...$bindings, 'warning' => RowSeverity::Warning->value]);

            DB::update(<<<SQL
                WITH scoped AS (
                    SELECT s.id, s.mobile_e164, s.landline_e164, s.email FROM staged_rows s
                    WHERE s.import_batch_id = :batch{$scope}
                ), keys AS (
                    SELECT DISTINCT k FROM scoped, unnest(array_remove(ARRAY[mobile_e164, landline_e164, email], NULL)) AS k
                ), candidates AS (
                    SELECT r.id, r.row_number, k.k
                    FROM staged_rows r
                    CROSS JOIN LATERAL unnest(array_remove(ARRAY[r.mobile_e164, r.landline_e164, r.email], NULL)) AS k(k)
                    WHERE r.import_batch_id = :batch AND k.k IN (SELECT k FROM keys)
                ), firsts AS (
                    SELECT id, first_value(id) OVER (PARTITION BY k ORDER BY row_number, id) AS first_id FROM candidates
                ), duplicates AS (
                    SELECT id, min(first_id) AS dup_of FROM firsts WHERE first_id <> id GROUP BY id
                )
                UPDATE staged_rows s SET match_status = :dup::smallint, dup_of_row_id = d.dup_of
                FROM duplicates d WHERE s.id = d.id AND s.id IN (SELECT id FROM scoped)
                SQL, [...$bindings, 'dup' => MatchStatus::DupInBatch->value]);

            DB::update(<<<SQL
                WITH matches AS (
                    SELECT s.id,
                        (SELECT array_agg(DISTINCT p.contact_id ORDER BY p.contact_id) FROM contact_phones p WHERE p.e164 IN (s.mobile_e164, s.landline_e164)) AS phone_ids,
                        (SELECT array_agg(DISTINCT e.contact_id) FROM contact_emails e WHERE e.email = s.email) AS email_ids
                    FROM staged_rows s
                    WHERE s.import_batch_id = :batch AND s.match_status = :new{$scope}
                )
                UPDATE staged_rows s SET
                    matched_contact_id = coalesce(m.phone_ids[1], m.email_ids[1]),
                    match_status = CASE
                        WHEN (SELECT count(DISTINCT x) FROM unnest(coalesce(m.phone_ids, '{}') || coalesce(m.email_ids, '{}')) x) > 1 THEN :conflict::smallint
                        ELSE :matches::smallint END
                FROM matches m
                WHERE s.id = m.id AND (m.phone_ids IS NOT NULL OR m.email_ids IS NOT NULL)
                SQL, [...$bindings, 'new' => MatchStatus::New->value, 'conflict' => MatchStatus::Conflict->value, 'matches' => MatchStatus::MatchesContact->value]);

            DB::update(<<<SQL
                UPDATE staged_rows s SET
                    severity = CASE
                        WHEN jsonb_path_exists(s.issues, '\$[*] ? (@.severity == 2)') THEN 2
                        WHEN jsonb_array_length(s.issues) > 0 THEN 1 ELSE 0 END,
                    action = CASE
                        WHEN s.action_locked THEN s.action
                        WHEN jsonb_path_exists(s.issues, '\$[*] ? (@.severity == 2)')
                          OR '{$suppressed}' = ANY(s.issue_codes)
                          OR s.match_status IN (:dup, :conflict) THEN :skip::smallint
                        WHEN s.match_status = :matches THEN :merge::smallint
                        ELSE :create::smallint END
                WHERE s.import_batch_id = :batch{$scope}
                SQL, [...$bindings,
                'dup' => MatchStatus::DupInBatch->value, 'conflict' => MatchStatus::Conflict->value,
                'matches' => MatchStatus::MatchesContact->value, 'skip' => StagedRowAction::Skip->value,
                'merge' => StagedRowAction::Merge->value, 'create' => StagedRowAction::Create->value]);
        });
    }
}
