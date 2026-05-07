<?php

namespace Tests\Feature\Workflow;

use App\Models\CaseStage;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceStageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private ImmigrationCase $case;
    private WorkflowStage $stage1;
    private WorkflowStage $stage2;
    private CaseStage $caseStage1;
    private CaseStage $caseStage2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $caseType = CaseType::factory()->create(['tenant_id' => null]);

        [$this->stage1, $this->stage2] = WorkflowStage::factory()->count(2)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $caseType->id],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $caseType->id, 'is_terminal' => true],
        )->create();

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->case = ImmigrationCase::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'client_id'        => $client->id,
            'case_type_id'     => $caseType->id,
            'current_stage_id' => $this->stage1->id,
            'assigned_to'      => $this->admin->id,
        ]);

        // Build CaseStage instances mirroring the workflow_stages so advanceStage works.
        $this->caseStage1 = CaseStage::create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'code'              => $this->stage1->code,
            'sort_order'        => 0,
            'is_active'         => true,
            'is_terminal'       => false,
            'color'             => 'primary',
        ]);
        $this->caseStage2 = CaseStage::create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage2->id,
            'code'              => $this->stage2->code,
            'sort_order'        => 1,
            'is_active'         => true,
            'is_terminal'       => true,
            'color'             => 'success',
        ]);

        $this->case->update(['current_case_stage_id' => $this->caseStage1->id]);
    }

    public function test_advance_stage_blocked_when_required_tasks_pending(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage1)->create([
            'blocks_stage_completion' => true,
            'is_required'             => true,
        ]);

        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'new',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'STAGE_BLOCKED');
    }

    public function test_advance_stage_succeeds_when_no_blocking_tasks(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertOk();

        $this->assertDatabaseHas('cases', [
            'id'                    => $this->case->id,
            'current_case_stage_id' => $this->caseStage2->id,
            'current_stage_id'      => $this->stage2->id,
        ]);
    }

    public function test_advance_stage_to_terminal_stage_closes_case(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertOk();

        $this->case->refresh();
        $this->assertEquals($this->caseStage2->id, $this->case->current_case_stage_id);
        $this->assertEquals($this->stage2->id, $this->case->current_stage_id);
        $this->assertEquals(ImmigrationCase::STATUS_CLOSED, $this->case->status);
        $this->assertNotNull($this->case->closed_at);
    }

    public function test_advance_stage_succeeds_when_blocking_tasks_completed(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage1)->create([
            'blocks_stage_completion' => true,
        ]);

        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertOk();

        $this->assertDatabaseHas('cases', [
            'id'                    => $this->case->id,
            'current_case_stage_id' => $this->caseStage2->id,
        ]);
    }
}
