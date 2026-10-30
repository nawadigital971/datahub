<?php

namespace Database\Factories;

use App\Enums\LawfulBasis;
use App\Models\Source;
use App\Models\SourceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_group_id' => SourceGroup::factory(),
            'slug' => fake()->unique()->slug(3),
            'name' => ['en' => fake()->words(3, true), 'ar' => 'مصدر'],
            'collected_year' => fake()->numberBetween(2013, 2026),
            'lawful_basis' => LawfulBasis::Unknown,
            'notes' => null,
        ];
    }
}
