---
tipo: spec
numero: 48
modulo: Workflows y Tareas
dominio: WFLOW
estado: PART
tags: [spec, workflows, parcial]
relacionados:
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[47_tablero_interactivo_tareas_expediente|Spec 47]]"
---
# Especificacion Tecnica: Etapas Personalizadas por Expediente

## Metadata
- **Fecha:** 2026-04-28
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado (Fase 9 — despliegue a produccion — diferida)
- **Dependencias:** [[46_workflows_dinamicos_motor_etapas|Spec 46]] (Motor de Workflows Dinamicos), [[47_tablero_interactivo_tareas_expediente|Spec 47]] (Tablero Interactivo de Tareas), [[40_trash_papelera_reciclaje|Spec 40]] (Trash Papelera Reciclaje)

---

## 1. Resumen Ejecutivo

### Problema
Tras los Specs 46 y 47, la estructura de etapas (`workflow_stages`) sigue siendo **propiedad del `case_type` a nivel tenant**: todos los expedientes del mismo tipo comparten exactamente las mismas columnas. El consultor puede mover, crear y borrar **tareas** en su tablero, pero no puede modificar la **estructura de columnas (stages)** del expediente individual cuando el caso real exige una etapa adicional, un orden distinto, o suprimir una etapa que no aplica.

Esto fuerza a los consultores a:
- Acumular tareas ad-hoc dentro de stages "incorrectas" (semanticamente confusas).
- Pedir al admin que modifique el workflow del `case_type`, lo que rompe a TODOS los demas expedientes del mismo tipo.
- Llevar parte del flujo "fuera del sistema" (notas, hojas externas).

### Alcance
- **Stages ad-hoc por expediente:** crear stages que solo existen en ese case (no propagan a otros expedientes del mismo `case_type`).
- **Reordenamiento horizontal de columnas:** drag & drop horizontal en `TaskBoard` para cambiar `sort_order` de las stages del case.
- **Eliminacion de stage del case:** soft delete con politica explicita para tasks asociadas (mover/papelera).
- **Cascada automatica al reordenar:** las tasks NO se modifican; mantienen `workflow_stage_id` y `sort_order` interno; la cascada visual se obtiene del JOIN con `case_stages.sort_order`.
- **Traducciones polimorficas (ES/EN/FR/...):** los nombres y descripciones de stages ad-hoc usan la tabla `translations` ya existente (Spec 46) con el trait `HasTranslations`.
- **Compatibilidad retro:** todos los expedientes existentes en produccion siguen funcionando sin requerir intervencion manual; backfill idempotente.
- **Avance de etapa actualizado:** `WorkflowInstantiator::advanceStage()` se reescribe para usar las stages del case (`case_stages`) en vez de las del tenant.

### Fuera de alcance (v1)
- Editor visual de "plantilla" desde la vista del expediente (no se promueve la stage ad-hoc al `case_type`).
- Bulk operations (mover N tareas a la nueva stage).
- Historial visual de cambios de estructura (queda en `activity_log`).
- Permisos granulares por stage (toda modificacion requiere `cases.update`).
- Sincronizacion entre clientes (sin broadcasting).

---

## 2. Arquitectura

### 2.1 Decision arquitectonica: como modelar stages-del-case

Se evaluaron cuatro opciones. Cada expediente debe tener una estructura de columnas independiente sin filtrarse a otros expedientes del mismo tenant.

#### Opcion A — Tabla nueva `case_stages` (instanciacion completa)
- Al crear el case, `WorkflowInstantiator` copia las filas de `workflow_stages` (template) en `case_stages` (instancia). Las tasks apuntan a `case_stages.id` en lugar de `workflow_stages.id`.
- Pros: aislamiento total entre cases; cada case es libre; semantica clara (template vs instancia, igual que `task_templates` -> `tasks`); reordenar/eliminar/agregar son operaciones triviales sobre la tabla de instancia; queries simples (no OR / no NULL FK semantica).
- Contras: migracion masiva en produccion (todos los cases existentes deben recibir filas en `case_stages` y todas sus tasks deben re-apuntar). Cambio de FK en `tasks.workflow_stage_id` -> `tasks.case_stage_id`. Tests del Spec 46/47 requieren actualizacion ligera.

#### Opcion B — `workflow_stages` con `case_id` nullable
- Stages del tenant: `case_id = NULL`. Stages ad-hoc: `case_id = X`.
- Pros: sin tabla nueva; minima migracion en BD.
- Contras: queries actuales (Spec 46/47) que filtran por `case_type_id` siguen viendo solo las del tenant -> hay que reescribir TODAS para hacer OR / UNION. Riesgo alto de leak entre cases si un developer olvida el filtro `WHERE case_id = X OR case_id IS NULL`. Reordenar stages globales del tenant para un solo case requiere copiar la fila igualmente (porque modificar `sort_order` global afectaria a todos los cases). Termina siendo una variante peor de la Opcion A.

#### Opcion C — `workflow_snapshot` (JSON) como source of truth
- Mantener stages del tenant inmutables; las stages ad-hoc solo viven en el JSON del case. Tasks ad-hoc usan `workflow_stage_id = NULL` + un campo `snapshot_stage_key`.
- Pros: cero migraciones de schema.
- Contras: rompe la integridad relacional (stages ya no son entidades, son strings); imposible mantener traducciones polimorficas (`HasTranslations` requiere `translatable_id` real); cada operacion (mover, reordenar, eliminar) requiere reescribir el JSON entero con bloqueo pesimista; queries complejas para listar stages (mezcla de FK y JSON parsing); el snapshot pierde su rol original (era inmutable, historico). Anti-patron.

#### Opcion D — Hibrido: `workflow_stages` con flag `case_id`
- Tasks de plantilla siguen apuntando a `workflow_stages` global; stages ad-hoc se crean como filas con `case_id` setteado.
- Pros: parece minimal.
- Contras: hereda los contras de la Opcion B (queries OR, leak risk) Y ademas crea dos clases de stages que no se pueden reordenar uniformemente (la `sort_order` de la global afecta a otros cases). Imposible "mover" una stage de plantilla a otro orden solo para ESE case sin clonarla (= Opcion A nuevamente).

#### Recomendacion: **Opcion A — `case_stages`**

Razones decisivas:
1. **Aislamiento por diseño:** la integridad referencial (`case_stages.case_id` FK + cascade) elimina el riesgo de leak entre cases. No depende de que el developer recuerde filtrar.
2. **Coherencia con el modelo `task_templates -> tasks`:** Spec 46 ya hizo exactamente esta decision para tasks; replicarla para stages es paralelismo arquitectural.
3. **Queries simples:** `SELECT * FROM case_stages WHERE case_id = ?` reemplaza filtros compuestos por `case_type_id + tenant_id`.
4. **Reordenamiento natural:** modificar `sort_order` afecta solo a este case; las tasks no se mueven (siguen apuntando a su `case_stage_id`).
5. **Migracion controlada y unica:** una sola migracion poblada con datos existentes (idempotente) elimina la deuda futura. Mientras que las opciones B/D dejan la deuda viva en cada query nueva.

Costo: una migracion de produccion con backfill — manejable con el patron de migracion idempotente descrito en seccion 11.

---

### 2.2 Diagrama de Componentes

```
EDIT VIEW (Vue SPA)
    |
    v
[cases/edit.vue]
    |-- TaskBoard.vue                    (extender: editable horizontal)
    |   |-- vue-draggable-plus           (NUEVO grupo "case-stages", horizontal)
    |   |   |-- StageColumn.vue          (con stage-header editable + handle drag)
    |   |   |   |-- AddTaskButton
    |   |   |   |-- TaskCard[]           (sigue Spec 47)
    |   |   |-- AddStageButton           (al final del board)
    |   |-- StageEditorModal.vue         (NUEVO: nombre ES/EN/FR, color, terminal flag)
    |   |-- DeleteStagePolicyModal.vue   (NUEVO: confirma destino de tasks)
    |   |-- NewTaskModal.vue (Spec 47, sin cambios)
    |
    v (REST API)
[Laravel API Layer]
    |-- CaseStageController              (NUEVO)
    |   |-- index    GET    /cases/{case}/stages
    |   |-- store    POST   /cases/{case}/stages
    |   |-- update   PATCH  /cases/{case}/stages/{stage}
    |   |-- reorder  PATCH  /cases/{case}/stages/reorder
    |   |-- destroy  DELETE /cases/{case}/stages/{stage}
    |
    v
[Application Services]
    |-- CaseStageService (NUEVO)
    |   |-- listForCase(case)
    |   |-- createAdHocStage(case, payload)
    |   |-- updateStage(stage, payload)
    |   |-- reorderStages(case, orderedIds)
    |   |-- deleteStage(stage, policy: 'move'|'trash', moveToStageId?)
    |
    |-- WorkflowInstantiator (MODIFICAR Spec 46)
    |   |-- instantiate()       -> crea filas en case_stages, no apunta a workflow_stages
    |   |-- advanceStage()      -> busca siguiente CaseStage por case_id + sort_order
    |
    |-- CaseTaskService (MODIFICAR Spec 47)
    |   |-- moveTask validation -> case_stage pertenece al case (no a case_type)
    |
    v
[Domain Models]
    |-- CaseStage (NUEVO)
    |   |-- belongsTo(case, workflowStage as "originTemplate")
    |   |-- hasMany(tasks)
    |   |-- usa BelongsToTenant + HasTranslations + SoftDeletes
    |-- Task (MODIFICAR)
    |   |-- workflow_stage_id  -> renombrar a case_stage_id (FK a case_stages)
    |   |-- nuevo opcional: stage_template_id (referencia a workflow_stages original)
    |
    v
[Cross-cutting]
    |-- Pinia store useCaseStagesStore (NUEVO; optimistic horizontal reorder)
    |-- useCaseTasksStore             (extender: mover por case_stage_id)
    |-- activity_log en cada create/update/reorder/delete de stage
```

### 2.3 Flujo: Reordenar stages (drag horizontal)

```
1. Usuario arrastra StageColumn de posicion 2 a posicion 0 en TaskBoard
2. vue-draggable-plus group="case-stages" horizontal -> @end emite array final
3. useCaseStagesStore.reorder(caseId, [stageIdA, stageIdC, stageIdB, ...])
4. Store mutua orden local (UI inmediata)
5. PATCH /api/cases/{case}/stages/reorder { stage_ids: [...] }
6. Backend (CaseStageService::reorderStages):
   a. Authorize update sobre el case
   b. Validar que TODOS los stage_ids pertenecen al case (no leak)
   c. Validar que la cantidad es exacta (no faltan, no sobran)
   d. DB::transaction:
      - foreach con index => CaseStage::where('id', $id)->update(['sort_order' => $i])
   e. activity()->log('Reordeno etapas del expediente')
7. Response 200 + lista nueva -> store confirma; tasks NO requieren update porque
   siguen apuntando al mismo case_stage_id; el orden visual se logra via JOIN.
8. Error -> store revierte + toast.
```

### 2.4 Flujo: Crear stage ad-hoc

```
1. Usuario click "+ Nueva etapa" al final del board
2. StageEditorModal abre con form:
   - name_es (requerido), name_en, name_fr
   - description_es, description_en, description_fr (opcionales)
   - color (paleta tailwind del proyecto)
   - is_terminal (default: false; warning si activa)
3. Submit -> POST /api/cases/{case}/stages { name: {es,en,fr}, description?, color, is_terminal }
4. Backend (CaseStageService::createAdHocStage):
   a. Authorize update sobre el case
   b. CaseStage::create con:
      - tenant_id = case->tenant_id
      - case_id = case->id
      - workflow_stage_id = NULL (ad-hoc, no proviene de plantilla)
      - code = 'adhoc_' . Str::random(8)
      - sort_order = max(case_stages.sort_order WHERE case_id=...) + 1
      - is_active = true
   c. setTranslations('name', $payload['name']) en cada locale provisto
   d. setTranslations('description', $payload['description'] ?? [])
   e. activity()->log('Agrego etapa ad-hoc al expediente')
5. Response 201 con CaseStageResource -> store agrega columna al final.
```

### 2.5 Flujo: Eliminar stage del case

```
1. Usuario click kebab en stage header -> "Eliminar etapa"
2. Si la stage tiene tasks asociadas:
   - DeleteStagePolicyModal pregunta:
     [a] Mover tareas a otra etapa (dropdown con sister stages)
     [b] Enviar tareas a la papelera del expediente
   - Cancel cierra
3. Si stage vacia: SweetAlert2 confirmacion simple
4. DELETE /api/cases/{case}/stages/{stage}
   body: { policy: 'move'|'trash', move_to_stage_id?: int }
5. Backend (CaseStageService::deleteStage):
   a. Authorize update sobre el case
   b. lockForUpdate sobre stage y sus tasks
   c. Si is_terminal y stage es la unica terminal: 422 "no puedes dejar el caso sin etapa final"
   d. Si current_stage_id del case apunta a esta stage:
      - Reasignar current_stage_id a la stage anterior (sort_order menor) o NULL si era la primera
   e. policy = 'move':
      - Validar move_to_stage_id pertenece al case y no es el id que se borra
      - Reasignar tasks: case_stage_id = move_to_stage_id; recalcular sort_order al final del destino
   f. policy = 'trash':
      - Soft delete de cada task asociada
   g. $stage->delete() (soft delete)
   h. WorkflowInstantiator::recalculateProgress($case)
   i. activity()->log con detalle del policy aplicado
6. Response 200 -> store remueve columna; refresca tasksByStage.
```

### 2.6 Decision: workflow_snapshot

El campo `cases.workflow_snapshot` (Spec 46) se mantiene **inmutable como historico de instanciacion original**. Esto preserva:
- La capacidad de comparar "como estaba el workflow al crear el caso" vs "como esta hoy".
- La trazabilidad ante auditorias (importante para casos legales).

Las modificaciones de stages del case NO actualizan el snapshot. La fuente de verdad operacional pasa a ser la tabla `case_stages`. El snapshot es metadata de origen.

---

## 3. Cambios en Base de Datos

### 3.1 Migracion: Crear tabla `case_stages`

**Archivo:** `database/migrations/2026_04_28_000001_create_case_stages_table.php`

```php
public function up(): void
{
    Schema::create('case_stages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
        $table->foreignId('workflow_stage_id')->nullable()
              ->constrained('workflow_stages')->nullOnDelete();
        $table->string('code', 80);
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->boolean('is_terminal')->default(false);
        $table->string('color', 20)->default('primary');
        $table->timestamps();
        $table->softDeletes();

        $table->unique(['case_id', 'code']);
        $table->index(['tenant_id', 'case_id', 'sort_order'], 'case_stages_tenant_case_order_idx');
    });
}
```

Notas:
- `workflow_stage_id` nullable: rastrea origen template; NULL = stage ad-hoc.
- `code` unico por case (no por tenant): permite copiar codigo de plantilla sin conflictos cross-case.
- `nullOnDelete` en `workflow_stage_id`: si admin elimina la stage del template, las copias de los cases sobreviven.

### 3.2 Migracion: Anadir `case_stage_id` a `tasks`

**Archivo:** `database/migrations/2026_04_28_000002_add_case_stage_id_to_tasks.php`

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->foreignId('case_stage_id')->nullable()->after('workflow_stage_id')
              ->constrained('case_stages')->nullOnDelete();
        $table->index(['case_id', 'case_stage_id', 'sort_order'], 'tasks_case_case_stage_order_idx');
    });

    // workflow_stage_id se mantiene durante la transicion (lectura).
    // Una migracion posterior (3.4) lo retira tras backfill exitoso.
}

public function down(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropIndex('tasks_case_case_stage_order_idx');
        $table->dropForeign(['case_stage_id']);
        $table->dropColumn('case_stage_id');
    });
}
```

### 3.3 Migracion: Backfill `case_stages` y `tasks.case_stage_id`

**Archivo:** `database/migrations/2026_04_28_000003_backfill_case_stages.php`

```php
public function up(): void
{
    // Idempotente: solo crea filas para cases que no tienen aun case_stages.
    DB::transaction(function () {
        $cases = DB::table('cases')->whereNotNull('case_type_id')->get();

        foreach ($cases as $case) {
            // Skip si ya tiene case_stages
            $exists = DB::table('case_stages')->where('case_id', $case->id)->exists();
            if ($exists) continue;

            $stages = DB::table('workflow_stages')
                ->where('case_type_id', $case->case_type_id)
                ->where('tenant_id', $case->tenant_id)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get();

            $idMap = []; // workflow_stage_id => new case_stage_id

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

                // Copiar translations polimorficas (Spec 46)
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

            // Re-apuntar tasks: workflow_stage_id -> case_stage_id
            foreach ($idMap as $oldStageId => $newCaseStageId) {
                DB::table('tasks')
                    ->where('case_id', $case->id)
                    ->where('workflow_stage_id', $oldStageId)
                    ->whereNull('case_stage_id')
                    ->update(['case_stage_id' => $newCaseStageId]);
            }

            // Re-apuntar current_stage_id de cases (de workflow_stages a case_stages)
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
    // No-op: revertir el backfill borraria datos del usuario. Manejarse desde 000001.down().
}
```

### 3.4 Migracion: Anadir `current_case_stage_id` a `cases`

**Archivo:** `database/migrations/2026_04_28_000004_add_current_case_stage_id_to_cases.php`

Debe ejecutarse **antes** del backfill (3.3) o el backfill se debe partir en dos. Por simplicidad operacional se ordena asi:

- `000001` crea `case_stages`.
- `000002` agrega `tasks.case_stage_id`.
- `000003` agrega `cases.current_case_stage_id` (este).
- `000004` ejecuta el backfill (renombrado a `000005`).

```php
public function up(): void
{
    Schema::table('cases', function (Blueprint $table) {
        $table->foreignId('current_case_stage_id')->nullable()->after('current_stage_id')
              ->constrained('case_stages')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('cases', function (Blueprint $table) {
        $table->dropForeign(['current_case_stage_id']);
        $table->dropColumn('current_case_stage_id');
    });
}
```

### 3.5 Migracion (diferida): Drop `workflow_stage_id` de `tasks`

**Archivo:** `database/migrations/2026_05_15_000001_drop_workflow_stage_id_from_tasks.php` (programada para release N+1, NO en este spec).

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropForeign(['workflow_stage_id']);
        $table->dropIndex('tasks_case_stage_order_idx');
        $table->dropColumn('workflow_stage_id');
    });
}
```

> **Razon de diferir:** durante la transicion el codigo escribe **a las dos columnas** (`workflow_stage_id` y `case_stage_id`) para permitir rollback. Tras una semana en produccion sin incidentes, una migracion siguiente retira la columna obsoleta. Esto reduce el riesgo del despliegue inicial.

### 3.6 Migracion: Permisos opcionales

No se introducen permisos nuevos. La operacion de modificar stages del case requiere `cases.update`, que ya existe. Si en el futuro se quiere granular, agregar `cases.stages.modify` en una migracion posterior siguiendo gotcha G4.

---

## 4. Cambios en Backend

### 4.1 Modelo `CaseStage` (NUEVO)

**Archivo:** `app/Models/CaseStage.php`

```php
namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CaseStage extends Model
{
    use BelongsToTenant, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'case_id', 'workflow_stage_id',
        'code', 'sort_order', 'is_active', 'is_terminal', 'color',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_terminal' => 'boolean',
    ];

    public function case(): BelongsTo { return $this->belongsTo(ImmigrationCase::class, 'case_id'); }
    public function originTemplate(): BelongsTo { return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class, 'case_stage_id'); }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code','sort_order','is_active','is_terminal','color'])
            ->logOnlyDirty();
    }
}
```

### 4.2 Form Requests

**`app/Http/Requests/Workflow/CreateCaseStageRequest.php`**

```php
public function rules(): array
{
    return [
        'name'                  => ['required', 'array'],
        'name.es'               => ['required', 'string', 'max:120'],
        'name.en'               => ['nullable', 'string', 'max:120'],
        'name.fr'               => ['nullable', 'string', 'max:120'],
        'description'           => ['nullable', 'array'],
        'description.es'        => ['nullable', 'string', 'max:500'],
        'description.en'        => ['nullable', 'string', 'max:500'],
        'description.fr'        => ['nullable', 'string', 'max:500'],
        'color'                 => ['required', 'string', 'in:primary,secondary,success,danger,warning,info,dark'],
        'is_terminal'           => ['boolean'],
    ];
}
```

**`app/Http/Requests/Workflow/UpdateCaseStageRequest.php`** — igual al anterior pero todos `sometimes`.

**`app/Http/Requests/Workflow/ReorderCaseStagesRequest.php`**

```php
public function rules(): array
{
    $caseId = $this->route('case')->id;
    return [
        'stage_ids'   => ['required', 'array', 'min:1'],
        'stage_ids.*' => [
            'integer',
            Rule::exists('case_stages', 'id')->where('case_id', $caseId)->whereNull('deleted_at'),
        ],
    ];
}

public function withValidator($validator): void
{
    $validator->after(function ($v) {
        $caseId   = $this->route('case')->id;
        $expected = CaseStage::where('case_id', $caseId)->whereNull('deleted_at')->count();
        if (count($this->stage_ids) !== $expected) {
            $v->errors()->add('stage_ids', 'Debe incluir TODAS las etapas del expediente.');
        }
        if (count($this->stage_ids) !== count(array_unique($this->stage_ids))) {
            $v->errors()->add('stage_ids', 'Hay etapas duplicadas en el orden.');
        }
    });
}
```

**`app/Http/Requests/Workflow/DeleteCaseStageRequest.php`**

```php
public function rules(): array
{
    $caseId  = $this->route('case')->id;
    $stageId = $this->route('stage')->id;
    return [
        'policy' => ['required', 'in:move,trash'],
        'move_to_stage_id' => [
            'required_if:policy,move',
            'integer',
            Rule::exists('case_stages', 'id')
                ->where('case_id', $caseId)
                ->where('id', '!=', $stageId)
                ->whereNull('deleted_at'),
        ],
    ];
}
```

### 4.3 Service `CaseStageService` (NUEVO)

**Archivo:** `app/Services/Workflow/CaseStageService.php`

```php
class CaseStageService
{
    public function __construct(private WorkflowInstantiator $instantiator) {}

    public function listForCase(ImmigrationCase $case): Collection
    {
        return CaseStage::with('translations')
            ->where('case_id', $case->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function createAdHocStage(ImmigrationCase $case, array $data): CaseStage
    {
        return DB::transaction(function () use ($case, $data) {
            $maxOrder = CaseStage::where('case_id', $case->id)->max('sort_order') ?? -1;

            $stage = CaseStage::create([
                'tenant_id'         => $case->tenant_id,
                'case_id'           => $case->id,
                'workflow_stage_id' => null,
                'code'              => 'adhoc_' . Str::random(10),
                'sort_order'        => $maxOrder + 1,
                'is_active'         => true,
                'is_terminal'       => $data['is_terminal'] ?? false,
                'color'             => $data['color'],
            ]);

            $stage->setTranslations('name', $data['name']);
            if (!empty($data['description'])) {
                $stage->setTranslations('description', $data['description']);
            }

            activity()->causedBy(Auth::user())->performedOn($case)
                ->withProperties(['case_stage_id' => $stage->id, 'name_es' => $data['name']['es']])
                ->log('Agrego etapa ad-hoc al expediente');

            return $stage->load('translations');
        });
    }

    public function updateStage(CaseStage $stage, array $data): CaseStage
    {
        return DB::transaction(function () use ($stage, $data) {
            $stage->fill(array_intersect_key($data, array_flip(['color','is_terminal'])));
            $stage->save();

            if (!empty($data['name']))        $stage->setTranslations('name', $data['name']);
            if (!empty($data['description'])) $stage->setTranslations('description', $data['description']);

            return $stage->fresh('translations');
        });
    }

    public function reorderStages(ImmigrationCase $case, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($case, $orderedIds) {
            // Lock todas las stages del case
            $stages = CaseStage::where('case_id', $case->id)->lockForUpdate()->get();

            foreach ($orderedIds as $i => $id) {
                CaseStage::where('id', $id)->where('case_id', $case->id)
                    ->update(['sort_order' => $i]);
            }

            activity()->causedBy(Auth::user())->performedOn($case)
                ->withProperties(['order' => $orderedIds])
                ->log('Reordeno etapas del expediente');

            return $this->listForCase($case);
        });
    }

    public function deleteStage(ImmigrationCase $case, CaseStage $stage, string $policy, ?int $moveToStageId): void
    {
        DB::transaction(function () use ($case, $stage, $policy, $moveToStageId) {
            // Validacion: stage terminal unica
            if ($stage->is_terminal) {
                $otherTerminals = CaseStage::where('case_id', $case->id)
                    ->where('id', '!=', $stage->id)
                    ->where('is_terminal', true)->count();
                if ($otherTerminals === 0) {
                    abort(422, 'No puedes eliminar la unica etapa terminal del expediente.');
                }
            }

            // Reasignar current_case_stage_id si apunta a esta stage
            if ($case->current_case_stage_id === $stage->id) {
                $prev = CaseStage::where('case_id', $case->id)
                    ->where('id', '!=', $stage->id)
                    ->where('sort_order', '<', $stage->sort_order)
                    ->orderByDesc('sort_order')->first();
                $case->updateQuietly(['current_case_stage_id' => $prev?->id]);
            }

            // Politica para tasks
            $tasks = Task::where('case_stage_id', $stage->id)->lockForUpdate()->get();

            if ($policy === 'move') {
                $maxOrder = Task::where('case_id', $case->id)
                    ->where('case_stage_id', $moveToStageId)
                    ->max('sort_order') ?? -1;
                foreach ($tasks as $i => $t) {
                    $t->update([
                        'case_stage_id' => $moveToStageId,
                        'sort_order'    => $maxOrder + 1 + $i,
                    ]);
                }
            } elseif ($policy === 'trash') {
                foreach ($tasks as $t) $t->delete();
            }

            $stage->delete();
            $this->instantiator->recalculateProgress($case);

            activity()->causedBy(Auth::user())->performedOn($case)
                ->withProperties([
                    'case_stage_id' => $stage->id,
                    'policy'        => $policy,
                    'move_to'       => $moveToStageId,
                    'tasks_affected'=> $tasks->count(),
                ])
                ->log('Elimino etapa del expediente');
        });
    }
}
```

### 4.4 Controller `CaseStageController` (NUEVO)

**Archivo:** `app/Http/Controllers/Api/CaseStageController.php`

Cinco metodos thin que delegan al service:

```php
public function index(ImmigrationCase $case)
{
    $this->authorize('view', $case);
    return CaseStageResource::collection($this->service->listForCase($case));
}

public function store(CreateCaseStageRequest $req, ImmigrationCase $case)
{
    $this->authorize('update', $case);
    return new CaseStageResource($this->service->createAdHocStage($case, $req->validated()));
}

public function update(UpdateCaseStageRequest $req, ImmigrationCase $case, CaseStage $stage)
{
    $this->authorize('update', $case);
    abort_unless($stage->case_id === $case->id, 404);
    return new CaseStageResource($this->service->updateStage($stage, $req->validated()));
}

public function reorder(ReorderCaseStagesRequest $req, ImmigrationCase $case)
{
    $this->authorize('update', $case);
    return CaseStageResource::collection(
        $this->service->reorderStages($case, $req->validated()['stage_ids'])
    );
}

public function destroy(DeleteCaseStageRequest $req, ImmigrationCase $case, CaseStage $stage)
{
    $this->authorize('update', $case);
    abort_unless($stage->case_id === $case->id, 404);
    $data = $req->validated();
    $this->service->deleteStage($case, $stage, $data['policy'], $data['move_to_stage_id'] ?? null);
    return response()->noContent();
}
```

### 4.5 Resource `CaseStageResource` (NUEVO)

```php
public function toArray($request): array
{
    return [
        'id'                 => $this->id,
        'case_id'            => $this->case_id,
        'workflow_stage_id'  => $this->workflow_stage_id, // NULL = ad-hoc
        'is_ad_hoc'          => $this->workflow_stage_id === null,
        'code'               => $this->code,
        'sort_order'         => $this->sort_order,
        'is_terminal'        => $this->is_terminal,
        'color'              => $this->color,
        'name'               => $this->translationsByField()['name']        ?? [],
        'description'        => $this->translationsByField()['description'] ?? [],
        'name_resolved'      => $this->trans('name', app()->getLocale()),
    ];
}
```

### 4.6 Modificaciones a `WorkflowInstantiator` (Spec 46)

**Cambios clave en `instantiate()`:**

```php
public function instantiate(ImmigrationCase $case, array $excludedTemplateIds = []): void
{
    $stages = WorkflowStage::with([...])
        ->where('case_type_id', $case->case_type_id)
        ->where('is_active', true)
        ->orderBy('sort_order')->get();

    if ($stages->isEmpty()) return;

    DB::transaction(function () use ($case, $stages, $excludedTemplateIds) {
        $stageIdMap = []; // workflow_stage_id => case_stage_id
        foreach ($stages as $stage) {
            $caseStage = CaseStage::create([
                'tenant_id'         => $case->tenant_id,
                'case_id'           => $case->id,
                'workflow_stage_id' => $stage->id,
                'code'              => $stage->code,
                'sort_order'        => $stage->sort_order,
                'is_active'         => $stage->is_active,
                'is_terminal'       => $stage->is_terminal,
                'color'             => $stage->color,
            ]);
            // Copiar translations del template
            foreach ($stage->translations as $t) {
                $caseStage->translations()->create([
                    'tenant_id' => $t->tenant_id,
                    'locale'    => $t->locale,
                    'field'     => $t->field,
                    'value'     => $t->value,
                ]);
            }
            $stageIdMap[$stage->id] = $caseStage->id;
        }

        // Tasks ahora apuntan a case_stage_id
        $rows = [];
        foreach ($stages as $stage) {
            foreach ($stage->taskTemplates as $tmpl) {
                if (in_array($tmpl->id, $excludedTemplateIds, true)) continue;
                $rows[] = [
                    // ... mismos campos que Spec 46 ...
                    'case_stage_id' => $stageIdMap[$stage->id],
                    'workflow_stage_id' => $stage->id, // mantener para rollback durante transicion
                ];
            }
        }
        if ($rows) Task::insert($rows);

        $case->workflow_snapshot      = $this->buildSnapshot($stages, $case->language ?? 'es');
        $case->current_case_stage_id  = $stageIdMap[$stages->first()->id];
        $case->current_stage_id       = $stages->first()->id; // mantener durante transicion
        $case->saveQuietly();

        $this->recalculateProgress($case);
    });
}
```

**Cambios en `advanceStage()`:**

```php
public function advanceStage(ImmigrationCase $case): ?CaseStage
{
    return DB::transaction(function () use ($case) {
        $case = ImmigrationCase::lockForUpdate()->findOrFail($case->id);
        if (!$case->current_case_stage_id) return null;

        $blocking = Task::where('case_stage_id', $case->current_case_stage_id)
            ->whereIn('status', ['new','assigned','in_progress'])
            ->whereHas('template', fn ($q) => $q->where('blocks_stage_completion', true))
            ->exists();

        if ($blocking) throw new StageBlockedException(...);

        $current = CaseStage::find($case->current_case_stage_id);
        $next = CaseStage::where('case_id', $case->id)
            ->where('is_active', true)
            ->where('sort_order', '>', $current->sort_order)
            ->orderBy('sort_order')->first();

        $updates = ['current_case_stage_id' => $next?->id];
        if ($next?->is_terminal) {
            $updates['status']    = ImmigrationCase::STATUS_CLOSED;
            $updates['closed_at'] = now();
        }
        $case->update($updates);

        return $next;
    });
}
```

### 4.7 Modificaciones a `CaseTaskService` (Spec 47)

`MoveTaskRequest` y `CreateAdHocTaskRequest` cambian su validacion: `target_stage_id` y `workflow_stage_id` ahora se validan contra `case_stages.id WHERE case_id = $case->id`, no contra `workflow_stages.id WHERE case_type_id`.

```php
'target_case_stage_id' => [
    'required', 'integer',
    Rule::exists('case_stages', 'id')->where('case_id', $case->id)->whereNull('deleted_at'),
],
```

`moveTask()` actualiza `case_stage_id` en lugar de `workflow_stage_id`.

### 4.8 Rutas

**`routes/api.php`** (dentro del grupo `auth:sanctum + tenant`):

```php
Route::get   ('/cases/{case}/stages',                 [CaseStageController::class, 'index']);
Route::post  ('/cases/{case}/stages',                 [CaseStageController::class, 'store']);
Route::patch ('/cases/{case}/stages/reorder',         [CaseStageController::class, 'reorder']);
Route::patch ('/cases/{case}/stages/{stage}',         [CaseStageController::class, 'update']);
Route::delete('/cases/{case}/stages/{stage}',         [CaseStageController::class, 'destroy']);
```

> El path `/reorder` se declara antes de `/{stage}` para evitar colision de route binding.

### 4.9 Policies

`CasePolicy::update($user, $case)` ya cubre la operacion. No se anaden policies nuevas.

---

## 5. Cambios en Frontend

### 5.1 Componentes nuevos

```
resources/js/src/components/cases/board/
├── StageEditorModal.vue          # Crear/editar stage (i18n ES/EN/FR)
├── DeleteStagePolicyModal.vue    # Modal con radio: mover|papelera + dropdown destino
├── AddStageButton.vue            # Boton "+" al final del board
└── StageHeaderEditable.vue       # Header de StageColumn con drag handle horizontal + kebab
```

**Componentes a modificar:**
- `TaskBoard.vue`: envolver columnas en un segundo VueDraggable horizontal con `group="case-stages"`. Es contenedor padre del Spec 47, asi que el grupo de tareas (`group="case-tasks"`) sigue siendo independiente sin colision.
- `StageColumn.vue`: header recibe `editable` prop; agrega el drag handle horizontal y el kebab menu.

### 5.2 Lineamientos graficos

- **Color primario:** `primary` `#4361ee` para botones de accion (Crear etapa, Guardar).
- **Stage header:** dot de color (de `stage.color`), titulo `text-base font-semibold`, kebab menu icon `IconDotsVertical`.
- **Drag handle horizontal:** icono `IconGripVertical` rotado 90deg (o `IconGripHorizontal`), `cursor-grab`/`cursor-grabbing`, color `text-gray-400 dark:text-gray-500`.
- **Modal:** patron `panel rounded-md` ya usado en NewTaskModal (consistencia con Spec 47).
- **Modo oscuro:** todas las cards/headers con `dark:bg-[#1c232f] dark:border-gray-700`.
- **Stage ad-hoc badge:** pequeño chip "Ad-hoc" `bg-secondary/10 text-secondary` para diferenciar de stages template.
- **Boton "+ Nueva etapa":** dashed border, `border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary text-primary`.

### 5.3 vue-draggable-plus — Drag horizontal

`TaskBoard.vue` usa **dos niveles** de VueDraggable:

```vue
<VueDraggable
  v-model="orderedStages"
  :animation="150"
  group="case-stages"
  handle=".stage-drag-handle"
  :disabled="!editable"
  class="flex gap-4 overflow-x-auto"
  @end="onStageDragEnd"
>
  <StageColumn
    v-for="stage in orderedStages"
    :key="stage.id"
    :stage="stage"
    :tasks="tasksByStage[stage.id] ?? []"
    :editable="editable"
  />
</VueDraggable>
```

El handle `stage-drag-handle` es exclusivo del header de columna; las TaskCard tienen su propio handle (`task-drag-handle`) y siguen usando `group="case-tasks"`. Como los grupos son distintos, no hay colision: arrastrar una task no mueve columnas y viceversa.

**Impacto en handlers Spec 47:** ninguno. Los handlers de tareas (`onTaskMove`) reciben los mismos eventos. Solo se anade `onStageDragEnd` a nivel TaskBoard.

### 5.4 Pinia store

**`resources/js/src/stores/useCaseStagesStore.ts`** (NUEVO)

```typescript
state: () => ({
  stagesByCase: {} as Record<number, CaseStage[]>,
  pendingStageIds: new Set<number>(),
})

actions:
- load(caseId)                                  // GET index
- createStage(caseId, payload)                  // POST store + push optimistic
- updateStage(caseId, stageId, payload)         // PATCH
- reorder(caseId, orderedIds)                   // optimistic reorder + PATCH /reorder; rollback on error
- deleteStage(caseId, stageId, policy, moveTo)  // DELETE; on success refresh tasksByStage del caseTasksStore
```

`useCaseTasksStore` se extiende con un metodo `rebindAfterStageDelete(stageId, policy, moveToId)` para sincronizar in-memory.

### 5.5 Service capa frontend

**`resources/js/src/services/caseStageService.ts`** (NUEVO)

```typescript
listStages(caseId): GET /cases/{caseId}/stages
createStage(caseId, payload): POST
updateStage(caseId, stageId, payload): PATCH /cases/{caseId}/stages/{stageId}
reorderStages(caseId, stageIds): PATCH /cases/{caseId}/stages/reorder
deleteStage(caseId, stageId, body): DELETE
```

### 5.6 Integracion en `cases/edit.vue`

- TaskBoard recibe nueva prop `editable-stages` (default true para vista edit, false para show).
- Boton "+ Nueva etapa" al final del scroll horizontal.
- Header de cada columna en modo editable expone:
  - Nombre (click -> abre StageEditorModal con campos i18n)
  - Drag handle horizontal
  - Kebab: Editar, Eliminar (-> DeleteStagePolicyModal)
- Tras crear/editar/eliminar/reordenar, ambos stores (stages y tasks) refrescan in-memory; no se hace full reload del case.

### 5.7 i18n (claves nuevas, FLAT)

Agregar en los 16 locales:
- `cases.stage.add_new`
- `cases.stage.edit`
- `cases.stage.delete`
- `cases.stage.modal_title_new`
- `cases.stage.modal_title_edit`
- `cases.stage.field_name`
- `cases.stage.field_description`
- `cases.stage.field_color`
- `cases.stage.field_is_terminal`
- `cases.stage.is_terminal_warning`
- `cases.stage.adhoc_badge`
- `cases.stage.delete_policy_title`
- `cases.stage.delete_policy_move`
- `cases.stage.delete_policy_trash`
- `cases.stage.delete_policy_dest`
- `cases.stage.delete_confirm_empty`
- `cases.stage.delete_blocked_terminal`
- `cases.stage.reorder_failed`
- `cases.stage.create_failed`
- `cases.stage.locale_es`
- `cases.stage.locale_en`
- `cases.stage.locale_fr`

---

## 6. Manejo de Errores y Edge Cases

| Caso | Manejo |
|---|---|
| Reordenar enviando subset de stages | 422 `stage_ids` debe incluir TODAS las etapas |
| Reordenar con duplicados | 422 detectado en `withValidator` |
| Eliminar la unica stage terminal | 422 con codigo `TERMINAL_REQUIRED` |
| Eliminar stage que es `current_case_stage_id` | Reasigna current al anterior por sort_order; si no hay, NULL |
| Eliminar stage con tasks sin elegir politica | 422 `policy` requerido |
| Move policy a `move_to_stage_id` invalido | 422 (Rule::exists con WHERE case_id) |
| Crear stage sin name.es | 422 `name.es` required |
| Carrera entre dos consultores reordenando | `lockForUpdate` sobre case_stages; segunda escritura ve estado fresco; orden definido por last write wins |
| Drag horizontal mientras hay tasks pending | Permitido; tasks no se ven afectadas (siguen apuntando al mismo case_stage_id) |
| Stage ad-hoc + intento de `advanceStage` | Funciona: la stage ad-hoc participa por su sort_order en case_stages |
| Restore de task a stage eliminada | task.case_stage_id queda NULL (FK nullOnDelete); UI la muestra en grupo "Sin etapa" |
| Backfill ya ejecutado parcialmente | Migracion 3.3 verifica `exists()` antes de insertar; idempotente |
| Tenant isolation cruzado | [[BelongsToTenant]] + verificacion `$stage->case_id === $case->id` en controller |
| FK del template original cambia (admin renombra workflow_stage) | case_stages no se ve afectada (tiene su propia copia de code/translations) |

---

## 7. Seguridad

- **Authorization:** `$this->authorize('update', $case)` en cada endpoint mutador; `view` en `index`.
- **Tenant isolation:** `CaseStage` usa `BelongsToTenant`. Adicionalmente, cada controller verifica `$stage->case_id === $case->id` para descartar route-binding cross-case dentro del mismo tenant.
- **Validacion de pertenencia:** `move_to_stage_id` y `stage_ids[*]` se validan via `Rule::exists` con WHERE `case_id`.
- **No exposicion de templates:** eliminar una `CaseStage` jamas toca `workflow_stages`. La FK es `nullOnDelete` desde el lado contrario.
- **Activity log:** create/update/reorder/delete todos generan entrada en `activity_log` con `causer_id` y `properties` detalladas para auditoria.
- **CSRF + Sanctum:** cubierto por middleware existente.
- **Rate limiting:** las rutas heredan el throttle estandar de la API (no requiere uno especial).

---

## 8. Performance

- **N stages por case:** tipicamente 4-8, maximo razonable 15. Reorder = N UPDATE = ~15ms.
- **Indice clave:** `(tenant_id, case_id, sort_order)` ya en migracion 3.1.
- **Listado del board:** `case_stages` con `tasks` (eager) + `translations` (eager) en una sola query (`with(['tasks','translations'])`). N+1 evitado.
- **Optimistic update frontend:** drag horizontal no espera respuesta; se confirma o revierte tras PATCH.
- **Backfill (one-time):** ejecutar en mantenimiento o en chunk con `DB::transaction` por case. Para tenants con miles de cases, considerar comando artisan dedicado en lugar de migracion (alternativa documentada en seccion 11).
- **Sin polling / sin broadcasting** (consistente con Spec 47 v1).

---

## 9. Testing

### Backend (Feature en `tests/Feature/Workflow/`)

`CaseStagesManagementTest.php`:
1. `test_consultant_can_list_case_stages_after_case_creation`
2. `test_consultant_can_create_ad_hoc_stage_with_translations`
3. `test_create_stage_requires_name_es`
4. `test_create_stage_validates_color_in_palette`
5. `test_consultant_can_reorder_stages_horizontally`
6. `test_reorder_rejects_partial_stage_ids`
7. `test_reorder_rejects_duplicate_stage_ids`
8. `test_consultant_can_delete_stage_with_move_policy`
9. `test_consultant_can_delete_stage_with_trash_policy`
10. `test_cannot_delete_only_terminal_stage`
11. `test_delete_stage_reassigns_current_case_stage_id_when_was_current`
12. `test_cannot_modify_stages_of_case_from_other_tenant`
13. `test_advance_stage_uses_case_stages_not_workflow_stages`
14. `test_advance_stage_works_with_ad_hoc_stage_in_middle`
15. `test_progress_recalculates_after_stage_deletion_with_trash_policy`
16. `test_workflow_snapshot_is_immutable_after_stage_changes`

`CaseInstantiationTest.php` (extender Spec 46):
17. `test_instantiation_creates_case_stages_from_workflow_stages`
18. `test_instantiation_copies_template_translations_into_case_stages`
19. `test_instantiation_sets_current_case_stage_id_to_first_stage`

`BackfillCaseStagesTest.php`:
20. `test_backfill_creates_case_stages_for_existing_cases`
21. `test_backfill_remaps_tasks_workflow_stage_id_to_case_stage_id`
22. `test_backfill_is_idempotent`
23. `test_backfill_copies_translations_polymorphically`

### Unit (`tests/Unit/`)
- `CaseStageServiceTest::test_reorder_assigns_consecutive_sort_order`
- `CaseStageServiceTest::test_delete_with_move_appends_tasks_at_end`
- `CaseStageServiceTest::test_create_increments_max_sort_order`

### Frontend (manual)
- Drag horizontal de columna persiste tras refresh.
- Drag de tarea entre columnas sigue funcionando (no regression Spec 47).
- Modal i18n: alternar locale ES/EN/FR muestra el name correcto.
- Eliminar stage con tasks abre policy modal; ambas opciones funcionan.
- Stage ad-hoc en medio: avanzar etapa pasa por ella.
- Modo oscuro consistente.

---

## 10. Plan de Implementacion (Checklist)

### Fase 1 — Base de datos y modelos

- [x] **\1** Crear migracion `2026_04_28_000001_create_case_stages_table.php`.
- [x] **\1** Crear migracion `2026_04_28_000002_add_case_stage_id_to_tasks.php`.
- [x] **\1** Crear migracion `2026_04_28_000003_add_current_case_stage_id_to_cases.php`.
- [x] **\1** Crear migracion `2026_04_28_000004_backfill_case_stages.php` (idempotente).
- [x] **\1** Crear modelo `App\Models\CaseStage` con `BelongsToTenant`, `HasTranslations`, `SoftDeletes`, `LogsActivity`.
- [x] **\1** Anadir relaciones en `ImmigrationCase` (`caseStages()`, `currentCaseStage()`).
- [x] **\1** Anadir relacion en `Task` (`caseStage()`); fillable += `case_stage_id`.
- [x] **\1** Verificar `php artisan migrate` y `migrate:fresh --seed` localmente.

### Fase 2 — Servicios

- [x] **\1** Crear `App\Services\Workflow\CaseStageService` con metodos list/create/update/reorder/delete.
- [x] **\1** Modificar `WorkflowInstantiator::instantiate()` para crear `case_stages` y mapear tasks a `case_stage_id`.
- [x] **\1** Modificar `WorkflowInstantiator::advanceStage()` para usar `case_stages` por `case_id + sort_order`.
- [x] **\1** Modificar `CaseTaskService::moveTask` y `createAdHocTask` para usar `case_stage_id`.
- [x] **\1** Adaptar `recalculateProgress` para filtrar por `case_stage_id` (queries que antes usaban `workflow_stage_id`).

### Fase 3 — API

- [x] **\1** Crear `CreateCaseStageRequest`, `UpdateCaseStageRequest`, `ReorderCaseStagesRequest`, `DeleteCaseStageRequest`.
- [x] **\1** Crear `CaseStageController` con index/store/update/reorder/destroy.
- [x] **\1** Crear `CaseStageResource`.
- [x] **\1** Anadir 5 rutas en `routes/api.php` (con `/reorder` antes de `/{stage}`).
- [x] **\1** Actualizar `CaseResource` para incluir `current_case_stage_id`.
- [x] **\1** Actualizar `CaseTaskController` (Move y Create) para validar `case_stage_id`.

### Fase 4 — Frontend store y servicios

- [x] **\1** Crear `services/caseStageService.ts` con los 5 endpoints.
- [x] **\1** Crear `stores/useCaseStagesStore.ts` (optimistic load, reorder, create, update, delete).
- [x] **\1** Extender `useCaseTasksStore` con `rebindAfterStageDelete`.
- [x] **\1** Anadir tipos TypeScript: `CaseStage`, `CreateCaseStagePayload`, `DeleteStagePolicy`.

### Fase 5 — Frontend componentes

- [x] **\1** Crear `StageEditorModal.vue` (form con campos i18n ES/EN/FR + color picker).
- [x] **\1** Crear `DeleteStagePolicyModal.vue` (radio policy + dropdown destino).
- [x] **\1** Crear `AddStageButton.vue` (dashed border, abre modal).
- [x] **\1** Crear `StageHeaderEditable.vue` (drag handle + kebab + click-to-edit).
- [x] **\1** Modificar `TaskBoard.vue` para envolver columnas en VueDraggable group="case-stages" horizontal.
- [x] **\1** Modificar `StageColumn.vue` para usar `StageHeaderEditable` cuando `editable=true`.
- [x] **\1** Integrar en `cases/edit.vue` (botton + modales + handlers).

### Fase 6 — i18n

- [x] **\1** Agregar las 21 claves nuevas (FLAT) en los 16 locales.
- [x] **\1** Verificar Arabic (ae) RTL en el board horizontal (espejado).

### Fase 7 — Testing

- [x] **\1** Crear `tests/Feature/Workflow/CaseStagesManagementTest.php` (16 tests).
- [x] **\1** Extender `CaseInstantiationTest` con 3 tests de instanciacion de case_stages.
- [x] **\1** Crear `tests/Feature/Workflow/BackfillCaseStagesTest.php` (4 tests).
- [x] **\1** Crear `tests/Unit/CaseStageServiceTest.php`.
- [x] **\1** Verificar que los tests existentes de Spec 46 y 47 siguen pasando (ajustar referencias a `case_stage_id`).

### Fase 8 — QA y refinamiento

- [x] **\1** Smoke test: crear caso, agregar stage ad-hoc, reordenar, eliminar con policy 'move'.
- [x] **\1** Smoke test: avanzar etapa pasando por una stage ad-hoc.
- [x] **\1** Verificar modo oscuro y RTL (locale `ae`).
- [x] **\1** Verificar accesibilidad: teclado para reordenar columnas (kebab "Mover etapa a posicion N").

### Fase 9 — Despliegue

- [ ] **9.1** Ejecutar backup de produccion antes del deploy.
- [ ] **9.2** Ejecutar migraciones en staging; validar backfill manualmente con queries.
- [ ] **9.3** Deploy de codigo (mantiene escritura dual a `workflow_stage_id` y `case_stage_id`).
- [ ] **9.4** Monitorizar 7 dias `activity_log` y errores de Sentry.
- [ ] **9.5** Programar migracion `2026_05_15` para drop de `tasks.workflow_stage_id` y `cases.current_stage_id`.

---

## 11. Migracion y Despliegue

### 11.1 Estrategia para produccion

El despliegue es delicado porque involucra backfill masivo. Se aplica una estrategia de **cambio en dos fases (expand-contract pattern)**:

**Fase EXPAND (este spec):**
1. Anadir las nuevas columnas y tabla (`case_stages`, `tasks.case_stage_id`, `cases.current_case_stage_id`).
2. Backfill idempotente.
3. El codigo escribe **a ambos** lados (`workflow_stage_id` Y `case_stage_id`) durante el periodo transitorio. Las lecturas usan `case_stage_id`.
4. Validar 7 dias en produccion.

**Fase CONTRACT (release N+1, no en este spec):**
5. Migracion `2026_05_15_000001` retira `tasks.workflow_stage_id`, `cases.current_stage_id`, indice viejo.
6. Codigo deja de escribir el campo legacy.

### 11.2 Idempotencia del backfill

- La migracion 1.4 verifica `case_stages.case_id` antes de insertar.
- Si se interrumpe a mitad, una segunda ejecucion completa lo faltante sin duplicar.
- En tenants grandes (>5000 cases), considerar mover el backfill a un comando artisan: `php artisan workflow:backfill-case-stages --chunk=100` y dejar la migracion vacia. Esto evita el lock prolongado durante `migrate`.

### 11.3 Compatibilidad con expedientes legacy

Cases anteriores a Spec 46 (sin `case_type_id` o sin workflow definido) NO reciben `case_stages` (el backfill los salta porque el JOIN con `workflow_stages` no produce filas). Su comportamiento en el board sera:
- `stages` vacio en el endpoint -> el frontend muestra mensaje "Este expediente no tiene workflow. Crea la primera etapa." y habilita el boton "+ Nueva etapa" como entrada.
- Esto es una mejora UX adicional sobre el estado actual.

### 11.4 Rollback

Si tras deploy hay incidente:
1. Revertir codigo (commit anterior).
2. NO ejecutar migraciones down (preservar datos).
3. El codigo viejo sigue funcionando porque `tasks.workflow_stage_id` y `cases.current_stage_id` se mantuvieron escribiendose.
4. Investigar y re-deployar con fix.

### 11.5 Sin downtime

- Migraciones `ADD COLUMN` y `CREATE TABLE` son online en MySQL 8 con InnoDB.
- El backfill se ejecuta en `DB::transaction` por case (chunks pequeños) -> no bloquea tablas largo tiempo.
- Frontend se cachea en CDN; deploy de assets antes de migraciones para evitar 404 de chunks.

### 11.6 Comunicacion al usuario

- No requiere tutorial activo: la nueva UI aparece en la vista de edicion del expediente.
- Sugerencia: tooltip de bienvenida en `cases/edit.vue` la primera vez que el usuario entra tras el deploy ("Ahora puedes personalizar las etapas de cada expediente. Arrastra horizontalmente para reordenar.").
