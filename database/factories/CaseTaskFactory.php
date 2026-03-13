<?php

namespace Database\Factories;

use App\Models\CaseTask;
use App\Models\ImmigrationCase;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseTaskFactory extends Factory
{
    protected $model = CaseTask::class;

    public function definition(): array
    {
        return [
            'case_id' => ImmigrationCase::factory(),
            'label' => $this->faker->randomElement([
                'Firma de Contrato',
                'Recepcion de Documentos',
                'Revision de Documentos',
                'Preparacion de la Solicitud',
                'Envio de la Solicitud',
            ]),
            'is_completed' => $this->faker->boolean(30),
            'is_custom' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(0, 9),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
