<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $cases = DB::table('cases')->whereNotNull('case_type_id')->get();

            foreach ($cases as $case) {
                $exists = DB::table('case_stages')->where('case_id', $case->id)->exists();
                if ($exists) {
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

                    // Copy polymorphic translations
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

                // Re-point tasks to case_stage_id
                foreach ($idMap as $oldStageId => $newCaseStageId) {
                    DB::table('tasks')
                        ->where('case_id', $case->id)
                        ->where('workflow_stage_id', $oldStageId)
                        ->whereNull('case_stage_id')
                        ->update(['case_stage_id' => $newCaseStageId]);
                }

                // Re-point current_case_stage_id
                if ($case->current_stage_id && isset($idMap[$case->current_stage_id])) {
                    DB::table('cases')
                        ->where('id', $case->id)
                        ->update(['current_case_stage_id' => $idMap[$case->current_stage_id]]);
                }
            }
        });
    }

    public function down(): void
    {
        // No-op: reverting backfill would destroy user data.
    }
};
