<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Companion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Companion>
 */
class CompanionFactory extends Factory
{
    protected $model = Companion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female', 'other']);
        $firstName = $gender === 'male' ? fake()->firstNameMale() : fake()->firstNameFemale();
        $relationship = fake()->randomElement(['spouse', 'child', 'parent', 'sibling', 'other']);

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'first_name' => $firstName,
            'last_name' => fake()->lastName(),
            'relationship' => $relationship,
            'relationship_other' => $relationship === 'other' ? fake()->word() : null,
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-1 year'),
            'gender' => $gender,
            'passport_number' => fake()->optional(0.7)->bothify('??######'),
            'passport_country' => fake()->optional(0.7)->country(),
            'passport_expiry_date' => fake()->optional(0.5)->dateTimeBetween('+1 year', '+10 years'),
            'nationality' => fake()->country(),
            'iuc' => fake()->optional(0.3)->bothify('??######'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Indicate that the companion is a spouse.
     */
    public function spouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'spouse',
            'relationship_other' => null,
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
        ]);
    }

    /**
     * Indicate that the companion is a child.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'child',
            'relationship_other' => null,
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-1 year'),
        ]);
    }

    /**
     * Indicate that the companion is a parent.
     */
    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'parent',
            'relationship_other' => null,
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-40 years'),
        ]);
    }

    /**
     * Indicate that the companion is a sibling.
     */
    public function sibling(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'sibling',
            'relationship_other' => null,
        ]);
    }

    /**
     * Indicate that the companion has an 'other' relationship.
     */
    public function other(string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'other',
            'relationship_other' => $description ?? fake()->word(),
        ]);
    }
}
