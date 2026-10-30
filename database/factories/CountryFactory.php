<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iso2' => fake()->unique()->lexify('??'),
            'dial_code' => (string) fake()->numberBetween(1, 999),
            'name' => ['en' => fake()->country(), 'ar' => 'دولة'],
        ];
    }
}
