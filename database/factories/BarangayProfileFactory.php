<?php

namespace Database\Factories;

use App\Models\BarangayProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarangayProfile>
 */
class BarangayProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_name' => 'Barangay '.fake()->word(),
            'municipality' => fake()->city(),
            'province' => fake()->state(),
            'zip_code' => fake()->postcode(),
            'address' => fake()->streetAddress(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'website' => null,
            'captain_name' => fake()->name(),
            'secretary_name' => fake()->name(),
            'treasurer_name' => fake()->name(),
            'office_hours' => 'Monday - Friday, 8:00 AM - 5:00 PM',
            'logo_path' => null,
        ];
    }
}
