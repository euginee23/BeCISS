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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = array_keys(Certificate::TYPES);

        return [
            'resident_id' => Resident::factory(),
            'certificate_number' => Certificate::generateCertificateNumber(),
            'type' => fake()->randomElement($types),
            'purpose' => fake()->randomElement([
                'Employment',
                'Travel',
                'School Enrollment',
                'Bank Transaction',
                'Legal Document',
                'Government Transaction',
                'Medical Assistance',
            ]),
            'status' => 'pending',
            'remarks' => fake()->optional(0.3)->sentence(),
            'fee' => fake()->randomFloat(2, 0, 500),
            'is_paid' => false,
        ];
    }

    /**
     * Indicate the certificate is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Indicate the certificate is ready for pickup.
     */
    public function readyForPickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready_for_pickup',
            'processed_at' => now()->subHours(2),
        ]);
    }

    /**
     * Indicate the certificate is completed.
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
