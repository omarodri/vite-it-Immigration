<?php

namespace Database\Factories;

use App\Models\CaseType;
use App\Models\Tenant;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStage>
 */
class WorkflowStageFactory extends Factory
{
    protected $model = WorkflowStage::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => Tenant::factory(),
            'case_type_id'=> CaseType::factory(),
            'code'        => strtolower(fake()->unique()->lexify('stage_???')),
            'sort_order'  => fake()->numberBetween(0, 10),
            'is_active'   => true,
            'is_terminal' => false,
            'color'       => fake()->randomElement(['primary', 'success', 'warning', 'danger', 'info']),
        ];
    }

    public function terminal(): static
    {
        return $this->state(['is_terminal' => true]);
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function forCaseType(CaseType $caseType): static
    {
        return $this->state([
            'case_type_id' => $caseType->id,
            'tenant_id'    => $caseType->tenant_id,
        ]);
    }
}
