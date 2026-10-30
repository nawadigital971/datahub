<?php

namespace Database\Factories;

use App\Enums\DncReason;
use App\Enums\SuppressionKind;
use App\Models\Suppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suppression>
 */
class SuppressionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => SuppressionKind::Phone,
            'value' => '+9715'.fake()->unique()->numerify('########'),
            'reason' => DncReason::OptedOut,
        ];
    }
}
