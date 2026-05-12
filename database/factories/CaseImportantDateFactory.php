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
            'due_date' => $this->faker->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d'),
            'sort_order' => $this->faker->numberBetween(0, 5),
            'calendar_event_id' => null,
        ];
    }

    public function overdue(): self
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(random_int(1, 25))->toDateString(),
        ]);
    }

    public function today(): self
    {
        return $this->state(fn () => [
            'due_date' => now()->toDateString(),
        ]);
    }

    public function upcoming(int $days = 7): self
    {
        return $this->state(fn () => [
            'due_date' => now()->addDays($days)->toDateString(),
        ]);
    }
}
