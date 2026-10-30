<?php

namespace Database\Factories;

use App\Enums\ExportChannel;
use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filters' => [],
            'channel' => ExportChannel::Email,
            'hashed' => false,
            'status' => ExportStatus::Queued,
            'purpose' => fake()->sentence(),
        ];
    }
}
