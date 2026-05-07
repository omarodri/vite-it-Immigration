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

class CaseStagesManagementTest extends TestCase
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
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'code' => 'stage_one'],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'code' => 'stage_two', 'is_terminal' => true],
        )->create();

        // Add Spanish translations to template stages so instantiation copies them.
        $this->stage1->setTranslations('name', ['es' => 'Etapa 1', 'en' => 'Stage 1']);
        $this->stage2->setTranslations('name', ['es' => 'Etapa 2', 'en' => 'Stage 2']);

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->case = ImmigrationCase::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $client->id,
            'case_type_id' => $this->caseType->id,
            'assigned_to'  => $this->admin->id,
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
        $this->caseStage1->setTranslations('name', ['es' => 'Etapa 1']);

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
        $this->caseStage2->setTranslations('name', ['es' => 'Etapa 2']);

        $this->case->update([
            'current_stage_id'      => $this->stage1->id,
            'current_case_stage_id' => $this->caseStage1->id,
        ]);
    }

    public function test_consultant_can_list_case_stages_after_case_creation(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/cases/{$this->case->id}/stages");

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertEquals($this->caseStage1->id, $response->json('data.0.id'));
    }

    public function test_consultant_can_create_ad_hoc_stage_with_translations(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/stages", [
                'name'  => ['es' => 'Etapa Custom', 'en' => 'Custom Stage'],
                'color' => 'warning',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_ad_hoc', true)
            ->assertJsonPath('data.color', 'warning')
            ->assertJsonPath('data.name.es', 'Etapa Custom')
            ->assertJsonPath('data.name.en', 'Custom Stage');

        $this->assertDatabaseHas('case_stages', [
            'case_id'           => $this->case->id,
            'workflow_stage_id' => null,
            'sort_order'        => 2,
        ]);
    }

    public function test_create_stage_requires_name_es(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/stages", [
                'name'  => ['en' => 'Only English'],
                'color' => 'primary',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name.es']);
    }

    public function test_create_stage_validates_color_in_palette(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/stages", [
                'name'  => ['es' => 'Test'],
                'color' => 'invalid-color',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['color']);
    }

    public function test_consultant_can_reorder_stages_horizontally(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/stages/reorder", [
                'stage_ids' => [$this->caseStage2->id, $this->caseStage1->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('case_stages', ['id' => $this->caseStage2->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('case_stages', ['id' => $this->caseStage1->id, 'sort_order' => 1]);
    }

    public function test_reorder_rejects_partial_stage_ids(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/stages/reorder", [
                'stage_ids' => [$this->caseStage1->id],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['stage_ids']);
    }

    public function test_reorder_rejects_duplicate_stage_ids(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/stages/reorder", [
                'stage_ids' => [$this->caseStage1->id, $this->caseStage1->id],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['stage_ids']);
    }

    public function test_consultant_can_delete_stage_with_move_policy(): void
    {
        // 2 tasks in caseStage1 (non-terminal) — those will be moved to caseStage2.
        $task1 = Task::factory()->create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => $this->stage1->id, 'case_stage_id' => $this->caseStage1->id,
            'requester_id' => $this->admin->id, 'sort_order' => 0,
        ]);
        $task2 = Task::factory()->create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => $this->stage1->id, 'case_stage_id' => $this->caseStage1->id,
            'requester_id' => $this->admin->id, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/stages/{$this->caseStage1->id}", [
                'policy'           => 'move',
                'move_to_stage_id' => $this->caseStage2->id,
            ]);

        $response->assertNoContent();

        $this->assertSoftDeleted('case_stages', ['id' => $this->caseStage1->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'case_stage_id' => $this->caseStage2->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'case_stage_id' => $this->caseStage2->id]);
    }

    public function test_consultant_can_delete_stage_with_trash_policy(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => $this->stage1->id, 'case_stage_id' => $this->caseStage1->id,
            'requester_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/stages/{$this->caseStage1->id}", [
                'policy' => 'trash',
            ]);

        $response->assertNoContent();
        $this->assertSoftDeleted('case_stages', ['id' => $this->caseStage1->id]);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_cannot_delete_only_terminal_stage(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/stages/{$this->caseStage2->id}", [
                'policy' => 'trash',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('case_stages', ['id' => $this->caseStage2->id, 'deleted_at' => null]);
    }

    public function test_delete_stage_reassigns_current_case_stage_id_when_was_current(): void
    {
        // Add a stage at sort_order=0.5 (before current) — actually swap: make caseStage1 current and add another stage before it.
        $beforeStage = CaseStage::create([
            'tenant_id'   => $this->tenant->id,
            'case_id'     => $this->case->id,
            'code'        => 'before',
            'sort_order'  => -1,
            'is_active'   => true,
            'is_terminal' => false,
            'color'       => 'info',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/stages/{$this->caseStage1->id}", [
                'policy' => 'trash',
            ]);

        $response->assertNoContent();

        $this->case->refresh();
        $this->assertEquals($beforeStage->id, $this->case->current_case_stage_id);
    }

    public function test_cannot_modify_stages_of_case_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCase = ImmigrationCase::factory()->create([
            'tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/cases/{$otherCase->id}/stages");

        $response->assertNotFound();
    }

    public function test_advance_stage_uses_case_stages_not_workflow_stages(): void
    {
        // Insert an ad-hoc stage in the middle to verify advanceStage walks case_stages.
        $adHocStage = CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => null, 'code' => 'adhoc_mid',
            'sort_order' => 0, 'is_active' => true, 'is_terminal' => false, 'color' => 'info',
        ]);
        // Move caseStage1 to position 1 to make adHoc first wave precede it.
        // Actually simpler: keep current arrangement: caseStage1 at 0, adHoc at... let's place adHoc at 0.5 by
        // setting caseStage1 -> 1, caseStage2 -> 2 and adHoc at 0.
        $this->caseStage1->update(['sort_order' => 1]);
        $this->caseStage2->update(['sort_order' => 2]);
        // current is still caseStage1 (sort_order 1)
        // advancing should land on caseStage2.

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertOk();

        $this->case->refresh();
        $this->assertEquals($this->caseStage2->id, $this->case->current_case_stage_id);
    }

    public function test_advance_stage_works_with_ad_hoc_stage_in_middle(): void
    {
        $adHocStage = CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => null, 'code' => 'adhoc_mid',
            'sort_order' => 0, 'is_active' => true, 'is_terminal' => false, 'color' => 'info',
        ]);
        // Reorder: adHoc=0, caseStage1=1, caseStage2=2
        $this->caseStage1->update(['sort_order' => 1]);
        $this->caseStage2->update(['sort_order' => 2]);
        // Set current to adHoc
        $this->case->update(['current_case_stage_id' => $adHocStage->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/advance-stage");

        $response->assertOk();
        $this->case->refresh();
        $this->assertEquals($this->caseStage1->id, $this->case->current_case_stage_id);
    }

    public function test_progress_recalculates_after_stage_deletion_with_trash_policy(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage1)->create();

        // 2 template tasks: 1 resolved, 1 new -> 50%
        Task::factory()->create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => $this->stage1->id, 'case_stage_id' => $this->caseStage1->id,
            'task_template_id' => $template->id, 'requester_id' => $this->admin->id,
            'status' => 'resolved',
        ]);
        Task::factory()->create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'workflow_stage_id' => $this->stage1->id, 'case_stage_id' => $this->caseStage1->id,
            'task_template_id' => $template->id, 'requester_id' => $this->admin->id,
            'status' => 'new',
        ]);

        // Delete caseStage1 with trash policy -> all template tasks soft-deleted
        // -> progress recalculates over remaining tasks (0).
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cases/{$this->case->id}/stages/{$this->caseStage1->id}", [
                'policy' => 'trash',
            ])->assertNoContent();

        $this->case->refresh();
        $this->assertEquals(0, $this->case->progress);
    }

    public function test_workflow_snapshot_is_immutable_after_stage_changes(): void
    {
        // Set an initial snapshot and verify it doesn't change after stage operations.
        $originalSnapshot = ['locale' => 'es', 'captured_at' => '2026-01-01T00:00:00+00:00', 'stages' => [
            ['id' => $this->stage1->id, 'code' => 'stage_one', 'sort_order' => 0, 'is_terminal' => false, 'color' => 'primary', 'name' => 'Etapa 1', 'description' => null],
        ]];
        $this->case->update(['workflow_snapshot' => $originalSnapshot]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/stages", [
                'name'  => ['es' => 'Nueva ad-hoc'],
                'color' => 'info',
            ])->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/cases/{$this->case->id}/stages/reorder", [
                'stage_ids' => [
                    $this->caseStage2->id,
                    $this->caseStage1->id,
                    CaseStage::where('case_id', $this->case->id)->latest('id')->first()->id,
                ],
            ])->assertOk();

        $this->case->refresh();
        $this->assertEquals($originalSnapshot, $this->case->workflow_snapshot);
    }
}
