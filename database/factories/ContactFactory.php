<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Enums\EmailStatus;
use App\Enums\Gender;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContactType::Person,
            'name_en' => fake()->name(),
            'name_ar' => null,
            'company_name' => null,
            'gender' => fake()->randomElement([Gender::Male, Gender::Female, null]),
            'mobile_e164' => '+9715'.fake()->unique()->numerify('########'),
            'landline_e164' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_status' => EmailStatus::Valid,
            'source_ids' => [],
            'source_count' => 0,
            'first_seen_year' => 2020,
            'last_seen_year' => 2024,
            'do_not_contact' => false,
            'is_junk' => false,
        ];
    }

    /**
     * A business record with a company name and landline.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContactType::Business,
            'company_name' => fake()->company(),
            'landline_e164' => '+9714'.fake()->unique()->numerify('#######'),
        ]);
    }

    /**
     * A contact on the do-not-contact list.
     */
    public function doNotContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'do_not_contact' => true,
        ]);
    }
}
