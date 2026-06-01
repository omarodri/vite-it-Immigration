<?php

namespace App\Services\Case;

use App\Models\CaseType;
use App\Models\ImmigrationCase;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseTypeService
{
    /**
     * Clonación en cascada: CaseType → WorkflowStage[] → TaskTemplate[] → Translations.
     * Síncrona dentro de DB::transaction. ~250–500 ms para 4 etapas × 8 tareas × 3 idiomas.
     */
    public function clone(CaseType $source): CaseType
    {
        return DB::transaction(function () use ($source) {
            $tenantId = Auth::user()->tenant_id;

            // 1) Clonar el CaseType raíz — siempre al tenant actual (H1).
            $newType = $source->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
            $newType->tenant_id = $tenantId;
            $newType->code      = $this->generateUniqueCode($source->code, $tenantId);
            $newType->name      = $source->name . ' (copia)';
            $newType->is_active = false;   // fuerza revisión antes de activar (D4)
            $newType->save();

            // 2) Cargar árbol completo de origen ordenado por sort_order.
            $stages = WorkflowStage::with([
                'translations',
                'taskTemplates' => fn ($q) => $q->orderBy('sort_order'),
                'taskTemplates.translations',
            ])
                ->where('case_type_id', $source->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($stages as $stage) {
                // 3) Clonar WorkflowStage — nuevo case_type_id evita colisión en
                // unique (tenant_id, case_type_id, code).
                $newStage = $stage->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                $newStage->tenant_id    = $tenantId;
                $newStage->case_type_id = $newType->id;
                $newStage->sort_order   = $stage->sort_order;   // D9: preservado literal
                $newStage->save();

                // 4) Copiar traducciones de la etapa vía trait HasTranslations (H6).
                foreach ($stage->translationsByField() as $field => $byLocale) {
                    $newStage->setTranslations($field, $byLocale);
                }

                // 5) Clonar TaskTemplates hijos → FK al NUEVO stage.
                foreach ($stage->taskTemplates as $tmpl) {
                    $newTmpl = $tmpl->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                    $newTmpl->tenant_id         = $tenantId;
                    $newTmpl->workflow_stage_id = $newStage->id;   // FK reconstruida
                    $newTmpl->sort_order        = $tmpl->sort_order;
                    $newTmpl->save();

                    foreach ($tmpl->translationsByField() as $field => $byLocale) {
                        $newTmpl->setTranslations($field, $byLocale);
                    }
                }
            }

            return $newType->load('workflowStages.taskTemplates');
        });
    }

    /**
     * Cuenta expedientes activos para el aviso informativo en UI.
     * NO es una validación de borrado — el soft-delete es siempre libre (D7).
     */
    public function getActiveCasesCount(CaseType $caseType): int
    {
        return ImmigrationCase::where('case_type_id', $caseType->id)
            ->whereNotIn('status', ['closed', 'archived'])
            ->count();
    }

    /**
     * Genera un code único para el clon dentro del tenant.
     * code es string(10) — truncar para dejar margen al sufijo numérico.
     * Protegido por la unique de BD como red de seguridad real.
     */
    private function generateUniqueCode(string $base, int $tenantId): string
    {
        $stem = substr($base, 0, 7);    // deja 3 chars para sufijo "2".."99"
        for ($i = 2; $i <= 99; $i++) {
            $candidate = substr($stem . $i, 0, 10);
            $exists = CaseType::withoutGlobalScope('tenant_or_global')
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        return substr(strtoupper(Str::random(8)), 0, 10);   // fallback improbable
    }

    /**
     * Listado de tipos con conteo de etapas y de tareas (task_templates no eliminadas).
     */
    public function getWithWorkflowCount(): Collection
    {
        return CaseType::withCount(['workflowStages'])
            ->withCount(['workflowStages as tasks_count' => fn ($q) => $q
                ->join('task_templates', 'workflow_stages.id', '=', 'task_templates.workflow_stage_id')
                ->whereNull('task_templates.deleted_at'),
            ])
            ->orderBy('name')
            ->get();
    }
}
