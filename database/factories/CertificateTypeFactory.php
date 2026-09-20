<?php

namespace Database\Factories;

use App\Models\CertificateType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificateType>
 */
class CertificateTypeFactory extends Factory
{
    protected $model = CertificateType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'slug' => Str::snake($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'fee' => fake()->randomElement([0, 30, 50, 100]),
            'is_active' => true,
            'available_to_residents' => true,
            'requires_ctc' => false,
            'template_disk' => 'local',
            'template_path' => null,
            'template_original_name' => null,
            'template_placeholders' => null,
            'template_uploaded_at' => null,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * A type that is hidden from the resident-facing request form.
     */
    public function staffOnly(): static
    {
        return $this->state(fn (): array => [
            'available_to_residents' => false,
        ]);
    }

    /**
     * A type that is no longer offered.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    /**
     * A type that collects Community Tax Certificate details on issuance.
     */
    public function requiresCtc(): static
    {
        return $this->state(fn (): array => [
            'requires_ctc' => true,
        ]);
    }

    /**
     * A free type, which needs no official receipt to be released.
     */
    public function free(): static
    {
        return $this->state(fn (): array => [
            'fee' => 0,
        ]);
    }
}
