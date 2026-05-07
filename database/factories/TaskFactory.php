<?php

namespace Database\Factories;

use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'tenant_id'         => Tenant::factory(),
            'case_id'           => ImmigrationCase::factory(),
            'workflow_stage_id' => null,
            'task_template_id'  => null,
            'requester_id'      => User::factory(),
            'assigned_to'       => null,
            'subject'           => fake()->sentence(4),
            'description'       => null,
            'type'              => fake()->randomElement(['filing', 'document', 'other']),
            'priority'          => 'medium',
            'status'            => 'new',
            'due_date'          => null,
            'sort_order'        => 0,
        ];
    }
}
