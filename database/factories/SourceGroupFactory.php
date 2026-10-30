<?php

namespace Database\Factories;

use App\Models\SourceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceGroup>
 */
class SourceGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => fake()->words(2, true), 'ar' => 'مجموعة'],
        ];
    }
}
