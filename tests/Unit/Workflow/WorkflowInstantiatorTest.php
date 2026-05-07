<?php

namespace Tests\Unit\Workflow;

use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\Workflow\TranslationResolver;
use App\Services\Workflow\WorkflowInstantiator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowInstantiatorTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowInstantiator $instantiator;
    private Tenant $tenant;
    private CaseType $caseType;
    private User $admin;
    private ImmigrationCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant   = Tenant::factory()->create();
        $this->caseType = CaseType::factory()->create(['tenant_id' => null]);
        $this->admin    = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->case = ImmigrationCase::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'client_id'    => $client->id,
            'case_type_id' => $this->caseType->id,
            'assigned_to'  => $this->admin->id,
            'language'     => 'es',
        ]);

        $this->instantiator = app(WorkflowInstantiator::class);
    }

    // ── buildSnapshot (via instantiate) ──────────────────────────────────────

    public function test_build_snapshot_includes_all_stages(): void
    {
        [$s1, $s2, $s3] = WorkflowStage::factory()->count(3)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 2, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'is_terminal' => true],
        )->create();

        $this->actingAs($this->admin);
        $this->instantiator->instantiate($this->case);

        $this->case->refresh();
        $snapshot = $this->case->workflow_snapshot;

        $this->assertNotNull($snapshot);
        $this->assertCount(3, $snapshot['stages']);
        $stageCodes = array_column($snapshot['stages'], 'code');
        $this->assertContains($s1->code, $stageCodes);
        $this->assertContains($s2->code, $stageCodes);
        $this->assertContains($s3->code, $stageCodes);
    }

    public function test_build_snapshot_records_terminal_flag(): void
    {
        WorkflowStage::factory()->count(2)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'is_terminal' => false],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'is_terminal' => true],
        )->create();

        $this->actingAs($this->admin);
        $this->instantiator->instantiate($this->case);

        $this->case->refresh();
        $stages = $this->case->workflow_snapshot['stages'];

        $terminalStages = array_filter($stages, fn ($s) => $s['is_terminal']);
        $this->assertCount(1, $terminalStages);
    }

    // ── recalculateProgress ───────────────────────────────────────────────────

    public function test_recalculate_progress_is_zero_when_no_tasks_completed(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'sort_order'   => 0,
        ]);

        $template = TaskTemplate::factory()->forStage($stage)->create();

        Task::factory()->count(3)->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'new',
        ]);

        $this->instantiator->recalculateProgress($this->case);

        $this->case->refresh();
        $this->assertEquals(0, $this->case->progress);
    }

    public function test_recalculate_progress_is_100_when_all_tasks_completed(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'sort_order'   => 0,
        ]);

        $template = TaskTemplate::factory()->forStage($stage)->create();

        Task::factory()->count(4)->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
        ]);

        $this->instantiator->recalculateProgress($this->case);

        $this->case->refresh();
        $this->assertEquals(100, $this->case->progress);
    }

    public function test_recalculate_progress_calculates_partial_percentage(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'sort_order'   => 0,
        ]);

        $template = TaskTemplate::factory()->forStage($stage)->create();

        // 2 resolved out of 4 = 50%
        Task::factory()->count(2)->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
        ]);

        Task::factory()->count(2)->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'new',
        ]);

        $this->instantiator->recalculateProgress($this->case);

        $this->case->refresh();
        $this->assertEquals(50, $this->case->progress);
    }

    public function test_recalculate_progress_counts_closed_status_as_done(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'sort_order'   => 0,
        ]);

        $template = TaskTemplate::factory()->forStage($stage)->create();

        // 1 closed + 1 resolved = 2 done out of 3 = 67%
        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'closed',
        ]);

        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'resolved',
        ]);

        Task::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'case_id'           => $this->case->id,
            'workflow_stage_id' => $stage->id,
            'task_template_id'  => $template->id,
            'requester_id'      => $this->admin->id,
            'status'            => 'new',
        ]);

        $this->instantiator->recalculateProgress($this->case);

        $this->case->refresh();
        $this->assertEquals(67, $this->case->progress);
    }

    public function test_recalculate_progress_is_zero_when_case_has_no_tasks(): void
    {
        $this->instantiator->recalculateProgress($this->case);

        $this->case->refresh();
        $this->assertEquals(0, $this->case->progress);
    }
}
