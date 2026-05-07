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

class TaskBoardOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private CaseType $caseType;
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

        $this->caseType = CaseType::factory()->create(['tenant_id' => null]);

        [$this->stage1, $this->stage2] = WorkflowStage::factory()->count(2)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'is_terminal' => true],
        )->create();

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->case = ImmigrationCase::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'client_id'        => $client->id,
            'case_type_id'     => $this->caseType->id,
            'current_stage_id' => $this->stage1->id,
            'assigned_to'      => $this->admin->id,
        ]);

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

    public function test_can_create_ad_hoc_task(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/workflow-tasks", [
                'case_stage_id' => $this->caseStage1->id,
                'subject'       => 'Tarea ad-hoc nueva',
                'type'          => 'other',
                'priority'      => 'medium',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', 'Tarea ad-hoc nueva')
            ->assertJsonPath('data.task_template_id', null)
            ->assertJsonPath('data.is_adhoc', true);

        $this->assertDatabaseHas('tasks', [
            'case_id'           => $this->case->id,
            'case_stage_id'     => $this->caseStage1->id,
            'workflow_stage_id' => $this->stage1->id,
            'task_template_id'  => null,
            'subject'           => 'Tarea ad-hoc nueva',
            'sort_order'        => 0,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/workflow-tasks", [
                'case_stage_id' => $this->caseStage1->id,
                'subject'       => 'Segunda',
                'type'          => 'other',
                'priority'      => 'low',
            ])->assertCreated()
              ->assertJsonPath('data.sort_order', 1);
    }

    public function test_create_ad_hoc_task_validates_stage_belongs_to_case_type(): void
    {
        // CaseStage from a different case (different case_id, even if same tenant).
        $otherCaseType = CaseType::factory()->create(['tenant_id' => null]);
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCase = ImmigrationCase::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $otherClient->id,
            'case_type_id' => $otherCaseType->id,
        ]);
        $otherStage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $otherCaseType->id,
        ]);
        $otherCaseStage = CaseStage::create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $otherCase->id,
            'workflow_stage_id' => $otherStage->id,
            'code'              => $otherStage->code,
            'sort_order'        => 0,
            'is_active'         => true,
            'is_terminal'       => false,
            'color'             => 'primary',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/workflow-tasks", [
                'case_stage_id' => $otherCaseStage->id,
                'subject'       => 'Invalida',
                'type'          => 'other',
                'priority'      => 'medium',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['case_stage_id']);
    }

    public function test_can_move_task_within_same_stage(): void
    {
        $tasks = collect(range(0, 2))->map(fn ($i) => Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'requester_id'      => $this->admin->id,
            'sort_order'        => $i,
        ]));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/workflow-tasks/{$tasks[0]->id}/move", [
                'source_stage_id' => $this->caseStage1->id,
                'target_stage_id' => $this->caseStage1->id,
                'target_index'    => 2,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $tasks[1]->id, 'sort_order' => 0, 'case_stage_id' => $this->caseStage1->id]);
        $this->assertDatabaseHas('tasks', ['id' => $tasks[2]->id, 'sort_order' => 1, 'case_stage_id' => $this->caseStage1->id]);
        $this->assertDatabaseHas('tasks', ['id' => $tasks[0]->id, 'sort_order' => 2, 'case_stage_id' => $this->caseStage1->id]);
    }

    public function test_can_move_task_to_different_stage(): void
    {
        $s1 = collect(range(0, 2))->map(fn ($i) => Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'requester_id'      => $this->admin->id,
            'sort_order'        => $i,
        ]));
        $s2 = collect(range(0, 1))->map(fn ($i) => Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage2->id,
            'case_stage_id'     => $this->caseStage2->id,
            'requester_id'      => $this->admin->id,
            'sort_order'        => $i,
        ]));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/workflow-tasks/{$s1[1]->id}/move", [
                'source_stage_id' => $this->caseStage1->id,
                'target_stage_id' => $this->caseStage2->id,
                'target_index'    => 1,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $s1[0]->id, 'sort_order' => 0, 'case_stage_id' => $this->caseStage1->id]);
        $this->assertDatabaseHas('tasks', ['id' => $s1[2]->id, 'sort_order' => 1, 'case_stage_id' => $this->caseStage1->id]);

        $this->assertDatabaseHas('tasks', ['id' => $s2[0]->id, 'sort_order' => 0, 'case_stage_id' => $this->caseStage2->id]);
        $this->assertDatabaseHas('tasks', ['id' => $s1[1]->id, 'sort_order' => 1, 'case_stage_id' => $this->caseStage2->id]);
        $this->assertDatabaseHas('tasks', ['id' => $s2[1]->id, 'sort_order' => 2, 'case_stage_id' => $this->caseStage2->id]);
    }

    public function test_move_task_validates_target_stage(): void
    {
        $task = Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'requester_id'      => $this->admin->id,
        ]);

        // Stage from a different case.
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCase = ImmigrationCase::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $otherClient->id,
            'case_type_id' => $this->caseType->id,
        ]);
        $otherCaseStage = CaseStage::create([
            'tenant_id'   => $this->tenant->id,
            'case_id'     => $otherCase->id,
            'code'        => 'other',
            'sort_order'  => 0,
            'is_active'   => true,
            'is_terminal' => false,
            'color'       => 'primary',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/workflow-tasks/{$task->id}/move", [
                'source_stage_id' => $this->caseStage1->id,
                'target_stage_id' => $otherCaseStage->id,
                'target_index'    => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target_stage_id']);
    }

    public function test_can_soft_delete_task_and_restore(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage1)->create();

        $task = Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/workflow-tasks/{$task->id}")
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/workflow-tasks/{$task->id}/restore");

        $response->assertOk()
            ->assertJsonPath('data.id', $task->id);

        $this->assertDatabaseHas('tasks', [
            'id'         => $task->id,
            'deleted_at' => null,
        ]);
    }

    public function test_progress_excludes_ad_hoc_tasks(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage1)->create();

        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
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

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/workflow-tasks", [
                'case_stage_id' => $this->caseStage1->id,
                'subject'       => 'Ad-hoc',
                'type'          => 'other',
                'priority'      => 'medium',
            ])->assertCreated();

        $this->case->refresh();

        $this->assertEquals(50, $this->case->progress);
    }

    public function test_listing_trashed_tasks_returns_only_soft_deleted(): void
    {
        $kept = Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'requester_id'      => $this->admin->id,
        ]);

        $trashed = Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $this->stage1->id,
            'case_stage_id'     => $this->caseStage1->id,
            'requester_id'      => $this->admin->id,
        ]);
        $trashed->delete();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/cases/{$this->case->id}/workflow-tasks/trashed");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trashed->id);
    }

    public function test_cannot_move_task_from_other_tenant_case(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser->assignRole('admin');

        $otherCaseType = CaseType::factory()->create(['tenant_id' => null]);
        $otherStage = WorkflowStage::factory()->create([
            'tenant_id'    => $otherTenant->id,
            'case_type_id' => $otherCaseType->id,
        ]);
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCase = ImmigrationCase::factory()->create([
            'tenant_id'    => $otherTenant->id,
            'client_id'    => $otherClient->id,
            'case_type_id' => $otherCaseType->id,
        ]);
        $otherCaseStage = CaseStage::create([
            'tenant_id'         => $otherTenant->id,
            'case_id'           => $otherCase->id,
            'workflow_stage_id' => $otherStage->id,
            'code'              => 'other',
            'sort_order'        => 0,
            'is_active'         => true,
            'is_terminal'       => false,
            'color'             => 'primary',
        ]);
        $otherTask = Task::factory()->create([
            'tenant_id'         => $otherTenant->id,
            'case_id'           => $otherCase->id,
            'workflow_stage_id' => $otherStage->id,
            'case_stage_id'     => $otherCaseStage->id,
            'requester_id'      => $otherUser->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$otherCase->id}/workflow-tasks/{$otherTask->id}/move", [
                'source_stage_id' => $otherCaseStage->id,
                'target_stage_id' => $otherCaseStage->id,
                'target_index'    => 0,
            ]);

        $response->assertNotFound();
    }
}
