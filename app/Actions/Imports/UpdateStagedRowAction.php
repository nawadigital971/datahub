<?php

namespace App\Actions\Imports;

use App\Actions\Normalization\NormalizePhoneAction;
use App\DTOs\Imports\AnalysisContext;
use App\DTOs\Imports\MappedRow;
use App\DTOs\Imports\StagedRowEditData;
use App\Enums\StagedRowAction;
use App\Models\StagedRow;
use App\Models\StagedRowEdit;
use App\Settings\DataSettings;
use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * One inline edit: logs it, re-normalizes and re-checks that row (and rows sharing its phones/emails),
 * then refreshes the batch counts.
 */
class UpdateStagedRowAction
{
    public function __construct(
        private AnalyzeRowAction $analyze,
        private NormalizePhoneAction $phones,
        private DetectDuplicatesAction $duplicates,
        private RecountBatchAction $recount,
        private DataSettings $settings,
    ) {}

    public function handle(StagedRow $row, StagedRowEditData $edit): StagedRow
    {
        $batch = $row->importBatch;

        return DB::transaction(function () use ($row, $edit, $batch): StagedRow {
            $oldValue = $this->stringValue($row->{$edit->field});
            $keysBefore = [$row->mobile_e164, $row->landline_e164, $row->email];

            if ($edit->field === 'action') {
                $row->fill([
                    'action' => match ($edit->value) {
                        'merge' => StagedRowAction::Merge,
                        'skip' => StagedRowAction::Skip,
                        default => StagedRowAction::Create,
                    },
                    'action_locked' => true,
                ]);
            } else {
                $value = $this->normalizedValue($edit->field, $edit->value);
                $extra = $row->extra ?? [];
                if (in_array($edit->field, ['mobile_e164', 'landline_e164'], true)) {
                    unset($extra['invalid_phones']);
                }
                if ($edit->field === 'email') {
                    unset($extra['invalid_email']);
                }
                if ($edit->field === 'region_id') {
                    $row->area_id = null;
                }
                $row->extra = $extra;

                $analyzed = $this->analyze->handle(
                    MappedRow::fromStagedRow($row, [$edit->field => $value]),
                    new AnalysisContext($row->seen_year, $this->settings->stale_data_year_threshold),
                );
                $row->fill([...$analyzed->attributes(), $edit->field => $value]);
            }

            $row->fill(['edited_by' => $edit->userId, 'edited_at' => now()])->save();

            StagedRowEdit::query()->create([
                'staged_row_id' => $row->id,
                'field' => $edit->field,
                'old_value' => $oldValue,
                'new_value' => $this->stringValue($row->{$edit->field}),
                'user_id' => $edit->userId,
            ]);

            $this->duplicates->handle($batch, $this->affectedRows($row, $keysBefore));
            $this->recount->handle($batch);

            return $row->refresh();
        });
    }

    private function normalizedValue(string $field, string|int|null $value): string|int|null
    {
        if (in_array($field, ['mobile_e164', 'landline_e164'], true) && is_string($value)) {
            return $this->phones->handle($value)[0]->e164 ?? null;
        }
        if ($field === 'email' && is_string($value)) {
            return mb_strtolower(trim($value));
        }

        return $value;
    }

    /**
     * The edited row plus rows in the batch that share its old or new phones/emails.
     *
     * @param  list<string|null>  $keysBefore
     * @return list<int>
     */
    private function affectedRows(StagedRow $row, array $keysBefore): array
    {
        $keys = array_values(array_unique(array_filter([...$keysBefore, $row->mobile_e164, $row->landline_e164, $row->email])));

        $ids = $keys === [] ? [] : DB::table('staged_rows')
            ->where('import_batch_id', $row->import_batch_id)
            ->where(fn ($query) => $query->whereIn('mobile_e164', $keys)->orWhereIn('landline_e164', $keys)->orWhereIn('email', $keys)->orWhere('dup_of_row_id', $row->id))
            ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        return array_values(array_unique([$row->id, ...$ids]));
    }

    private function stringValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value) => (string) $value,
            default => json_encode($value) ?: null,
        };
    }
}
