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
            'house_number' => fake()->buildingNumber(),
            'street' => fake()->streetName(),
            'purok' => fake()->randomElement(Resident::PUROKS),
            'residency_start_date' => fake()->dateTimeBetween('-40 years', '-1 year'),
            'occupation' => fake()->optional(0.7)->jobTitle(),
            'monthly_income' => fake()->optional(0.6)->randomFloat(2, 5000, 100000),
            'is_voter' => fake()->boolean(70),
            'place_of_birth' => fake()->optional(0.7)->city(),
            'citizenship' => 'Filipino',
            'religion' => fake()->optional(0.7)->randomElement(['Roman Catholic', 'Iglesia ni Cristo', 'Islam', 'Born Again Christian']),
            'blood_type' => fake()->optional(0.4)->randomElement(Resident::BLOOD_TYPES),
            'educational_attainment' => fake()->optional(0.8)->randomElement(array_keys(Resident::EDUCATION_LEVELS)),
            'employment_status' => fake()->optional(0.8)->randomElement(array_keys(Resident::EMPLOYMENT_STATUSES)),
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
     * A resident aged 60 or older.
     */
    public function senior(): static
    {
        return $this->state(fn (array $attributes) => [
            'birthdate' => now()->subYears(fake()->numberBetween(60, 85))->subDays(10),
        ]);
    }

    /**
     * A resident younger than 18.
     */
    public function minor(): static
    {
        return $this->state(fn (array $attributes) => [
            'birthdate' => now()->subYears(fake()->numberBetween(1, 17))->subDays(10),
        ]);
    }

    /**
     * A resident between 18 and 59.
     */
    public function adult(): static
    {
        return $this->state(fn (array $attributes) => [
            'birthdate' => now()->subYears(fake()->numberBetween(18, 59))->subDays(10),
        ]);
    }

    /**
     * A person with disability.
     */
    public function pwd(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pwd' => true,
            'pwd_id_number' => fake()->numerify('PWD-####-####'),
        ]);
    }

    /**
     * A solo parent.
     */
    public function soloParent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_solo_parent' => true,
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
