<?php

namespace Database\Factories;

use App\Enums\EmailVerificationTaskStatus;
use App\Models\EmailVerificationTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailVerificationTask>
 */
class EmailVerificationTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emails = [fake()->unique()->safeEmail(), fake()->unique()->safeEmail()];

        return [
            'reoon_task_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'status' => EmailVerificationTaskStatus::Submitted,
            'emails' => $emails,
            'contact_ids' => [],
            'count_submitted' => count($emails),
            'count_processing' => count($emails),
        ];
    }
}
