<?php

namespace Tests\Feature\Workflow;

use App\Models\CaseType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowStageCrudTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $admin;
    private User $otherAdmin;
    private CaseType $caseType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant      = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->otherAdmin = User::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->otherAdmin->assignRole('admin');

        $this->caseType = CaseType::factory()->create(['tenant_id' => null]);
    }

    // ── LIST ──────────────────────────────────────────────────────────────────

    public function test_admin_can_list_stages_for_case_type(): void
    {
        WorkflowStage::factory()->count(3)->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_cannot_list_stages(): void
    {
        $regular = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $regular->assignRole('cliente');

        $this->actingAs($regular, 'sanctum')
            ->getJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages")
            ->assertForbidden();
    }

    // ── CREATE ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_stage(): void
    {
        $payload = [
            'code'        => 'admision',
            'is_active'   => true,
            'is_terminal' => false,
            'color'       => 'primary',
            'translations'=> ['name' => ['es' => 'Admisión', 'en' => 'Admission']],
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'admision');

        $this->assertDatabaseHas('workflow_stages', [
            'code'         => 'admision',
            'case_type_id' => $this->caseType->id,
            'tenant_id'    => $this->tenant->id,
        ]);
    }

    public function test_create_stage_requires_spanish_name(): void
    {
        $payload = [
            'code'         => 'sin_nombre',
            'translations' => ['name' => ['en' => 'No Spanish']],
        ];

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages", $payload)
            ->assertUnprocessable();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_own_stage(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'color'        => 'primary',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/workflow/stages/{$stage->id}", [
                'color'        => 'success',
                'translations' => ['name' => ['es' => 'Actualizado']],
            ])
            ->assertOk()
            ->assertJsonPath('data.color', 'success');
    }

    public function test_admin_cannot_update_stage_of_another_tenant(): void
    {
        $foreignStage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->otherTenant->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/workflow/stages/{$foreignStage->id}", [
                'color'        => 'danger',
                'translations' => ['name' => ['es' => 'Hack']],
            ])
            ->assertNotFound();
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_own_stage(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/workflow/stages/{$stage->id}")
            ->assertOk();

        $this->assertSoftDeleted('workflow_stages', ['id' => $stage->id]);
    }

    // ── REORDER ───────────────────────────────────────────────────────────────

    public function test_admin_can_reorder_stages(): void
    {
        $stages = WorkflowStage::factory()->count(3)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 2, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
        )->create();

        $reversed = $stages->pluck('id')->reverse()->values()->all();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages/reorder", [
                'ordered_ids' => $reversed,
            ])
            ->assertOk();

        $this->assertDatabaseHas('workflow_stages', ['id' => $reversed[0], 'sort_order' => 0]);
        $this->assertDatabaseHas('workflow_stages', ['id' => $reversed[2], 'sort_order' => 2]);
    }

    // ── TENANT ISOLATION ─────────────────────────────────────────────────────

    public function test_admin_only_sees_own_tenant_stages(): void
    {
        WorkflowStage::factory()->count(2)->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
        ]);
        WorkflowStage::factory()->count(3)->create([
            'tenant_id'    => $this->otherTenant->id,
            'case_type_id' => $this->caseType->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/workflow/case-types/{$this->caseType->id}/stages")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
