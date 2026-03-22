<?php

namespace Database\Factories;

use App\Models\BarangayOfficial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarangayOfficial>
 */
class BarangayOfficialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->randomElement(['Kagawad', 'SK Chairperson', 'Barangay Captain', 'Barangay Secretary', 'Barangay Treasurer']),
            'committee' => fake()->optional(0.7)->randomElement(['Peace and Order', 'Health', 'Education', 'Infrastructure', 'Environment', 'Finance', 'Youth Affairs']),
            'term_start' => fake()->dateTimeBetween('-4 years', '-1 year'),
            'term_end' => fake()->optional(0.5)->dateTimeBetween('now', '+3 years'),
            'photo_path' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
