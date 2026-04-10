<?php

namespace Database\Factories;

use App\Models\ServiceFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceFee>
 */
class ServiceFeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type' => fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'fee' => fake()->randomFloat(2, 0, 500),
            'is_active' => true,
        ];
    }
}
