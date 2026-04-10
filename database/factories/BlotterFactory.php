<?php

namespace Database\Factories;

use App\Models\Blotter;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blotter>
 */
class BlotterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = array_keys(Blotter::TYPES);

        return [
            'resident_id' => Resident::factory(),
            'blotter_number' => 'BLT-'.date('Y').'-'.str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'incident_type' => fake()->randomElement($types),
            'incident_datetime' => fake()->dateTimeBetween('-30 days', 'now'),
            'owner_name' => fake()->optional(0.5)->name(),
            'respondent_name' => fake()->optional(0.7)->name(),
            'narrative' => fake()->paragraph(3),
            'status' => 'pending',
            'remarks' => fake()->optional(0.3)->sentence(),
            'fee' => 50.00,
            'is_paid' => false,
        ];
    }

    /**
     * Indicate the blotter is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate the blotter is ready for pickup.
     */
    public function readyForPickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready_for_pickup',
            'processed_at' => now()->subHours(2),
        ]);
    }

    /**
     * Indicate the blotter is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_at' => now()->subDays(2),
            'completed_at' => now(),
            'is_paid' => true,
            'or_number' => fake()->numerify('OR-####-####'),
        ]);
    }

    /**
     * Indicate the blotter is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate the blotter is from a walk-in (unregistered) complainant.
     */
    public function walkin(): static
    {
        return $this->state(fn (array $attributes) => [
            'resident_id' => null,
            'complainant_name' => fake()->name(),
            'complainant_purok' => 'Purok '.fake()->numberBetween(1, 10),
            'complainant_street' => fake()->streetName(),
            'complainant_house_number' => fake()->buildingNumber(),
            'complainant_contact' => '09'.fake()->numerify('#########'),
        ]);
    }
}
