<?php

namespace Tests\Unit\Workflow;

use App\Models\CaseStage;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Workflow\CaseStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseStageServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaseStageService $service;
    private ImmigrationCase $case;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin  = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $caseType = CaseType::factory()->create(['tenant_id' => null]);
        $client   = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->case = ImmigrationCase::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $client->id,
            'case_type_id' => $caseType->id,
            'assigned_to'  => $this->admin->id,
        ]);

        $this->service = app(CaseStageService::class);
    }

    public function test_reorder_assigns_consecutive_sort_order(): void
    {
        $stages = collect([0, 1, 2])->map(fn ($i) => CaseStage::create([
            'tenant_id'   => $this->tenant->id,
            'case_id'     => $this->case->id,
            'code'        => "stage_$i",
            'sort_order'  => $i,
            'is_active'   => true,
            'is_terminal' => false,
            'color'       => 'primary',
        ]));

        $reversed = $stages->pluck('id')->reverse()->values()->all();
        $this->service->reorderStages($this->case, $reversed);

        $this->assertEquals(0, CaseStage::find($reversed[0])->sort_order);
        $this->assertEquals(1, CaseStage::find($reversed[1])->sort_order);
        $this->assertEquals(2, CaseStage::find($reversed[2])->sort_order);
    }

    public function test_delete_with_move_appends_tasks_at_end(): void
    {
        $source = CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'code' => 'src', 'sort_order' => 0, 'is_active' => true, 'is_terminal' => false, 'color' => 'primary',
        ]);
        $dest = CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'code' => 'dst', 'sort_order' => 1, 'is_active' => true, 'is_terminal' => true, 'color' => 'success',
        ]);

        // Pre-existing task in destination
        Task::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'case_id'       => $this->case->id,
            'case_stage_id' => $dest->id,
            'requester_id'  => $this->admin->id,
            'sort_order'    => 5,
            'subject'       => 'Existing in dest',
        ]);

        // Tasks to move from source
        $movedA = Task::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'case_id'       => $this->case->id,
            'case_stage_id' => $source->id,
            'requester_id'  => $this->admin->id,
            'sort_order'    => 0,
            'subject'       => 'A',
        ]);
        $movedB = Task::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'case_id'       => $this->case->id,
            'case_stage_id' => $source->id,
            'requester_id'  => $this->admin->id,
            'sort_order'    => 1,
            'subject'       => 'B',
        ]);

        $this->service->deleteStage($this->case, $source, 'move', $dest->id);

        $movedA->refresh();
        $movedB->refresh();

        $this->assertEquals($dest->id, $movedA->case_stage_id);
        $this->assertEquals($dest->id, $movedB->case_stage_id);
        // sort_order must continue from existing max (5) + 1
        $this->assertEquals(6, $movedA->sort_order);
        $this->assertEquals(7, $movedB->sort_order);
        $this->assertSoftDeleted('case_stages', ['id' => $source->id]);
    }

    public function test_create_increments_max_sort_order(): void
    {
        CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'code' => 'first', 'sort_order' => 0, 'is_active' => true, 'is_terminal' => false, 'color' => 'primary',
        ]);
        CaseStage::create([
            'tenant_id' => $this->tenant->id, 'case_id' => $this->case->id,
            'code' => 'second', 'sort_order' => 7, 'is_active' => true, 'is_terminal' => false, 'color' => 'primary',
        ]);

        $stage = $this->service->createAdHocStage($this->case, [
            'name'  => ['es' => 'Nueva'],
            'color' => 'info',
        ]);

        $this->assertEquals(8, $stage->sort_order);
        $this->assertNull($stage->workflow_stage_id);
        $this->assertEquals('Nueva', $stage->trans('name', 'es'));
    }
}
