<?php

namespace App\Actions\Imports;

use App\DTOs\Imports\BulkStagedRowsData;
use App\Enums\IssueCode;
use App\Enums\StagedRowAction;
use App\Models\ImportBatch;
use App\Queries\Imports\StagedRowFilters;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Set-based changes on many staged rows at once (selected ids, or every row matching the preview filters).
 */
class BulkUpdateStagedRowsAction
{
    public function __construct(
        private StagedRowFilters $filters,
        private DetectDuplicatesAction $duplicates,
        private RecountBatchAction $recount,
    ) {}

    /**
     * @return int Number of rows changed.
     */
    public function handle(ImportBatch $batch, BulkStagedRowsData $data): int
    {
        $scope = DB::table('staged_rows')->where('import_batch_id', $batch->id);
        if ($data->ids !== null) {
            $scope->whereIn('id', $data->ids);
        } else {
            $this->filters->apply($scope, $data->filters);
        }

        /** @var list<int> $ids */
        $ids = $scope->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        if ($ids === []) {
            return 0;
        }

        DB::transaction(function () use ($batch, $data, $ids): void {
            $rows = DB::table('staged_rows')->where('import_batch_id', $batch->id)->whereIn('id', $ids);

            match ($data->operation) {
                'set_action' => $this->setAction($rows, (string) $data->value),
                'set_region' => $this->setRegion($ids, $data->value === null ? null : (int) $data->value),
                'set_area' => $this->setArea($ids, (int) $data->value),
                'set_sector' => $rows->update(['sector_id' => $data->value]),
                'set_source' => $rows->update(['source_name' => trim((string) $data->value), 'source_id' => null]),
                'accept_suggestions' => $this->acceptSuggestions($ids),
                default => null,
            };

            $this->duplicates->handle($batch, $ids);
            $this->recount->handle($batch);
        });

        return count($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private function setRegion(array $ids, ?int $regionId): void
    {
        DB::update(
            'UPDATE staged_rows SET region_id = :region, area_id = CASE WHEN area_id IN (SELECT id FROM areas WHERE region_id = :region) THEN area_id END WHERE id = ANY(:ids::bigint[])',
            ['region' => $regionId, 'ids' => '{'.implode(',', $ids).'}'],
        );
        DB::table('staged_rows')->whereIn('id', $ids)->update($this->withoutIssues([IssueCode::UnknownRegion]));
    }

    /**
     * @param  list<int>  $ids
     */
    private function setArea(array $ids, int $areaId): void
    {
        DB::update(
            'UPDATE staged_rows SET area_id = :area, region_id = (SELECT region_id FROM areas WHERE id = :area) WHERE id = ANY(:ids::bigint[])',
            ['area' => $areaId, 'ids' => '{'.implode(',', $ids).'}'],
        );
        DB::table('staged_rows')->whereIn('id', $ids)->update($this->withoutIssues([IssueCode::UnknownArea, IssueCode::UnknownRegion]));
    }

    private function setAction(Builder $rows, string $action): void
    {
        $value = StagedRowAction::fromKey($action);
        if ($value === StagedRowAction::Merge) {
            $rows->whereNotNull('matched_contact_id');
        }

        $rows->update(['action' => $value->value, 'action_locked' => true]);
    }

    /**
     * Apply email-typo fixes and phones found in notes, then drop the issues they solve.
     *
     * @param  list<int>  $ids
     */
    private function acceptSuggestions(array $ids): void
    {
        $typo = IssueCode::EmailTypo->value;
        $notes = IssueCode::PhoneInNotes->value;
        $missing = IssueCode::MissingContactMethod->value;
        $idList = '{'.implode(',', $ids).'}';

        DB::update(<<<SQL
            UPDATE staged_rows s SET
                extra = coalesce(s.extra, '{}'::jsonb) || jsonb_build_object('email_original', s.email),
                email = (SELECT i->>'suggestion' FROM jsonb_array_elements(s.issues) i WHERE i->>'code' = '{$typo}' LIMIT 1)
            WHERE s.id = ANY(?::bigint[]) AND '{$typo}' = ANY(s.issue_codes)
            SQL, [$idList]);

        DB::update(<<<SQL
            UPDATE staged_rows s SET
                mobile_e164 = coalesce(s.mobile_e164, (SELECT i->>'suggestion' FROM jsonb_array_elements(s.issues) i WHERE i->>'code' = '{$notes}' AND i->>'field' = 'mobile_e164' LIMIT 1)),
                landline_e164 = coalesce(s.landline_e164, (SELECT i->>'suggestion' FROM jsonb_array_elements(s.issues) i WHERE i->>'code' = '{$notes}' AND i->>'field' = 'landline_e164' LIMIT 1))
            WHERE s.id = ANY(?::bigint[]) AND '{$notes}' = ANY(s.issue_codes)
            SQL, [$idList]);

        DB::table('staged_rows')->whereIn('id', $ids)->whereRaw("'{$typo}' = ANY(issue_codes) OR '{$notes}' = ANY(issue_codes)")
            ->update($this->withoutIssues([IssueCode::EmailTypo, IssueCode::PhoneInNotes]));

        DB::table('staged_rows')->whereIn('id', $ids)
            ->whereRaw("'{$missing}' = ANY(issue_codes)")
            ->where(fn ($query) => $query->whereNotNull('mobile_e164')->orWhereNotNull('landline_e164')->orWhereNotNull('email'))
            ->update($this->withoutIssues([IssueCode::MissingContactMethod]));
    }

    /**
     * Column updates that remove issues from both issue_codes and issues.
     *
     * @param  list<IssueCode>  $codes
     * @return array{issue_codes: Expression, issues: Expression}
     */
    private function withoutIssues(array $codes): array
    {
        $values = implode(',', array_map(fn (IssueCode $code): string => "'{$code->value}'", $codes));

        return [
            'issue_codes' => DB::raw("ARRAY(SELECT c FROM unnest(issue_codes) c WHERE c NOT IN ({$values}))"),
            'issues' => DB::raw("coalesce((SELECT jsonb_agg(i) FROM jsonb_array_elements(issues) i WHERE i->>'code' NOT IN ({$values})), '[]'::jsonb)"),
        ];
    }
}
