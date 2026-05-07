<?php

namespace Database\Factories;

use App\Models\TaskTemplate;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskTemplate>
 */
class TaskTemplateFactory extends Factory
{
    protected $model = TaskTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id'               => null,
            'workflow_stage_id'       => WorkflowStage::factory(),
            'code'                    => strtolower(fake()->unique()->lexify('tmpl_???')),
            'sort_order'              => fake()->numberBetween(0, 10),
            'is_required'             => true,
            'blocks_stage_completion' => true,
            'default_type'            => fake()->randomElement(['filing', 'document', 'other']),
            'default_priority'        => 'medium',
            'due_offset_days'         => null,
            'is_active'               => true,
        ];
    }

    public function forStage(WorkflowStage $stage): static
    {
        return $this->state([
            'workflow_stage_id' => $stage->id,
            'tenant_id'         => $stage->tenant_id,
        ]);
    }

    public function optional(): static
    {
        return $this->state(['is_required' => false, 'blocks_stage_completion' => false]);
    }
}
