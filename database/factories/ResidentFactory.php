<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.7)->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional(0.1)->randomElement(['Jr.', 'Sr.', 'III', 'IV']),
            'birthdate' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'civil_status' => fake()->randomElement(['single', 'married', 'widowed', 'separated']),
            'contact_number' => fake()->optional(0.8)->phoneNumber(),
            'address' => fake()->streetAddress(),
            'purok' => fake()->optional(0.9)->randomElement(['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7']),
            'years_of_residency' => fake()->numberBetween(1, 50),
            'occupation' => fake()->optional(0.7)->jobTitle(),
            'monthly_income' => fake()->optional(0.6)->randomFloat(2, 5000, 100000),
            'is_voter' => fake()->boolean(70),
            'status' => 'approved',
            'approved_at' => now(),
        ];
    }

    /**
     * Set status to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
        ]);
    }

    /**
     * Set status to rejected with a reason.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejection_reason' => 'Incomplete or invalid information provided.',
            'approved_at' => null,
        ]);
    }

    /**
     * Set as a voter.
     */
    public function voter(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_voter' => true,
        ]);
    }

    /**
     * Set as male.
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'male',
            'first_name' => fake()->firstNameMale(),
        ]);
    }

    /**
     * Set as female.
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'female',
            'first_name' => fake()->firstNameFemale(),
        ]);
    }
}
