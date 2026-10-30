<?php

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StagedRow>
 */
class StagedRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 1_000_000),
            'raw' => ['Name' => fake()->name()],
            'name_en' => fake()->name(),
            'mobile_e164' => '+9715'.fake()->numerify('########'),
            'issue_codes' => [],
            'issues' => [],
            'severity' => RowSeverity::Clean,
            'match_status' => MatchStatus::New,
            'action' => StagedRowAction::Create,
        ];
    }
}
