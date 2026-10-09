<?php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * The types seeded by the certificate_types migration.
     *
     * @var list<string>
     */
    private const array BUILT_IN_TYPES = [
        'barangay_clearance',
        'barangay_certification',
        'certificate_of_residency',
        'certificate_of_indigency',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'certificate_number' => sprintf('CERT-%s-%05d', now()->format('Y'), fake()->unique()->numberBetween(1, 99999)),
            'type' => fake()->randomElement(self::BUILT_IN_TYPES),
            'purpose' => fake()->randomElement([
                'Employment / Job Application',
                'Loan Application',
                'Scholarship / School Enrollment',
                'Travel / Passport Application',
                'Medical / Health Services',
            ]),
            'status' => 'pending',
            'remarks' => fake()->optional(0.3)->sentence(),
            'fee' => fake()->randomFloat(2, 0, 500),
            'is_paid' => false,
        ];
    }

    /**
     * Indicate the certificate was approved and is waiting to be paid.
     */
    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'awaiting_payment',
            'approved_at' => now(),
            'fee' => 50.00,
            'is_paid' => false,
        ]);
    }

    /**
     * Indicate the certificate has been paid for.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
            'or_number' => fake()->unique()->numerify('OR-######'),
        ]);
    }

    /**
     * Indicate the certificate is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'approved_at' => now(),
            'processed_at' => now(),
            'is_paid' => true,
            'or_number' => fake()->unique()->numerify('OR-######'),
        ]);
    }

    /**
     * Indicate the certificate is ready for pickup.
     */
    public function readyForPickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready_for_pickup',
            'approved_at' => now()->subHours(3),
            'processed_at' => now()->subHours(2),
            'issued_at' => now(),
            'is_paid' => true,
            'or_number' => fake()->unique()->numerify('OR-######'),
        ]);
    }

    /**
     * Indicate the certificate is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'approved_at' => now()->subDays(2),
            'processed_at' => now()->subDays(2),
            'completed_at' => now(),
            'issued_at' => now(),
            'is_paid' => true,
            'or_number' => fake()->unique()->numerify('OR-######'),
        ]);
    }

    /**
     * Indicate the certificate is rejected.
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
     * Set as barangay clearance.
     */
    public function barangayClearance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'barangay_clearance',
            'fee' => 50.00,
        ]);
    }

    /**
     * Set as barangay certification.
     */
    public function barangayCertification(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'barangay_certification',
            'fee' => 50.00,
        ]);
    }

    /**
     * Set as certificate of residency.
     */
    public function residency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'certificate_of_residency',
            'fee' => 30.00,
        ]);
    }

    /**
     * Set as certificate of indigency.
     */
    public function indigency(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'certificate_of_indigency',
            'fee' => 0.00,
        ]);
    }
}
