<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'slug' => Str::snake($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'requirements' => null,
            'fee' => 0,
            'is_active' => true,
            'is_bookable' => true,
            'show_on_website' => true,
            'is_system' => false,
            'sort_order' => fake()->numberBetween(20, 90),
        ];
    }

    /**
     * A service that is listed but cannot be booked online.
     */
    public function notBookable(): static
    {
        return $this->state(fn (): array => [
            'is_bookable' => false,
        ]);
    }

    /**
     * A service that is no longer offered.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
