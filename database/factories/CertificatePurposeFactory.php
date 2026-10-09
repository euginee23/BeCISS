<?php

namespace Database\Factories;

use App\Models\CertificatePurpose;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificatePurpose>
 */
class CertificatePurposeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(3, true)),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(100, 200),
        ];
    }

    /**
     * A purpose that is no longer offered on the request forms.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
