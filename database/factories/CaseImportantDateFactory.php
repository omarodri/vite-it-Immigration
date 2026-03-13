<?php

namespace Database\Factories;

use App\Models\CaseImportantDate;
use App\Models\ImmigrationCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseImportantDate>
 */
class CaseImportantDateFactory extends Factory
{
    protected $model = CaseImportantDate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => ImmigrationCase::factory(),
            'label' => $this->faker->randomElement([
                'Fecha de inicio', 'Fecha limite legal', 'Fecha de envio IRCC', 'Fecha de decision',
            ]),
            'due_date' => $this->faker->optional(0.7)->dateTimeBetween('now', '+6 months')?->format('Y-m-d'),
            'sort_order' => $this->faker->numberBetween(0, 5),
        ];
    }
}
