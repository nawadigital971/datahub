<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => fake()->streetName(), 'ar' => 'منطقة'],
            'aliases' => [],
        ];
    }
}
