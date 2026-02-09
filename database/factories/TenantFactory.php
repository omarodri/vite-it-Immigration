<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'settings' => [
                'logo_url' => null,
                'primary_color' => '#4361ee',
                'company_name' => $name,
                'company_email' => fake()->companyEmail(),
                'company_phone' => fake()->phoneNumber(),
            ],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant has Microsoft OAuth configured.
     */
    public function withMicrosoftOAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'ms_client_id' => fake()->uuid(),
            'ms_client_secret' => fake()->sha256(),
        ]);
    }

    /**
     * Indicate that the tenant has Google OAuth configured.
     */
    public function withGoogleOAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_client_id' => fake()->uuid() . '.apps.googleusercontent.com',
            'google_client_secret' => fake()->sha256(),
        ]);
    }
}
