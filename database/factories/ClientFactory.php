<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female', 'other']);
        $firstName = $gender === 'male' ? fake()->firstNameMale() : fake()->firstNameFemale();

        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => $firstName,
            'last_name' => fake()->lastName(),
            'nationality' => fake()->country(),
            'second_nationality' => fake()->optional(0.2)->country(),
            'language' => fake()->randomElement(['es', 'en', 'fr']),
            'second_language' => fake()->optional(0.5)->randomElement(['es', 'en', 'fr']),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'gender' => $gender,
            'passport_number' => strtoupper(fake()->bothify('??######')),
            'passport_country' => fake()->country(),
            'passport_expiry_date' => fake()->dateTimeBetween('+1 year', '+10 years'),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed', 'common_law', 'separated']),
            'profession' => fake()->jobTitle(),
            'description' => fake()->optional(0.3)->paragraph(),
            'email' => fake()->unique()->safeEmail(),
            'residential_address' => fake()->streetAddress(),
            'mailing_address' => fake()->optional(0.5)->streetAddress(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'phone' => fake()->phoneNumber(),
            'secondary_phone' => fake()->optional(0.3)->phoneNumber(),
            'canada_status' => fake()->randomElement([
                'asylum_seeker', 'refugee', 'temporary_resident',
                'permanent_resident', 'visitor', 'student', 'worker',
            ]),
            'status_date' => fake()->optional(0.7)->dateTimeBetween('-5 years', 'now'),
            'arrival_date' => fake()->optional(0.7)->dateTimeBetween('-10 years', 'now'),
            'entry_point' => fake()->optional(0.6)->randomElement(['airport', 'land_border', 'green_path']),
            'iuc' => fake()->optional(0.5)->numerify('##########'),
            'work_permit_number' => fake()->optional(0.4)->numerify('W#######'),
            'study_permit_number' => fake()->optional(0.3)->numerify('S#######'),
            'permit_expiry_date' => fake()->optional(0.5)->dateTimeBetween('now', '+5 years'),
            'status' => fake()->randomElement(['prospect', 'active', 'inactive', 'archived']),
            'is_primary_applicant' => true,
        ];
    }

    /**
     * Indicate that the client is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the client is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the client is an asylum seeker.
     */
    public function asylumSeeker(): static
    {
        return $this->state(fn (array $attributes) => [
            'canada_status' => 'asylum_seeker',
            'entry_point' => fake()->randomElement(['airport', 'land_border', 'green_path']),
            'arrival_date' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * Indicate that the client is a refugee.
     */
    public function refugee(): static
    {
        return $this->state(fn (array $attributes) => [
            'canada_status' => 'refugee',
        ]);
    }

    /**
     * Indicate that the client is a prospect.
     */
    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'prospect',
        ]);
    }

    /**
     * Indicate that the client is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}
