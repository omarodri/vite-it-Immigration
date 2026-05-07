<?php

namespace Tests\Feature\Workflow;

use App\Models\CaseType;
use App\Models\TaskTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $admin;
    private WorkflowStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant      = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $caseType = CaseType::factory()->create(['tenant_id' => null]);

        $this->stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $caseType->id,
        ]);
    }

    // ── LIST ──────────────────────────────────────────────────────────────────

    public function test_admin_can_list_templates_for_stage(): void
    {
        TaskTemplate::factory()->count(4)->forStage($this->stage)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/workflow/stages/{$this->stage->id}/templates")
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_template(): void
    {
        $payload = [
            'code'                    => 'doc_recopilacion',
            'is_required'             => true,
            'blocks_stage_completion' => true,
            'default_type'            => 'document',
            'default_priority'        => 'medium',
            'translations'            => ['name' => ['es' => 'Recopilación de documentos', 'en' => 'Document collection']],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/workflow/stages/{$this->stage->id}/templates", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'doc_recopilacion');

        $this->assertDatabaseHas('task_templates', [
            'code'              => 'doc_recopilacion',
            'workflow_stage_id' => $this->stage->id,
        ]);
    }

    public function test_create_template_requires_spanish_name(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/workflow/stages/{$this->stage->id}/templates", [
                'code'         => 'missing_es',
                'translations' => ['name' => ['en' => 'Only English']],
            ])
            ->assertUnprocessable();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_template(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/workflow/templates/{$template->id}", [
                'default_priority' => 'urgent',
                'translations'     => ['name' => ['es' => 'Tarea urgente']],
            ])
            ->assertOk()
            ->assertJsonPath('data.default_priority', 'urgent');
    }

    public function test_admin_cannot_update_template_of_another_tenant(): void
    {
        $otherStage = WorkflowStage::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $foreignTemplate = TaskTemplate::factory()->forStage($otherStage)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/workflow/templates/{$foreignTemplate->id}", [
                'translations' => ['name' => ['es' => 'Hack']],
            ])
            ->assertNotFound();
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_template(): void
    {
        $template = TaskTemplate::factory()->forStage($this->stage)->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/workflow/templates/{$template->id}")
            ->assertOk();

        $this->assertSoftDeleted('task_templates', ['id' => $template->id]);
    }

    // ── REORDER ───────────────────────────────────────────────────────────────

    public function test_admin_can_reorder_templates(): void
    {
        $templates = TaskTemplate::factory()->count(3)->sequence(
            ['sort_order' => 0],
            ['sort_order' => 1],
            ['sort_order' => 2],
        )->forStage($this->stage)->create();

        $reversed = $templates->pluck('id')->reverse()->values()->all();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/workflow/stages/{$this->stage->id}/templates/reorder", [
                'ordered_ids' => $reversed,
            ])
            ->assertOk();

        $this->assertDatabaseHas('task_templates', ['id' => $reversed[0], 'sort_order' => 0]);
    }
}
