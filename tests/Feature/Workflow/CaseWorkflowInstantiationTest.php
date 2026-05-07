<?php

namespace Tests\Feature\Workflow;

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

class CaseWorkflowInstantiationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Client $client;
    private CaseType $caseType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->client   = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->caseType = CaseType::factory()->create(['tenant_id' => null]);
    }

    public function test_creating_case_with_workflow_instantiates_tasks(): void
    {
        // Define 2 stages, each with 2 templates
        [$stage1, $stage2] = WorkflowStage::factory()->count(2)->sequence(
            ['sort_order' => 0, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id],
            ['sort_order' => 1, 'tenant_id' => $this->tenant->id, 'case_type_id' => $this->caseType->id, 'is_terminal' => true],
        )->create();

        TaskTemplate::factory()->count(2)->forStage($stage1)->create();
        TaskTemplate::factory()->count(2)->forStage($stage2)->create();

        // Add Spanish translations so subject is populated
        foreach (TaskTemplate::all() as $t) {
            $t->setTranslations('name', ['es' => "Tarea {$t->id}"]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cases', [
                'client_id'    => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'language'     => 'es',
            ]);

        $response->assertCreated();

        $caseId = $response->json('data.id');
        $this->assertCount(4, Task::where('case_id', $caseId)->get());

        // current_stage_id should point to first stage
        $this->assertDatabaseHas('cases', [
            'id'               => $caseId,
            'current_stage_id' => $stage1->id,
        ]);

        // workflow_snapshot should be stored
        $case = ImmigrationCase::find($caseId);
        $this->assertNotNull($case->workflow_snapshot);
        $this->assertCount(2, $case->workflow_snapshot['stages']);
    }

    public function test_creating_case_without_workflow_succeeds_with_zero_tasks(): void
    {
        // caseType has no stages
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cases', [
                'client_id'    => $this->client->id,
                'case_type_id' => $this->caseType->id,
                'language'     => 'es',
            ]);

        $response->assertCreated();

        $caseId = $response->json('data.id');
        $this->assertCount(0, Task::where('case_id', $caseId)->get());

        $case = ImmigrationCase::find($caseId);
        $this->assertNull($case->current_stage_id);
        $this->assertNull($case->workflow_snapshot);
    }

    public function test_excluded_template_ids_are_not_instantiated(): void
    {
        $stage = WorkflowStage::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'case_type_id' => $this->caseType->id,
            'sort_order'   => 0,
        ]);

        [$required, $optional] = TaskTemplate::factory()->count(2)->sequence(
            ['is_required' => true],
            ['is_required' => false],
        )->forStage($stage)->create();

        foreach (TaskTemplate::all() as $t) {
            $t->setTranslations('name', ['es' => "Tarea {$t->id}"]);
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cases', [
                'client_id'             => $this->client->id,
                'case_type_id'          => $this->caseType->id,
                'language'              => 'es',
                'excluded_template_ids' => [$optional->id],
            ]);

        $response->assertCreated();

        $caseId = $response->json('data.id');
        $this->assertCount(1, Task::where('case_id', $caseId)->get());
        $this->assertDatabaseHas('tasks', ['case_id' => $caseId, 'task_template_id' => $required->id]);
        $this->assertDatabaseMissing('tasks', ['case_id' => $caseId, 'task_template_id' => $optional->id]);
    }
}
