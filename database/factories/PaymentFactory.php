<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => Certificate::class,
            'payable_id' => Certificate::factory(),
            'or_number' => fake()->unique()->numerify('OR-######'),
            'amount' => fake()->randomElement([30, 50, 100]),
            'paid_at' => now(),
            'received_by' => User::factory()->staff(),
            'payor_name' => fake()->name(),
            'remarks' => null,
        ];
    }
}
