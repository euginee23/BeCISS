<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serviceTypes = array_keys(Appointment::SERVICE_TYPES);

        return [
            'resident_id' => Resident::factory(),
            'reference_number' => Appointment::generateReferenceNumber(),
            'service_type' => fake()->randomElement($serviceTypes),
            'description' => fake()->sentence(10),
            'appointment_date' => fake()->dateTimeBetween('now', '+2 weeks'),
            'appointment_time' => fake()->time('H:i:00'),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'status' => 'scheduled',
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Set as confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Set as in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    /**
     * Set as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Set as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set appointment for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_date' => now()->toDateString(),
        ]);
    }

    /**
     * Set as certificate request.
     */
    public function certificateRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'certificate_request',
            'description' => 'Request for ' . fake()->randomElement(['Barangay Clearance', 'Certificate of Residency', 'Certificate of Indigency']),
        ]);
    }

    /**
     * Set as consultation.
     */
    public function consultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => 'consultation',
            'description' => 'Consultation regarding ' . fake()->word(),
        ]);
    }
}
