---
tipo: spec
numero: 69
modulo: Clientes / Acompañantes / Expedientes
dominio: CLNT/CASES
estado: DONE
tags: [spec, clientes, empresa, companions, jerarquia, wizard, multinivel]
relacionados:
  - "[[64_clientes_empresa_patrocinador_beneficiario|Spec 64]]"
  - "[[58_cliente_vs_aplicante_principal|Spec 58]]"
  - "[[10_companions_en_detalle_expediente|Spec 10]]"
  - "[[09_epic_2.2_case_wizard|Spec 09]]"
  - "[[08_permisos_clientes_acompanantes_roles|Spec 08]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
---
# [[69_companions_jerarquicos_empresa|Spec 69]] — Companions Jerárquicos para Clientes Empresa

**Dominio:** CLNT / CASES  
**Estado:** PEND  
**Relacionados:** 64 (Empresa: Patrocinador + Beneficiario), 58 (Cliente vs Aplicante Principal), 10 (Companions en Expediente), 09 (Wizard Creación), 08 (Permisos Clientes/Acompañantes), 56 (Directorios Wizard)  
**Creado:** 2026-05-26  
**Autor:** Análisis de Impacto — Architect Agent + Validación de Reglas de Negocio

---

## Resumen Ejecutivo

El sistema actual (Spec 64) modela los acompañantes de una empresa como una **lista plana**: todos los companions cuelgan del mismo `client_id` sin distinción de a qué empleado pertenecen. Esta spec introduce una **jerarquía de 1 nivel** que permite que cada `Companion(relationship='beneficiary')` actúe como *empleado* y tenga su propio subgrupo de *familiares* directamente vinculados.

```
Empresa (Client, type='company')
    └── Companion (relationship='beneficiary') ← Empleado / Beneficiario Principal
            ├── Companion (relationship='spouse')   ← Cónyuge del empleado
            ├── Companion (relationship='child')    ← Hijo del empleado
            └── Companion (relationship='parent')   ← Padre del empleado
```

Las reglas de negocio que guían esta arquitectura son:

1. **Rol por defecto en empresa:** Cualquier companion creado directamente en el perfil de la empresa se registra como `beneficiary` (empleado). Sus familiares se añaden bajo su Card expandible.
2. **Subgrupo familiar:** Cada empleado tiene su propia lista de familiares (CRUD). Esta lista es gestionada por el consultor y aplica solo para clientes de tipo empresa.
3. **Filtrado en el Wizard (Step 3):** Al seleccionar un empleado como Aplicante Principal, solo se muestran sus familiares específicos en el listado de acompañantes elegibles.
4. **Continuidad desde Step 4:** El flujo del wizard no cambia a partir del paso 4.
5. **cases/show.vue:** La pestaña Companions muestra exclusivamente los familiares del empleado que fueron incluidos en ese caso (snapshot por expediente).
6. **cases/edit.vue:** El selector "Agregar companion" está limitado al universo de familiares del empleado del caso que aún no están incluidos.

**Decisión arquitectónica raíz:** Self-reference `companions.parent_companion_id` (nullable FK → companions). No se crea tabla pivote porque la relación es 1-a-N estricta — un familiar pertenece a un único empleado. La jerarquía es de profundidad máxima 1 (no se permiten familiares de familiares), enforced a nivel de servicio y CHECK constraint MySQL 8.

---

## Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---------|---------|----------------------|
| H1 | `companions` no tiene `parent_companion_id` — todos los companions son hermanos del mismo `client_id` | `database/migrations/0001_01_01_000006_create_business_tables.php:75` | Se requiere migración para añadir la columna y FK |
| H2 | `case_companions` es un pivote `case_id ↔ companion_id` sin columna de jerarquía | `database/migrations/0001_01_01_000006_create_business_tables.php:331` | Compatible con jerarquía sin cambios — los familiares se asocian individualmente al caso como siempre |
| H3 | `cases.primary_applicant_companion_id` ya existe (Spec 58) | `database/migrations/2026_05_14_000001_add_primary_applicant_to_cases.php` | Ancla para "el caso pertenece al empleado X" — cero cambios requeridos en `cases` |
| H4 | `Companion::RELATIONSHIP_BENEFICIARY = 'beneficiary'` y `scopeBeneficiaries()` ya implementados en Spec 64 | `app/Models/Companion.php` | Base reutilizable para `scopeEmployees()` = beneficiaries sin parent |
| H5 | `StepCompanions.vue` ya carga todos los companions del cliente y distingue `isCompanyCase` | `resources/js/src/views/cases/wizard/steps/StepCompanions.vue` | El cambio es agregar computed `displayableCompanions` que filtre por `parent_companion_id` |
| H6 | `CompanionFormModal.vue` ya acepta prop `presetRelationship` y lo bloquea en edición (Spec 64 fix) | `resources/js/src/components/companions/CompanionFormModal.vue` | Se extiende con prop `parentCompanionId` para crear familiares bajo un empleado específico |
| H7 | `CompanionObserver` ya hace cascade soft-delete a `legalDocuments` | `app/Observers/CompanionObserver.php` | Se extiende para cascade soft-delete a `familyMembers` cuando se elimina un empleado |
| H8 | `clients/show.vue` ya tiene pestaña Companions con Card list para empresa (Spec 64) | `resources/js/src/views/clients/show.vue` | Se refactoriza para mostrar accordion por empleado cuando `client.type === 'company'` |
| H9 | CHECK constraint para `parent_companion_id` no puede validar "parent debe ser beneficiary" via subquery en MySQL | MySQL 8.0 docs | La invariante se enforced en `CompanionService` — CHECK solo valida que familiares no sean `beneficiary` |
| H10 | `case_companions.companion_id` tiene `cascadeOnDelete` en la migración base | `database/migrations/0001_01_01_000006_create_business_tables.php:334` | Hard-delete de un empleado limpia automáticamente sus familiares del pivote de casos |

---

## Decisiones Clave

| # | Decisión | Justificación |
|---|---------|---------------|
| D1 | **Self-reference `companions.parent_companion_id` nullable** sobre Opción B (tabla pivote) | La relación es 1-a-N estricta — un familiar pertenece a un único empleado. Many-to-many sería semánticamente incorrecto y añadiría JOINs en todos los listados. |
| D2 | **Profundidad máxima 1** (empleado → familiar). Nivel 2 (familiar de familiar) bloqueado en `CompanionService` | No hay caso de negocio para sub-jerarquías. CHECK constraint MySQL + validación de servicio son dos capas de protección. |
| D3 | **`parent_companion_id` es inmutable** post-creación (removido en `UpdateCompanionRequest`) | Mover un familiar entre empleados invalidaría snapshots de casos activos. Si el cambio es necesario, el flujo es eliminar + recrear (auditable vía `activity_log`). |
| D4 | **`tenant_id` y `client_id` de los familiares = los del empleado parent** — asignados por servicio, no por el frontend | Garantiza aislamiento multi-tenant. Permite queries directas sin JOIN al parent para búsquedas globales. |
| D5 | **Reusar `CompanionController`** con query params (`?tier=employees`, `?parent_id=X`) — NO crear `FamilyMemberController` | DRY: CRUD, validación, autorización y observer son idénticos para empleados y familiares. Un controlador separado duplicaría el 95% del código. |
| D6 | **Soft-delete cascade via Observer** (no solo FK DB) para preservar trazabilidad | La FK `CASCADE` solo actúa en hard delete. Para soft delete (el caso normal), el observer propaga `delete()` a todos los familiares del empleado eliminado. |
| D7 | **Accordion expandible por empleado** en `clients/show.vue` (no modal dedicado ni tab separado) | Mantiene la UX de tabs existente. Permite CRUD inline de familiares sin abrir pantallas nuevas. Escalable con lazy load por empleado si hay muchos (>20). |
| D8 | **Filtrado de familiares en wizard en memoria** (no roundtrip extra a la API) | Los companions del cliente ya están cargados en `StepCompanions.vue`. Filtrar por `parent_companion_id` es O(n) sin latencia de red. |
| D9 | **Pertenencia de snapshot no cambia** con reorganización familiar post-creación | `case_companions` guarda la asociación individual por companion_id. El caso queda congelado al momento de su creación. |
| D10 | **Nuevo permiso `companions.manage_family`** para visibilidad del accordion en UI | Granularidad UX: un `apoyo` podría ver el listado de empleados sin poder gestionar su familia. Backend sigue usando `companions.create/update/delete`. |

---

## 1. Base de Datos

### 1.1 Migración — `parent_companion_id` en `companions`

```php
<?php
// database/migrations/2026_05_27_000001_add_parent_companion_id_to_companions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companions', function (Blueprint $table) {
            $table->foreignId('parent_companion_id')
                ->nullable()
                ->after('client_id')
                ->constrained('companions')
                ->cascadeOnDelete();

            // Optimiza "dame la familia del empleado X dentro del tenant Y"
            $table->index(['tenant_id', 'parent_companion_id'], 'idx_companions_tenant_parent');
        });

        // CHECK: si tiene parent, no puede ser beneficiary (familiar no puede ser jefe de otra familia)
        DB::statement("
            ALTER TABLE companions
            ADD CONSTRAINT chk_companions_family_not_beneficiary
            CHECK (
                parent_companion_id IS NULL
                OR relationship <> 'beneficiary'
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companions DROP CHECK chk_companions_family_not_beneficiary');

        Schema::table('companions', function (Blueprint $table) {
            // Patrón G2: drop FK antes que el índice
            $table->dropForeign(['parent_companion_id']);
            $table->dropIndex('idx_companions_tenant_parent');
            $table->dropColumn('parent_companion_id');
        });
    }
};
```

> **Nota:** No se requiere migración de datos. Los companions existentes tendrán `parent_companion_id = NULL`, que es semánticamente correcto: son employees sin familia registrada aún o companions de casos de persona.

### 1.2 Migración — Permiso `companions.manage_family`

```php
<?php
// database/migrations/2026_05_27_000002_add_companion_family_permission.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'companions.manage_family']);

        // super-admin y admin: acceso total
        foreach (['super-admin', 'admin'] as $roleName) {
            Role::findByName($roleName)?->givePermissionTo($perm);
        }

        // consultor y apoyo: pueden gestionar familia (se puede ajustar por tenant)
        foreach (['consultor', 'apoyo'] as $roleName) {
            Role::findByName($roleName)?->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        Permission::findByName('companions.manage_family')?->delete();
    }
};
```

---

## 2. Backend

### 2.1 Modelo `Companion`

Agregar en `app/Models/Companion.php`:

```php
// En $fillable — añadir:
'parent_companion_id',

// Relaciones nuevas:

/** El empleado (beneficiary) del que depende este familiar. NULL si es empleado raíz. */
public function parent(): BelongsTo
{
    return $this->belongsTo(Companion::class, 'parent_companion_id');
}

/** Familiares directos de este empleado. Vacío para no-beneficiarios. */
public function familyMembers(): HasMany
{
    return $this->hasMany(Companion::class, 'parent_companion_id');
}

// Scopes nuevos:

/** Empleados raíz: beneficiaries sin parent. */
public function scopeEmployees(Builder $query): Builder
{
    return $query->whereNull('parent_companion_id')
                 ->where('relationship', self::RELATIONSHIP_BENEFICIARY);
}

/** Familiares directos de un empleado dado. */
public function scopeFamilyOf(Builder $query, int $employeeId): Builder
{
    return $query->where('parent_companion_id', $employeeId);
}

/** Raíces: companions sin parent (empleados + companions de casos persona). */
public function scopeRoots(Builder $query): Builder
{
    return $query->whereNull('parent_companion_id');
}

// Helpers:

public function isFamilyMember(): bool
{
    return $this->parent_companion_id !== null;
}

public function canHaveFamily(): bool
{
    return $this->relationship === self::RELATIONSHIP_BENEFICIARY
        && $this->parent_companion_id === null;
}
```

### 2.2 `CompanionObserver`

```php
// app/Observers/CompanionObserver.php

public function deleting(Companion $companion): void
{
    if ($companion->isForceDeleting()) return;

    // Cascade soft-delete a familiares del empleado eliminado
    if ($companion->canHaveFamily()) {
        $companion->familyMembers()->each(fn($f) => $f->delete());
    }

    // Comportamiento existente — documentos legales
    $companion->legalDocuments()->each(fn($doc) => $doc->delete());
}

public function restoring(Companion $companion): void
{
    // Restaurar familiares soft-deleted en el mismo evento de borrado
    if ($companion->canHaveFamily()) {
        $companion->familyMembers()
            ->onlyTrashed()
            ->where('deleted_at', '>=', $companion->deleted_at)
            ->each(fn($f) => $f->restore());
    }
}
```

### 2.3 `CompanionService`

Agregar `createFamilyMember()` y `listFamilyMembers()`:

```php
// app/Services/Companion/CompanionService.php

/**
 * Crea un familiar bajo un empleado (beneficiary). Valida profundidad máx. 1.
 */
public function createFamilyMember(Companion $employee, array $data): Companion
{
    // Invariantes de dominio (no cubiertas por CHECK constraint)
    abort_if(
        $employee->relationship !== Companion::RELATIONSHIP_BENEFICIARY,
        422,
        __('companions.errors.parent_must_be_beneficiary')
    );
    abort_if(
        $employee->parent_companion_id !== null,
        422,
        __('companions.errors.max_depth_exceeded')
    );

    $data['parent_companion_id'] = $employee->id;
    $data['client_id']           = $employee->client_id;
    $data['tenant_id']           = $employee->tenant_id;

    return $this->createCompanion($data); // lógica existente
}

/**
 * Lista familiares de un empleado con soft-delete filter.
 */
public function listFamilyMembers(Companion $employee): Collection
{
    return $employee->familyMembers()->orderBy('first_name')->get();
}
```

### 2.4 `StoreCompanionRequest`

```php
// app/Http/Requests/Companion/StoreCompanionRequest.php

// Añadir a rules():
'parent_companion_id' => [
    'nullable',
    'integer',
    Rule::exists('companions', 'id')
        ->where('tenant_id', tenant()->id)
        ->whereNull('deleted_at'),
],

// Actualizar regla de 'relationship':
'relationship' => [
    'required',
    Rule::in(array_keys(Companion::RELATIONSHIP_TYPES)),
    function ($attribute, $value, $fail) {
        if ($this->input('parent_companion_id') && $value === 'beneficiary') {
            $fail(__('companions.errors.family_cannot_be_beneficiary'));
        }
    },
],

// Añadir withValidator() para validar que el parent sea un empleado raíz válido:
public function withValidator($validator): void
{
    $validator->after(function ($v) {
        $parentId = $this->input('parent_companion_id');
        if (!$parentId) return;

        $parent = Companion::find($parentId);
        if (!$parent) return;

        if ($parent->relationship !== Companion::RELATIONSHIP_BENEFICIARY) {
            $v->errors()->add('parent_companion_id',
                __('companions.errors.parent_must_be_beneficiary'));
        }
        if ($parent->parent_companion_id !== null) {
            $v->errors()->add('parent_companion_id',
                __('companions.errors.max_depth_exceeded'));
        }
    });
}
```

### 2.5 `UpdateCompanionRequest`

```php
// app/Http/Requests/Companion/UpdateCompanionRequest.php

// parent_companion_id es INMUTABLE — silenciar si el frontend lo envía:
protected function passedValidation(): void
{
    $this->offsetUnset('parent_companion_id');
}
```

### 2.6 `CompanionResource`

```php
// app/Http/Resources/CompanionResource.php

// Añadir al array de retorno:
'parent_companion_id' => $this->parent_companion_id,
'is_employee'         => $this->canHaveFamily(),
'is_family_member'    => $this->isFamilyMember(),
'family_count'        => $this->when(isset($this->family_count), $this->family_count),
'family_members'      => CompanionResource::collection($this->whenLoaded('familyMembers')),
```

### 2.7 `CompanionController`

```php
// app/Http/Controllers/Api/CompanionController.php

/**
 * GET /api/companions/{companion}/family
 * Lista los familiares de un empleado (beneficiary). 404 si no es beneficiary raíz.
 */
public function family(Companion $companion): AnonymousResourceCollection
{
    $this->authorize('view', $companion);
    abort_unless($companion->canHaveFamily(), 404);

    $family = $this->companionService->listFamilyMembers($companion);

    return CompanionResource::collection($family);
}

/**
 * GET /api/clients/{client}/companions
 * Acepta query params adicionales:
 *   ?tier=employees        → solo beneficiaries raíz
 *   ?tier=all (default)    → todos (backward compatible)
 *   ?parent_id={id}        → familiares del empleado dado
 *   ?with_family=true      → eager load familyMembers (para accordion)
 *   ?with_family_count=true → añade family_count a cada empleado
 */
public function index(Client $client, Request $request): AnonymousResourceCollection
{
    $this->authorize('viewAny', [Companion::class, $client]);

    $companions = $this->companionService->listCompanions($client, $request->only([
        'tier', 'parent_id', 'with_family', 'with_family_count',
    ]));

    return CompanionResource::collection($companions);
}
```

### 2.8 Ruta nueva en `api.php`

```php
// routes/api.php — dentro del grupo auth:sanctum + tenant:
Route::get('companions/{companion}/family',
    [CompanionController::class, 'family'])
    ->name('companions.family');
```

---

## 3. Frontend

### 3.1 TypeScript — Actualizar tipo `Companion`

```typescript
// resources/js/src/types/index.ts (o donde esté definido Companion)

export interface Companion {
  id: number;
  client_id: number;
  parent_companion_id: number | null;   // ← NUEVO
  relationship: string;
  first_name: string;
  last_name: string;
  // ...campos existentes
  is_employee: boolean;                  // ← NUEVO
  is_family_member: boolean;             // ← NUEVO
  family_count?: number;                 // ← NUEVO (opcional, cuando se pide)
  family_members?: Companion[];          // ← NUEVO (opcional, cuando se eager-load)
}
```

### 3.2 `companionService.ts`

```typescript
// resources/js/src/services/companionService.ts

// Actualizar list() para aceptar nuevos params:
list(clientId: number, params?: {
  tier?: 'employees' | 'all';
  parent_id?: number | 'null';
  with_family?: boolean;
  with_family_count?: boolean;
}) {
  return api.get<CompanionResource[]>(`/clients/${clientId}/companions`, { params });
}

// Nuevo helper:
getFamilyMembers(employeeId: number) {
  return api.get<CompanionResource[]>(`/companions/${employeeId}/family`);
}
```

### 3.3 `CompanionFormModal.vue`

```typescript
// Añadir prop:
const props = defineProps<{
  companion?: Companion | null;
  clientId: number;
  presetRelationship?: string;
  lockedRelationship?: boolean;
  parentCompanionId?: number | null;   // ← NUEVO: si se pasa, es creación de familiar
}>();

// En el submit, incluir en el payload:
const payload = {
  ...form,
  parent_companion_id: props.parentCompanionId ?? null,
};
```

### 3.4 `clients/show.vue` — Pestaña Companions para Empresa

Reemplazar el render plano de companions por accordion jerárquico cuando `client.type === 'company'`:

```vue
<!-- Solo para company: accordion de empleados -->
<div v-if="activeTab === 'companions' && client.type === 'company'">
  <div class="flex justify-between items-center mb-4">
    <h5 class="font-semibold text-lg">{{ $t('clients.sponsored_employees') }}</h5>
    <button v-can="'companions.create'" class="btn btn-primary btn-sm"
            @click="openEmployeeModal()">
      + {{ $t('clients.add_employee') }}
    </button>
  </div>

  <!-- Card por empleado con accordion -->
  <div v-for="employee in employees" :key="employee.id"
       class="mb-3 border rounded-lg overflow-hidden">

    <!-- Header empleado -->
    <button class="w-full flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-800"
            @click="toggleEmployee(employee.id)">
      <div class="w-10 h-10 rounded-full bg-success/20 text-success flex items-center justify-center font-semibold">
        {{ employee.initials }}
      </div>
      <div class="flex-1 text-left">
        <p class="font-semibold">{{ employee.full_name }}</p>
        <p class="text-xs text-gray-500">{{ $t('companions.beneficiary_employee') }}</p>
      </div>
      <span class="badge badge-outline-primary text-xs">
        {{ employee.family_count ?? 0 }} {{ $t('clients.family_members') }}
      </span>
      <button v-can="'companions.update'" @click.stop="openEmployeeModal(employee)">
        <icon-pencil class="w-4 h-4 text-gray-400 hover:text-primary" />
      </button>
      <button v-can="'companions.delete'" @click.stop="confirmDeleteEmployee(employee)">
        <icon-trash class="w-4 h-4 text-gray-400 hover:text-danger" />
      </button>
      <icon-chevron-down class="w-5 h-5 transition-transform"
                        :class="{ 'rotate-180': expandedEmployees.has(employee.id) }" />
    </button>

    <!-- Panel familia (expandible, carga lazy al primer expand) -->
    <div v-show="expandedEmployees.has(employee.id)"
         class="border-t bg-gray-50 dark:bg-gray-800/40 p-4">
      <div class="flex justify-between items-center mb-3">
        <h6 class="font-medium text-sm">
          {{ $t('clients.family_of', { name: employee.first_name }) }}
        </h6>
        <button v-can="'companions.create'"
                class="btn btn-outline-primary btn-xs gap-1"
                @click="openFamilyMemberModal(employee)">
          + {{ $t('clients.add_family_member') }}
        </button>
      </div>

      <p v-if="!employee.family_members?.length"
         class="text-sm text-gray-500 text-center py-4">
        {{ $t('clients.no_family_yet') }}
      </p>

      <div v-else class="grid sm:grid-cols-2 gap-2">
        <div v-for="fm in employee.family_members" :key="fm.id"
             class="flex items-center gap-2 p-2 bg-white dark:bg-gray-900 rounded border">
          <div class="w-8 h-8 rounded-full bg-secondary/20 text-secondary flex items-center justify-center text-xs font-semibold">
            {{ fm.initials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ fm.full_name }}</p>
            <p class="text-xs text-gray-500">{{ formatRelationship(fm.relationship) }}</p>
          </div>
          <button v-can="'companions.update'" @click="openFamilyMemberModal(employee, fm)">
            <icon-pencil class="w-3 h-3 text-gray-400 hover:text-primary" />
          </button>
          <button v-can="'companions.delete'" @click="confirmDeleteFamilyMember(fm)">
            <icon-trash class="w-3 h-3 text-gray-400 hover:text-danger" />
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Para clientes persona: render plano actual (sin cambios) -->
<div v-else-if="activeTab === 'companions'">
  <!-- ...código existente sin modificar... -->
</div>
```

**Lógica de datos en `clients/show.vue`:**

```typescript
const employees = ref<Companion[]>([]);
const expandedEmployees = ref(new Set<number>());

async function loadEmployees() {
  // 2 queries en backend: empleados + sus familyMembers (eager load)
  const res = await companionService.list(clientId, {
    tier: 'employees',
    with_family: true,
    with_family_count: true,
  });
  employees.value = res.data;
}

async function toggleEmployee(id: number) {
  if (expandedEmployees.value.has(id)) {
    expandedEmployees.value.delete(id);
  } else {
    expandedEmployees.value.add(id);
  }
}

function openFamilyMemberModal(employee: Companion, familyMember?: Companion) {
  // Abre CompanionFormModal con parentCompanionId = employee.id
  // y presetRelationship bloqueado solo si es 'beneficiary' (no aplica para familiares)
  openCompanionModal(familyMember ?? null, {
    parentCompanionId: employee.id,
  });
}
```

### 3.5 `StepCompanions.vue` — Wizard Step 3

```typescript
// Computed: companions elegibles según tipo de caso y empleado seleccionado
const displayableCompanions = computed<Companion[]>(() => {
  if (!isCompanyCase.value) {
    // Persona: todos los companions que no son beneficiary (comportamiento actual)
    return companions.value.filter(c => c.relationship !== 'beneficiary');
  }
  if (!wizard.state.primaryApplicantCompanionId) {
    return []; // No se ha seleccionado empleado aún
  }
  // Empresa: solo familiares del empleado seleccionado
  return companions.value.filter(
    c => c.parent_companion_id === wizard.state.primaryApplicantCompanionId
  );
});

// Limpiar selección de familiares al cambiar de empleado
watch(() => wizard.state.primaryApplicantCompanionId, (newId, oldId) => {
  if (!isCompanyCase.value || newId === oldId) return;

  // Remover del caso los familiares del empleado anterior
  if (oldId !== null) {
    const staleIds = companions.value
      .filter(c => c.parent_companion_id === oldId)
      .map(c => c.id);
    wizard.state.selectedCompanionIds = wizard.state.selectedCompanionIds
      .filter(id => !staleIds.includes(id));
  }
  wizard.saveToSession();
});
```

**Estado vacío (empleado sin familia registrada):**

```vue
<div v-if="isCompanyCase && wizard.state.primaryApplicantCompanionId && !displayableCompanions.length"
     class="text-center py-8 text-gray-500">
  <icon-users class="w-12 h-12 mx-auto mb-3 opacity-30" />
  <p class="font-medium">{{ $t('wizard.step_companions.no_family_yet') }}</p>
  <p class="text-sm mt-1">{{ $t('wizard.step_companions.go_to_client_to_add_family') }}</p>
</div>
```

### 3.6 `cases/show.vue` — Pestaña Companions

```vue
<!-- Para empresa: mostrar empleado como header + sus familiares incluidos en el caso -->
<template v-if="caseData.client?.type === 'company' && primaryEmployee">

  <!-- Header del empleado aplicante -->
  <div class="mb-4 p-3 bg-success/5 border border-success/20 rounded-lg flex items-center gap-3">
    <div class="w-10 h-10 rounded-full bg-success/20 text-success flex items-center justify-center font-semibold">
      {{ primaryEmployee.initials }}
    </div>
    <div class="flex-1">
      <p class="font-semibold">{{ primaryEmployee.full_name }}</p>
      <span class="badge badge-outline-success text-xs">
        {{ $t('cases.beneficiary_employee') }}
      </span>
    </div>
  </div>

  <!-- Familiares incluidos en este expediente específico -->
  <h6 class="font-semibold mb-2 text-sm">{{ $t('cases.included_family') }}</h6>
  <p v-if="!includedFamily.length" class="text-sm text-gray-500 py-3">
    {{ $t('cases.no_family_included') }}
  </p>
  <div v-else class="space-y-2">
    <div v-for="fm in includedFamily" :key="fm.id" class="...existing companion card...">
      <!-- reusar el card existente -->
    </div>
  </div>
</template>

<!-- Persona: render plano actual sin cambios -->
<template v-else>
  <!-- ...código existente... -->
</template>
```

```typescript
const primaryEmployee = computed(() =>
  caseData.value?.companions.find(
    c => c.id === caseData.value?.primary_applicant_companion_id
  )
);
const includedFamily = computed(() =>
  caseData.value?.companions.filter(
    c => c.parent_companion_id === caseData.value?.primary_applicant_companion_id
  ) ?? []
);
```

### 3.7 `cases/edit.vue` — Selector de Companions

```typescript
async function loadAddableCompanions() {
  const includedIds = new Set(caseData.value.companions.map(c => c.id));

  if (caseData.value.client?.type === 'company') {
    const employeeId = caseData.value.primary_applicant_companion_id;
    if (!employeeId) return;

    // Solo familiares del empleado del caso que no estén ya incluidos
    const res = await companionService.getFamilyMembers(employeeId);
    addableCompanions.value = res.data.filter(c => !includedIds.has(c.id));
  } else {
    // Persona: companions raíz del cliente no incluidos (comportamiento actual)
    const res = await companionService.list(caseData.value.client_id, { tier: 'all' });
    addableCompanions.value = res.data.filter(c => !includedIds.has(c.id));
  }
}
```

### 3.8 Nuevas claves i18n (FLAT — agregar en es.json, en.json, fr.json)

```json
{
  "clients.sponsored_employees": "Empleados Patrocinados",
  "clients.add_employee": "Agregar empleado",
  "clients.add_family_member": "Agregar familiar",
  "clients.family_members": "familiares",
  "clients.family_of": "Familia de {name}",
  "clients.no_family_yet": "Este empleado aún no tiene familiares registrados",
  "companions.beneficiary_employee": "Empleado Beneficiario",
  "companions.errors.family_cannot_be_beneficiary": "Un familiar no puede ser empleado beneficiario",
  "companions.errors.parent_must_be_beneficiary": "El acompañante padre debe ser un empleado beneficiario",
  "companions.errors.max_depth_exceeded": "No se permiten más de un nivel de jerarquía familiar",
  "wizard.step_companions.no_family_yet": "El empleado seleccionado no tiene familiares registrados",
  "wizard.step_companions.go_to_client_to_add_family": "Ve al perfil del cliente para agregar familiares a este empleado",
  "cases.beneficiary_employee": "Empleado Aplicante",
  "cases.included_family": "Familiares incluidos en este expediente",
  "cases.no_family_included": "No se agregaron familiares a este expediente"
}
```

---

## 4. Plan de Implementación por Fases

> Marcar cada tarea con `[x]` al completarla. Las fases son secuenciales; dentro de cada fase las tareas pueden ejecutarse en paralelo salvo que se indique dependencia explícita.  
> `XS` ≈ 15 min · `S` ≈ 1 h · `M` ≈ 2–4 h · **Estimación total: ~3–4 días**

---

### Fase 1 — Base de Datos

- [x] **F1.1** `[S]` Crear migración `2026_05_27_000001_add_parent_companion_id_to_companions.php`
  - Columna `parent_companion_id BIGINT UNSIGNED NULL` después de `client_id`
  - FK `companions(id)` con `ON DELETE CASCADE`
  - Índice compuesto `idx_companions_tenant_parent (tenant_id, parent_companion_id)`
  - CHECK constraint `chk_companions_family_not_beneficiary` (ver §1.1)
  - Ejecutar `php artisan migrate` y verificar con `DESCRIBE companions`

- [x] **F1.2** `[XS]` Crear migración `2026_05_27_000002_add_companion_family_permission.php`
  - `Permission::firstOrCreate(['name' => 'companions.manage_family'])`
  - Asignar a: `super-admin`, `admin`, `consultor`, `apoyo`
  - Verificar que **no** se usa seeder (regla D-03 / G4)

---

### Fase 2 — Modelo y Capa de Dominio (Backend)

> Depende de: Fase 1

- [x] **F2.1** `[S]` Actualizar `app/Models/Companion.php`
  - Añadir `'parent_companion_id'` a `$fillable`
  - Relación `parent(): BelongsTo`
  - Relación `familyMembers(): HasMany`
  - Scope `scopeEmployees(Builder $query)`
  - Scope `scopeFamilyOf(Builder $query, int $employeeId)`
  - Scope `scopeRoots(Builder $query)`
  - Helpers `isFamilyMember(): bool` y `canHaveFamily(): bool`

- [x] **F2.2** `[M]` Actualizar `app/Services/Companion/CompanionService.php`
  - Método `createFamilyMember(Companion $employee, array $data): Companion`
    - Validar que `$employee->relationship === 'beneficiary'`
    - Validar que `$employee->parent_companion_id === null` (profundidad 1)
    - Propagar `client_id` y `tenant_id` del parent
  - Método `listFamilyMembers(Companion $employee): Collection`
  - Actualizar `listCompanions()` para aceptar params: `tier`, `parent_id`, `with_family`, `with_family_count`
  - Depende de: F2.1

- [x] **F2.3** `[S]` Actualizar `app/Observers/CompanionObserver.php`
  - En `deleting()`: si `$companion->canHaveFamily()` → cascade `$companion->familyMembers()->each(fn($f) => $f->delete())`
  - En `restoring()`: restaurar familiares soft-deleted en el mismo evento
  - Depende de: F2.1

- [x] **F2.4** `[S]` Actualizar `app/Http/Requests/Companion/StoreCompanionRequest.php`
  - Regla `parent_companion_id`: nullable, integer, `Rule::exists` scoped a `tenant_id`
  - Regla `relationship`: añadir closure que rechace `'beneficiary'` si `parent_companion_id` presente
  - Método `withValidator()`: validar que el parent sea `beneficiary` raíz (no tiene a su vez `parent_companion_id`)
  - Depende de: F2.1

- [x] **F2.5** `[XS]` Actualizar `app/Http/Requests/Companion/UpdateCompanionRequest.php`
  - Añadir `passedValidation()` que hace `$this->offsetUnset('parent_companion_id')`
  - Verificar que ningún otro campo se vuelve inmutable accidentalmente

- [x] **F2.6** `[XS]` Actualizar `app/Http/Resources/CompanionResource.php`
  - Añadir: `'parent_companion_id'`, `'is_employee'`, `'is_family_member'`
  - Condicional: `'family_count'` (con `$this->when(...)`) y `'family_members'` (con `$this->whenLoaded(...)`)

---

### Fase 3 — API HTTP (Backend)

> Depende de: Fase 2

- [x] **F3.1** `[S]` Actualizar `app/Http/Controllers/Api/CompanionController.php`
  - Nuevo método `family(Companion $companion)`: authorize + `abort_unless($companion->canHaveFamily(), 404)` + retornar `CompanionResource::collection`
  - Actualizar `index()`: pasar query params a `companionService->listCompanions()`

- [x] **F3.2** `[S]` Actualizar `listCompanions()` en `CompanionService` para los nuevos query params
  - `?tier=employees` → `scopeEmployees()`
  - `?parent_id={id}` → `scopeFamilyOf($id)`
  - `?with_family=true` → `with('familyMembers')`
  - `?with_family_count=true` → `withCount('familyMembers as family_count')`
  - `?tier=all` (default) → comportamiento actual sin filtro (backward compatible)
  - Depende de: F3.1

- [x] **F3.3** `[XS]` Añadir ruta en `routes/api.php`
  - `Route::get('companions/{companion}/family', [CompanionController::class, 'family'])->name('companions.family')`
  - Ubicar dentro del grupo `auth:sanctum + tenant`
  - Verificar con `php artisan route:list | grep family`

---

### Fase 4 — Capa de Servicios Frontend

> Depende de: Fase 3

- [x] **F4.1** `[XS]` Actualizar `resources/js/src/services/companionService.ts`
  - Actualizar firma de `list(clientId, params?)` con los nuevos query params tipados
  - Añadir `getFamilyMembers(employeeId: number)` → `GET /companions/{id}/family`

- [x] **F4.2** `[XS]` Actualizar tipo `Companion` en types/interfaces del proyecto
  - Añadir campos opcionales: `parent_companion_id: number | null`, `is_employee: boolean`, `is_family_member: boolean`, `family_count?: number`, `family_members?: Companion[]`

- [x] **F4.3** `[S]` Actualizar `resources/js/src/components/companions/CompanionFormModal.vue`
  - Añadir prop `parentCompanionId?: number | null`
  - Incluir `parent_companion_id: props.parentCompanionId ?? null` en el payload del POST
  - Verificar que la prop no afecta el flujo de edición (solo aplica en creación)
  - Depende de: F4.1, F4.2

---

### Fase 5 — UI: Perfil Corporativo (`clients/show.vue`)

> Depende de: Fase 4

- [x] **F5.1** `[M]` Refactorizar pestaña Companions en `resources/js/src/views/clients/show.vue`
  - Añadir rama condicional `v-if="client.type === 'company'"` con accordion de empleados
  - Mantener el render plano actual en el `v-else` (sin tocar código de clientes persona)
  - Implementar `loadEmployees()` usando `companionService.list({tier:'employees', with_family:true, with_family_count:true})`
  - Implementar `expandedEmployees: Set<number>` + `toggleEmployee(id)`
  - Implementar `openFamilyMemberModal(employee, familyMember?)` que pasa `parentCompanionId`
  - Implementar `confirmDeleteEmployee(employee)` con mensaje que advierte sobre la eliminación de familiares en cascada

- [x] **F5.2** `[S]` Añadir 18 claves i18n en `es.json`, `en.json`, `fr.json` (FLAT)
  - Ver §3.8 para la lista completa
  - Verificar que ninguna clave contiene `<` o `>` crudos (G10)
  - Build Vite sin errores: `npm run build`

---

### Fase 6 — UI: Wizard Step 3 (`StepCompanions.vue`)

> Depende de: Fase 4

- [x] **F6.1** `[M]` Actualizar `resources/js/src/views/cases/wizard/steps/StepCompanions.vue`
  - Añadir computed `displayableCompanions` que reemplaza el filtro actual por `beneficiary`
    - Si `isCompanyCase && primaryApplicantCompanionId`: filtra por `parent_companion_id === primaryApplicantCompanionId`
    - Si persona: filtra companions que no son `beneficiary` (comportamiento actual)
  - Añadir `watch` sobre `primaryApplicantCompanionId` para limpiar `selectedCompanionIds` al cambiar de empleado
  - Verificar que el watcher no dispara en la carga inicial (usar `{ immediate: false }`)

- [x] **F6.2** `[XS]` Añadir estado vacío en `StepCompanions.vue`
  - Mostrar cuando `isCompanyCase && primaryApplicantCompanionId && !displayableCompanions.length`
  - Mensaje con instrucción para ir al perfil del cliente a agregar familiares

---

### Fase 7 — UI: Vista de Expediente (`cases/show.vue`)

> Depende de: Fase 4

- [x] **F7.1** `[S]` Actualizar pestaña Companions en `resources/js/src/views/cases/show.vue`
  - Añadir rama `v-if="caseData.client?.type === 'company' && primaryEmployee"`
  - Computed `primaryEmployee`: busca el companion con `id === primary_applicant_companion_id`
  - Computed `includedFamily`: filtra `case.companions` donde `parent_companion_id === primaryEmployee.id`
  - Mantener render plano actual en `v-else` (casos persona sin cambios)

---

### Fase 8 — UI: Edición de Expediente (`cases/edit.vue`)

> Depende de: Fase 4

- [x] **F8.1** `[S]` Actualizar selector de acompañantes en `resources/js/src/views/cases/edit.vue`
  - Actualizar `loadAddableCompanions()`:
    - Si empresa: `companionService.getFamilyMembers(primary_applicant_companion_id)` → filtrar ya incluidos
    - Si persona: comportamiento actual (companions raíz del cliente no incluidos)
  - Verificar que el botón "Agregar companion" usa los nuevos `addableCompanions`

---

### Fase 9 — Pruebas y Cierre

> Depende de: Fases 1–8

- [ ] **F9.1** `[M]` Tests Feature PHP: `tests/Feature/CompanionHierarchyTest.php`
  - Crear empleado + familiares → verificar `parent_companion_id`
  - Rechazar familiar con `relationship='beneficiary'`
  - Rechazar profundidad > 1 (familiar de familiar)
  - Rechazar `parent_companion_id` de otro tenant (cross-tenant)
  - Verificar inmutabilidad en UPDATE
  - Endpoint `GET /companions/{id}/family` → 404 si no es beneficiary raíz

- [ ] **F9.2** `[S]` Tests Feature PHP: cascade soft-delete/restore
  - Soft-delete de empleado → familiares quedan soft-deleted
  - Restore de empleado → familiares restaurados
  - `case_companions` consistente en ambos escenarios

- [ ] **F9.3** `[M]` Smoke test manual E2E (pendiente)
  - Crear empresa → agregar empleado → agregar 2 familiares
  - Crear expediente corporativo → Step 3 solo muestra familiares del empleado seleccionado
  - Cambiar de empleado en Step 2 → lista de familiares se actualiza y selecciones se limpian
  - `cases/show.vue` → pestaña Companions muestra jerarquía correcta
  - `cases/edit.vue` → selector limitado a familiares del empleado del caso
  - Eliminar empleado → confirmar que familiares desaparecen del perfil y los casos quedan intactos

- [x] **F9.4** `[XS]` Actualizar documentación del proyecto
  - `spec/INDEX.md`: cambiar estado Spec 69 de `PEND` → `DONE`
  - `MEMORY.md`: añadir entrada en "Implementaciones Recientes"
  - Actualizar `spec/64_clientes_empresa_patrocinador_beneficiario.md` si algún pendiente de DT-09 queda cubierto

---

## 5. Riesgos y Mitigaciones

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|-------------|---------|------------|
| R1 | Companions existentes tipo `beneficiary` pasan a ser "empleados sin familia" — semánticamente correcto pero el UI accordion puede mostrarlos con `family_count = 0` | Alta | Bajo | Es el comportamiento correcto. El accordion muestra "Sin familiares registrados" y el botón para agregar. No requiere migración de datos. |
| R2 | `parent_companion_id` mutable post-creación rompería snapshots de casos activos | Media | Alto | Campo inmutable en `UpdateCompanionRequest::passedValidation()`. Flujo para mover: eliminar + recrear (auditable). |
| R3 | Hard-delete del empleado en DB dispara CASCADE sin pasar por observer → familiares eliminados sin audit | Media | Medio | `case_companions.companion_id CASCADE` ya limpia el pivote. El observer maneja el soft-delete (el flujo normal). Hard-delete debe ser explícito y requiere permiso elevado. |
| R4 | CHECK constraint requiere MySQL 8.0.16+ | Baja | Alto | El proyecto declara MySQL 8.0 como requisito (CLAUDE.md). Si hay deployments legacy, mover la invariante 100% al servicio y omitir el CHECK. |
| R5 | Paginación del accordion si una empresa tiene >20 empleados | Baja | Medio | El endpoint `?tier=employees` soporta paginación estándar Laravel. El accordion puede lazy-load los familiares solo al primer expand (se evita payload masivo). |
| R6 | Al cambiar `primaryApplicantCompanionId` en el wizard, se pierden selecciones del empleado anterior | Alta | Bajo | Comportamiento intencional y correcto (las familias no son intercambiables). Toast informativo: "Lista de familiares actualizada para [nombre]". |
| R7 | Wizard muestra "Sin familiares" pero el usuario no sabe cómo navegar al perfil del cliente | Media | Bajo | Empty state incluye mensaje con link/instrucción. Si la app lo permite, botón que abre el perfil del cliente en nueva pestaña. |
| R8 | Tests existentes de `CompanionController::index` fallan si se añaden query params sin defaults | Media | Bajo | `tier='all'` como default → response idéntica. Tests legacy siguen verdes sin modificación. |
| R9 | `with_family=true` en listado con muchos empleados genera N+1 si el eager load falla | Baja | Bajo | `with('familyMembers')` es una sola query SQL adicional (no N+1). Laravel eager load resuelve en 2 queries totales. |
| R10 | Permiso `companions.manage_family` no asignado a roles existentes → UI bloqueada en producción | Alta si se olvida | Medio | La **misma migración** F1.2 asigna el permiso a super-admin, admin, consultor, apoyo. Patrón G4 obligatorio. |

---

## 6. Casos de Borde Documentados

### CB-1 — Cambio de empresa del empleado
**Escenario:** Un companion `beneficiary` del `client_id=1` necesita moverse al `client_id=2`.  
**Regla:** El `client_id` de un companion es **inmutable** (igual que `parent_companion_id`). Si el empleado cambia de empresa patrocinadora, el flujo es: (1) soft-delete del companion en empresa original, (2) crear nuevo companion en empresa destino.  
**Efecto en casos pasados:** Los expedientes anteriores mantienen el snapshot vía `case_companions` — no se ven afectados. El caso queda congelado con la estructura original.

### CB-2 — Eliminación de empresa con empleados con expedientes activos
**Escenario:** Se elimina (soft-delete) el cliente empresa.  
**Regla:** `ClientObserver` ya hace cascade soft-delete a todos sus companions. El observer actualizado propaga el borrado a sus familiares. Los casos quedan en estado `inactive` con sus snapshots intactos.

### CB-3 — Familiar sin datos completos
**Regla:** Los familiares requieren `first_name`, `last_name`, `relationship` (distinto de `beneficiary`). No requieren `passport_number`, `date_of_birth` ni `nationality` (campos opcionales). `StoreCompanionRequest` ya maneja esto con `nullable` en esos campos.

### CB-4 — Empleado seleccionado como aplicante principal que no tiene familiares
**Escenario:** Se selecciona un beneficiary como aplicante en Step 2 y en Step 3 no hay familiares disponibles.  
**Comportamiento esperado:** El wizard muestra el estado vacío con mensaje informativo. El caso puede crearse sin familiares adicionales (solo el empleado como aplicante principal). Es un caso válido.

### CB-5 — Editar un expediente y cambiar el empleado aplicante principal
**Escenario:** En `cases/edit.vue`, el consultor cambia `primary_applicant_companion_id` a otro empleado.  
**Regla:** Los companions (familiares) ya asociados al caso **no se eliminan automáticamente**. El consultor debe revisar y ajustar manualmente qué familiares corresponden al nuevo empleado. Mostrar alerta: "Cambió el empleado principal. Revisa los acompañantes del expediente."

---

## Archivos Impactados

### Nuevos
- `database/migrations/2026_05_27_000001_add_parent_companion_id_to_companions.php`
- `database/migrations/2026_05_27_000002_add_companion_family_permission.php`

### Modificados — Backend
- `app/Models/Companion.php` — fillable, relaciones, scopes, helpers
- `app/Observers/CompanionObserver.php` — cascade soft-delete/restore a familiares
- `app/Services/Companion/CompanionService.php` — `createFamilyMember()`, `listFamilyMembers()`, `listCompanions()` con params
- `app/Http/Controllers/Api/CompanionController.php` — `family()`, `index()` con query params
- `app/Http/Requests/Companion/StoreCompanionRequest.php` — reglas `parent_companion_id`
- `app/Http/Requests/Companion/UpdateCompanionRequest.php` — inmutabilidad
- `app/Http/Resources/CompanionResource.php` — campos nuevos
- `routes/api.php` — ruta `companions/{companion}/family`

### Modificados — Frontend
- `resources/js/src/services/companionService.ts`
- `resources/js/src/components/companions/CompanionFormModal.vue`
- `resources/js/src/views/clients/show.vue`
- `resources/js/src/views/cases/wizard/steps/StepCompanions.vue`
- `resources/js/src/views/cases/show.vue`
- `resources/js/src/views/cases/edit.vue`
- `resources/js/src/locales/es.json`, `en.json`, `fr.json`

### Sin cambios requeridos
- `app/Models/ImmigrationCase.php` — `primary_applicant_companion_id` ya existe (Spec 58)
- `database/migrations/*_create_business_tables.php` — `case_companions` pivote compatible
- `app/Services/Document/FolderService.php` — ya usa `primaryApplicant()` vía Spec 58
- `app/Services/Search/GlobalSearchService.php` — ya usa `display_name` vía Spec 64
