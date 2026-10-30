<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use App\Models\ImportBatch;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'origin' => ImportOrigin::Upload,
            'user_id' => User::factory(),
            'status' => ImportBatchStatus::Ready,
            'original_filename' => fake()->word().'.xlsx',
        ];
    }
}
