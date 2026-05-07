<?php

namespace Database\Seeders;

use App\Models\CaseType;
use App\Models\TaskTemplate;
use App\Models\Tenant;
use App\Models\Translation;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds default workflow stages and task templates for every tenant.
 *
 * IMPORTANT: This seeder NEVER creates or modifies CaseType records.
 * If a case_type code is not found in the database, it is silently skipped.
 *
 * Run via: php artisan db:seed --class=DefaultWorkflowsSeeder
 * Or via:  php artisan workflow:seed-defaults [tenant_id]
 */
class DefaultWorkflowsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command?->warn('No tenants found. Skipping workflow seed.');
            return;
        }

        $definitions = WorkflowSeedData::all();

        foreach ($tenants as $tenant) {
            $this->seedTenant($tenant, $definitions);
        }
    }

    public function seedForTenant(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->command?->error("Tenant #{$tenantId} not found.");
            return;
        }

        $this->seedTenant($tenant, WorkflowSeedData::all());
    }

    // ─────────────────────────────────────────────────────────────────────────────

    private function seedTenant(Tenant $tenant, array $definitions): void
    {
        $this->command?->line("  → Tenant [{$tenant->id}] {$tenant->name}");

        foreach ($definitions as $definition) {
            foreach ($definition['case_type_codes'] as $code) {
                // NEVER use firstOrCreate here — case_types are read-only from this seeder.
                $caseType = CaseType::where('code', $code)->first();

                if (! $caseType) {
                    $this->command?->warn("    [SKIP] case_type code '{$code}' not found in database.");
                    continue;
                }

                $this->seedWorkflow($tenant, $caseType, $definition['stages']);
            }
        }
    }

    private function seedWorkflow(Tenant $tenant, CaseType $caseType, array $stageDefs): void
    {
        foreach ($stageDefs as $stageDef) {
            $this->seedStage($tenant, $caseType, $stageDef);
        }
    }

    private function seedStage(Tenant $tenant, CaseType $caseType, array $stageDef): void
    {
        // Skip if this stage already exists for this tenant+case_type combination.
        $exists = WorkflowStage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('case_type_id', $caseType->id)
            ->where('code', $stageDef['code'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($tenant, $caseType, $stageDef) {
            /** @var WorkflowStage $stage */
            $stage = WorkflowStage::withoutGlobalScopes()->create([
                'tenant_id'   => $tenant->id,
                'case_type_id' => $caseType->id,
                'code'        => $stageDef['code'],
                'sort_order'  => $stageDef['sort_order'],
                'is_active'   => true,
                'is_terminal' => $stageDef['is_terminal'],
                'color'       => $stageDef['color'],
            ]);

            $this->saveTranslations($stage, $stageDef['name'], 'name', $tenant->id);
            $this->saveTranslations($stage, $stageDef['description'], 'description', $tenant->id);

            foreach ($stageDef['templates'] as $i => $tmplDef) {
                $this->seedTemplate($stage, $tmplDef, $i, $tenant->id);
            }
        });
    }

    private function seedTemplate(WorkflowStage $stage, array $tmplDef, int $sortOrder, int $tenantId): void
    {
        /** @var TaskTemplate $template */
        $template = TaskTemplate::withoutGlobalScopes()->create([
            'tenant_id'               => $tenantId,
            'workflow_stage_id'       => $stage->id,
            'code'                    => $tmplDef['code'],
            'sort_order'              => $sortOrder,
            'is_required'             => $tmplDef['is_required'],
            'blocks_stage_completion' => $tmplDef['blocks_stage_completion'],
            'default_type'            => $tmplDef['default_type'],
            'default_priority'        => $tmplDef['default_priority'],
            'due_offset_days'         => $tmplDef['due_offset_days'],
            'is_active'               => true,
        ]);

        $this->saveTranslations($template, $tmplDef['name'], 'name', $tenantId);
        $this->saveTranslations($template, $tmplDef['description'], 'description', $tenantId);
    }

    /**
     * @param array<string, string> $byLocale e.g. ['es' => '...', 'en' => '...', 'fr' => '...']
     */
    private function saveTranslations(object $model, array $byLocale, string $field, int $tenantId): void
    {
        foreach ($byLocale as $locale => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            Translation::updateOrCreate(
                [
                    'translatable_type' => get_class($model),
                    'translatable_id'   => $model->id,
                    'locale'            => $locale,
                    'field'             => $field,
                ],
                [
                    'value'     => $value,
                    'tenant_id' => $tenantId,
                ]
            );
        }
    }
}
