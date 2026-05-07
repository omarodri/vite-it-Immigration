<?php

namespace Tests\Feature\Workflow;

use App\Models\CaseStage;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillCaseStagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Replicates the backfill migration logic for legacy cases that
     * were created before the case_stages table existed.
     *
     * Mirrors database/migrations/2026_04_28_000004_backfill_case_stages.php.
     */
    private function runBackfill(): void
    {
        DB::transaction(function () {
            $cases = DB::table('cases')->whereNotNull('case_type_id')->get();

            foreach ($cases as $case) {
                if (DB::table('case_stages')->where('case_id', $case->id)->exists()) {
                    continue;
                }

                $stages = DB::table('workflow_stages')
                    ->where('case_type_id', $case->case_type_id)
                    ->where('tenant_id', $case->tenant_id)
                    ->whereNull('deleted_at')
                    ->orderBy('sort_order')
                    ->get();

                $idMap = [];
                foreach ($stages as $stage) {
                    $newId = DB::table('case_stages')->insertGetId([
                        'tenant_id'         => $stage->tenant_id,
                        'case_id'           => $case->id,
                        'workflow_stage_id' => $stage->id,
                        'code'              => $stage->code,
                        'sort_order'        => $stage->sort_order,
                        'is_active'         => $stage->is_active,
                        'is_terminal'       => $stage->is_terminal,
                        'color'             => $stage->color,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                    $idMap[$stage->id] = $newId;

                    $translations = DB::table('translations')
                        ->where('translatable_type', \App\Models\WorkflowStage::class)
                        ->where('translatable_id', $stage->id)
                        ->get();
                    foreach ($translations as $t) {
                        DB::table('translations')->insert([
                            'tenant_id'         => $t->tenant_id,
                            'translatable_type' => \App\Models\CaseStage::class,
                            'translatable_id'   => $newId,
                            'locale'            => $t->locale,
                            'field'             => $t->field,
                            'value'             => $t->value,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                }

                foreach ($idMap as $oldStageId => $newCaseStageId) {
                    DB::table('tasks')
                        ->where('case_id', $case->id)
                        ->where('workflow_stage_id', $oldStageId)
                        ->whereNull('case_stage_id')
                        ->update(['case_stage_id' => $newCaseStageId]);
                }

                if ($case->current_stage_id && isset($idMap[$case->current_stage_id])) {
                    DB::table('cases')
                        ->where('id', $case->id)
                        ->update(['current_case_stage_id' => $idMap[$case->current_stage_id]]);
                }
            }
        });
    }

    /**
     * Creates a "legacy" case that mimics a pre-spec-48 case:
     * has workflow_stages + tasks pointing to workflow_stage_id, but NO case_stages.
     */
    private function createLegacyCase(Tenant $tenant): array
    {
        $caseType = CaseType::factory()->create(['tenant_id' => null]);

        [$s1, $s2] = WorkflowStage::factory()->count(2)->sequence(
            ['sort_order' => 0, 'tenant_id' => $tenant->id, 'case_type_id' => $caseType->id],
            ['sort_order' => 1, 'tenant_id' => $tenant->id, 'case_type_id' => $caseType->id, 'is_terminal' => true],
        )->create();

        // Add ES translations to the workflow stages
        $s1->setTranslations('name', ['es' => 'Etapa Uno', 'en' => 'Stage One']);
        $s2->setTranslations('name', ['es' => 'Etapa Final']);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $admin  = User::factory()->create(['tenant_id' => $tenant->id]);

        // Insert case directly (bypass WorkflowInstantiator) to simulate legacy state
        $caseId = DB::table('cases')->insertGetId([
            'tenant_id'        => $tenant->id,
            'client_id'        => $client->id,
            'case_type_id'     => $caseType->id,
            'case_number'      => 'LEGACY-' . uniqid(),
            'status'           => 'active',
            'priority'         => 'medium',
            'progress'         => 0,
            'language'         => 'es',
            'current_stage_id' => $s1->id,
            'assigned_to'      => $admin->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Tasks pointing to workflow_stage_id only (no case_stage_id)
        DB::table('tasks')->insert([
            'tenant_id'         => $tenant->id,
            'case_id'           => $caseId,
            'workflow_stage_id' => $s1->id,
            'case_stage_id'     => null,
            'requester_id'      => $admin->id,
            'subject'           => 'Legacy task 1',
            'type'              => 'document',
            'priority'          => 'medium',
            'status'            => 'new',
            'sort_order'        => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
        DB::table('tasks')->insert([
            'tenant_id'         => $tenant->id,
            'case_id'           => $caseId,
            'workflow_stage_id' => $s2->id,
            'case_stage_id'     => null,
            'requester_id'      => $admin->id,
            'subject'           => 'Legacy task 2',
            'type'              => 'filing',
            'priority'          => 'medium',
            'status'            => 'new',
            'sort_order'        => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return ['caseId' => $caseId, 'stages' => [$s1, $s2]];
    }

    public function test_backfill_creates_case_stages_for_existing_cases(): void
    {
        $tenant = Tenant::factory()->create();
        ['caseId' => $caseId] = $this->createLegacyCase($tenant);

        $this->assertSame(0, CaseStage::where('case_id', $caseId)->count());

        $this->runBackfill();

        $this->assertSame(2, CaseStage::where('case_id', $caseId)->count());
        $this->assertDatabaseHas('case_stages', ['case_id' => $caseId, 'sort_order' => 0]);
        $this->assertDatabaseHas('case_stages', ['case_id' => $caseId, 'sort_order' => 1, 'is_terminal' => true]);
    }

    public function test_backfill_remaps_tasks_workflow_stage_id_to_case_stage_id(): void
    {
        $tenant = Tenant::factory()->create();
        ['caseId' => $caseId, 'stages' => [$s1, $s2]] = $this->createLegacyCase($tenant);

        $this->runBackfill();

        $cs1 = CaseStage::where('case_id', $caseId)->where('workflow_stage_id', $s1->id)->firstOrFail();
        $cs2 = CaseStage::where('case_id', $caseId)->where('workflow_stage_id', $s2->id)->firstOrFail();

        $this->assertSame(2, Task::where('case_id', $caseId)->whereNotNull('case_stage_id')->count());
        $this->assertDatabaseHas('tasks', ['case_id' => $caseId, 'case_stage_id' => $cs1->id]);
        $this->assertDatabaseHas('tasks', ['case_id' => $caseId, 'case_stage_id' => $cs2->id]);

        // current_case_stage_id is set
        $this->assertDatabaseHas('cases', ['id' => $caseId, 'current_case_stage_id' => $cs1->id]);
    }

    public function test_backfill_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        ['caseId' => $caseId] = $this->createLegacyCase($tenant);

        $this->runBackfill();
        $countAfterFirst = CaseStage::where('case_id', $caseId)->count();

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame($countAfterFirst, CaseStage::where('case_id', $caseId)->count());
    }

    public function test_backfill_copies_translations_polymorphically(): void
    {
        $tenant = Tenant::factory()->create();
        ['caseId' => $caseId, 'stages' => [$s1, $s2]] = $this->createLegacyCase($tenant);

        $this->runBackfill();

        $cs1 = CaseStage::where('case_id', $caseId)->where('workflow_stage_id', $s1->id)->firstOrFail();
        $cs2 = CaseStage::where('case_id', $caseId)->where('workflow_stage_id', $s2->id)->firstOrFail();

        $this->assertEquals('Etapa Uno', $cs1->trans('name', 'es'));
        $this->assertEquals('Stage One', $cs1->trans('name', 'en'));
        $this->assertEquals('Etapa Final', $cs2->trans('name', 'es'));
    }
}
