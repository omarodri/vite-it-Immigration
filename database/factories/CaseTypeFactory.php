<?php

namespace Database\Factories;

use App\Models\CaseType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseType>
 */
class CaseTypeFactory extends Factory
{
    protected $model = CaseType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement([
            CaseType::CATEGORY_TEMPORARY,
            CaseType::CATEGORY_PERMANENT,
            CaseType::CATEGORY_REFUGEE,
            CaseType::CATEGORY_CITIZENSHIP,
        ]);

        return [
            'tenant_id' => null, // Global by default
            'name' => fake()->words(2, true) . ' Visa',
            'code' => strtoupper(fake()->unique()->lexify('???_???')),
            'category' => $category,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the case type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the case type is for temporary residence.
     */
    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CaseType::CATEGORY_TEMPORARY,
        ]);
    }

    /**
     * Indicate that the case type is for permanent residence.
     */
    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CaseType::CATEGORY_PERMANENT,
        ]);
    }

    /**
     * Indicate that the case type is for refugee/asylum.
     */
    public function refugee(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CaseType::CATEGORY_REFUGEE,
        ]);
    }

    /**
     * Indicate that the case type is for citizenship.
     */
    public function citizenship(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => CaseType::CATEGORY_CITIZENSHIP,
        ]);
    }

     /* Indicate that the case type belongs to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
