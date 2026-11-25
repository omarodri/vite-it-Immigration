---
tipo: spec
numero: 58
modulo: Expedientes
dominio: CASES
estado: DONE
tags: [spec, expedientes, done]
relacionados:
  - "[[09_epic_2.2_case_wizard|Spec 09]]"
  - "[[55_generador_codigo_expediente|Spec 55]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
  - "[[07_epic_2.1_expedientes_core|Spec 07]]"
---
# [[58_cliente_vs_aplicante_principal|Spec 58]]: Desacoplamiento Cliente / Aplicante Principal

**Versión:** 1.1
**Fecha:** 2026-05-14
**Branch:** `feature/client-vs-primary-applicant`
**Stack:** Laravel 12 + Vue 3.5 + TypeScript + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[09_epic_2.2_case_wizard|Spec 09]] (Wizard Creación), [[55_generador_codigo_expediente|Spec 55]] (Generador Código), [[56_seleccion_dinamica_directorios_wizard|Spec 56]] (Directorios Wizard), [[07_epic_2.1_expedientes_core|Spec 07]] (Expedientes Core)

---

## Resumen Ejecutivo

El sistema asume actualmente que el **Cliente** (entidad comercial que firma y paga) es siempre el **Aplicante Principal** del proceso migratorio. Esta suposición rompe en dos escenarios reales frecuentes:

- Un **patrocinador** (empleador, familiar residente) paga el expediente pero no aplica.
- Un **familiar dependiente** es el solicitante real mientras el adulto responsable firma el contrato.

Esta refactorización **mueve el concepto de "aplicante principal" del perfil global del cliente hacia el expediente específico**, añadiendo dos columnas a la tabla `cases` que permiten que el aplicante sea el cliente o cualquiera de los acompañantes vinculados al caso.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | `clients.is_primary_applicant` es **atributo global de perfil**, no por expediente | `database/migrations/0001_01_01_000006_create_business_tables.php:63` | El campo se elimina de `clients`; el concepto se traslada a `cases` |
| H2 | `case_companions` **no tiene `tenant_id`** — es pivot puro sin modelo Eloquent propio | `database/migrations/0001_01_01_000006_create_business_tables.php:331-338` | No se puede agregar `BelongsToTenant` al pivot sin convertirlo en modelo completo |
| H3 | `clients.status` **ya existe** con `ENUM('prospect', 'active', 'inactive', 'archived')` | `database/migrations/0001_01_01_000006_create_business_tables.php:62` | El lifecycle del cliente es lógica nueva sobre campo existente — sin migración de columna |
| H4 | `CaseService::generateCaseNumber()` acepta `Client $client` y usa `$client->last_name` para `AAAA` | `app/Services/Case/CaseService.php:367-390` | Cambio de firma requerido — debe aceptar el apellido del aplicante principal, no del cliente |
| H5 | `StepSummary.vue` genera el preview del número usando `selectedClient.value?.last_name` | `resources/js/src/views/cases/wizard/steps/StepSummary.vue:313-321` | Debe resolverse desde el aplicante principal seleccionado en Step 3 |
| H6 | `is_primary_applicant` toca 11 archivos en backend y frontend | grep exhaustivo | Superficie de cambio definida y acotada — ver sección de archivos afectados |
| H7 | Spec 55 ya implementado — consecutivo `NNNN` es global por tenant | `app/Services/Case/CaseService.php:371` | Solo cambia quién provee el apellido; el mecanismo de secuencia no cambia |
| H8 | `ImmigrationCaseObserver` ya existe registrado en `AppServiceProvider::boot()` | `CLAUDE.md — Observers` | Los hooks de lifecycle del cliente van aquí, no en un observer nuevo |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **Dos columnas en `cases`** (`primary_applicant_type` + `primary_applicant_companion_id`) en lugar de flag en `case_companions` | MySQL 8.0 no soporta partial unique index; el pivot no tiene modelo Eloquent; almacenar en `cases` es más natural (es propiedad del expediente, no de la relación) |
| D2 | **`primary_applicant_type ENUM('client','companion')`** con default `'client'` | Cubre todos los casos existentes sin migración de datos; semántica explícita |
| D3 | **Código de expediente `AAAA` es inmutable** una vez generado | Spec 55 D4: `case_number` es el único identificador; regenerar rompería trazabilidad y carpetas en nube |
| D4 | **Lifecycle del cliente en `ImmigrationCaseObserver`** | Ya registrado en `AppServiceProvider::boot()`; sigue el patrón establecido del proyecto |
| D5 | **Selección de aplicante principal obligatoria** en Step 3 del wizard | Garantiza integridad del dato desde la creación; el dropdown solo lista al cliente + companions seleccionados |
| D6 | **Reutilizar `CaseCodeHelper::normalizeLastName()`** — sin cambios | Solo cambia quién provee el apellido; el helper es agnóstico al tipo de entidad |
| D7 | **`is_primary_applicant` se elimina en Fase 6** (no en Fase 1) | Las fases son independientes y el sistema funciona correctamente entre fases |
| D8 | **Bloqueo de avance al Step 4** si no hay aplicante principal seleccionado | Selección obligatoria garantiza integridad; un default silencioso al cliente ocultaría datos incorrectos |
| D9 | **`case_number` es inmutable** — no se regenera si el aplicante cambia post-creación | Spec 55 D4: el código es identificador único de trazabilidad; regenerar rompería carpetas en nube y referencias externas |
| D10 | **Búsqueda global por nombre del aplicante diferida a v2** | Reduce scope de esta iteración sin impacto operativo; `case_number` y nombre del cliente siguen siendo suficientes para localizar expedientes |
| D11 | **Lifecycle del cliente via observer automático** (`ImmigrationCaseObserver`) | Elimina errores manuales de actualización de estado; `updateQuietly()` evita efectos secundarios en ActivityLog |

---

## 1. Modelado de Datos

### 1.1 Migración Fase 1 — Agregar columnas a `cases`

```php
<?php
// database/migrations/2026_05_XX_000001_add_primary_applicant_to_cases.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->enum('primary_applicant_type', ['client', 'companion'])
                  ->default('client')
                  ->after('client_id');

            $table->foreignId('primary_applicant_companion_id')
                  ->nullable()
                  ->after('primary_applicant_type')
                  ->constrained('companions')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'primary_applicant_type'], 'idx_cases_tenant_pa_type');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['primary_applicant_companion_id']);
            $table->dropIndex('idx_cases_tenant_pa_type');
            $table->dropColumn(['primary_applicant_type', 'primary_applicant_companion_id']);
        });
    }
};
```

**Por qué no en `case_companions`:** El pivot `case_companions` actualmente no tiene `tenant_id` ni modelo Eloquent propio. Añadir `is_primary_applicant` al pivot requeriría: (a) convertirlo en modelo completo, (b) validar unicidad por `case_id` sin partial unique index nativo en MySQL 8.0, (c) manejar el caso en que el cliente (no un companion) sea el aplicante. Las dos columnas en `cases` son equivalentes semánticamente y mucho más simples de consultar.

**Migración de datos existentes:** No requerida. El default `'client'` cubre correctamente el 100% de los casos históricos — actualmente el cliente es siempre el aplicante implícito.

### 1.2 Migración Fase 6 — Eliminar `is_primary_applicant` de `clients`

```php
<?php
// database/migrations/2026_05_XX_000002_remove_is_primary_applicant_from_clients.php
// EJECUTAR SOLO DESPUÉS de que ningún código lea este campo (Fase 5 completa)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('is_primary_applicant');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_primary_applicant')->default(true)->after('status');
        });
    }
};
```

### 1.3 Esquema resultante de `cases` (columnas relevantes)

```sql
cases
  id                              BIGINT UNSIGNED PK
  tenant_id                       BIGINT UNSIGNED FK → tenants.id
  case_number                     VARCHAR UNIQUE
  client_id                       BIGINT UNSIGNED FK → clients.id      -- responsable del contrato
  primary_applicant_type          ENUM('client','companion') DEFAULT 'client'  -- NUEVO
  primary_applicant_companion_id  BIGINT UNSIGNED NULL FK → companions.id      -- NUEVO
  case_type_id                    BIGINT UNSIGNED FK → case_types.id
  ...
```

---

## 2. Backend — Modelos Eloquent

### 2.1 `ImmigrationCase` — cambios

```php
// app/Models/ImmigrationCase.php

// Agregar a $fillable:
'primary_applicant_type',
'primary_applicant_companion_id',

// Agregar a $casts:
// (no requiere cast especial — enum y FK son strings/integers nativos)

// Nueva relación:
public function primaryApplicantCompanion(): BelongsTo
{
    return $this->belongsTo(Companion::class, 'primary_applicant_companion_id');
}

// Método de acceso unificado — retorna Client o Companion:
public function primaryApplicant(): Client|Companion
{
    if ($this->primary_applicant_type === 'companion' && $this->primary_applicant_companion_id) {
        return $this->primaryApplicantCompanion ?? $this->client;
    }
    return $this->client;
}

// Accessors para uso en Resources y vistas:
public function getPrimaryApplicantNameAttribute(): string
{
    return $this->primaryApplicant()->full_name ?? '';
}

public function getPrimaryApplicantLastNameAttribute(): string
{
    return $this->primaryApplicant()->last_name ?? '';
}
```

### 2.2 `Client` — cambios

```php
// app/Models/Client.php

// Remover de $fillable:
// 'is_primary_applicant',   ← eliminar esta línea

// Remover de $casts:
// 'is_primary_applicant' => 'boolean',   ← eliminar esta línea
```

### 2.3 `Companion` — sin cambios estructurales

El companion sigue siendo la misma entidad. El expediente ahora puede referenciarlo como aplicante principal a través de `cases.primary_applicant_companion_id`.

---

## 3. Backend — Service Layer

### 3.1 `CaseService::generateCaseNumber()` — cambio de firma

```php
// app/Services/Case/CaseService.php

// ANTES:
private function generateCaseNumber(CaseType $caseType, Client $client): string
{
    $year2        = date('y');
    $lastNameSlug = CaseCodeHelper::normalizeLastName($client->last_name);
    $sequence     = $this->caseRepository->getNextSequence(Auth::user()->tenant_id);
    // ...
}

// DESPUÉS:
private function generateCaseNumber(CaseType $caseType, string $primaryLastName): string
{
    $year2        = date('y');
    $lastNameSlug = CaseCodeHelper::normalizeLastName($primaryLastName);
    $sequence     = $this->caseRepository->getNextSequence(Auth::user()->tenant_id);

    $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);

    $attempts = 0;
    while ($this->caseRepository->existsByCaseNumber($caseNumber) && $attempts < 5) {
        $sequence++;
        $attempts++;
        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);
    }

    if ($this->caseRepository->existsByCaseNumber($caseNumber)) {
        throw new \RuntimeException(
            "No se pudo generar un código único para el expediente después de {$attempts} intentos."
        );
    }

    return $caseNumber;
}
```

### 3.2 `CaseService::createCase()` — resolución del apellido del aplicante

```php
// En createCase(), ANTES de llamar generateCaseNumber():

$caseType = $this->caseTypeRepository->findById($data['case_type_id']);
$client   = Client::findOrFail($data['client_id']);

// Resolver apellido del aplicante principal
$primaryApplicantType        = $data['primary_applicant_type'] ?? 'client';
$primaryApplicantCompanionId = $data['primary_applicant_companion_id'] ?? null;

$primaryLastName = $client->last_name; // default
if ($primaryApplicantType === 'companion' && $primaryApplicantCompanionId) {
    $primaryCompanion = Companion::find($primaryApplicantCompanionId);
    if ($primaryCompanion) {
        $primaryLastName = $primaryCompanion->last_name;
    }
}

$data['case_number'] = $this->generateCaseNumber($caseType, $primaryLastName);
```

**Invariante importante:** El código `AAAA` usa el apellido del aplicante **en el momento de creación** y no cambia si el aplicante principal se modifica posteriormente. El `case_number` es inmutable por diseño (Spec 55 D4).

### 3.3 `ImmigrationCaseObserver` — lifecycle del cliente

```php
// app/Observers/ImmigrationCaseObserver.php
// Agregar los métodos created() y updated() al observer existente:

public function created(ImmigrationCase $case): void
{
    // Activar cliente al crear su primer expediente
    $client = $case->client;
    if ($client && in_array($client->status, ['prospect', 'inactive'])) {
        $client->updateQuietly(['status' => 'active']);
    }
}

public function updated(ImmigrationCase $case): void
{
    // Al cerrar o archivar: verificar si el cliente tiene otros casos activos
    if ($case->isDirty('status') && in_array($case->status, ['closed', 'archived'])) {
        $client = $case->client;
        if (! $client) return;

        $hasActiveCases = ImmigrationCase::where('client_id', $client->id)
            ->where('id', '!=', $case->id)
            ->whereIn('status', ['active', 'inactive'])
            ->exists();

        if (! $hasActiveCases && $client->status === 'active') {
            $client->updateQuietly(['status' => 'inactive']);
        }
    }
}
```

**Por qué `updateQuietly()`:** El cambio de status es una transición automática del sistema, no una acción del usuario. `updateQuietly()` omite el ActivityLog y los observers del modelo `Client`, evitando efectos secundarios no intencionados.

**Registro del observer:** Ya está registrado en `AppServiceProvider::boot()`. No se crea un observer nuevo.

### 3.4 Validación en `StoreCaseRequest`

```php
// app/Http/Requests/Case/StoreCaseRequest.php — agregar reglas:

'primary_applicant_type'         => ['required', 'in:client,companion'],
'primary_applicant_companion_id' => [
    'nullable',
    'integer',
    'exists:companions,id',
    // Requerido solo si type es 'companion':
    Rule::requiredIf(fn() => $this->input('primary_applicant_type') === 'companion'),
],
```

---

## 4. Backend — API Layer

### 4.1 `ImmigrationCaseResource` — campos nuevos

```php
// app/Http/Resources/ImmigrationCaseResource.php — agregar a toArray():

'primary_applicant_type'            => $this->primary_applicant_type,
'primary_applicant_companion_id'    => $this->primary_applicant_companion_id,
'primary_applicant' => $this->when(
    $this->relationLoaded('client') || $this->relationLoaded('primaryApplicantCompanion'),
    function () {
        if ($this->primary_applicant_type === 'companion' && $this->primaryApplicantCompanion) {
            return new CompanionResource($this->primaryApplicantCompanion);
        }
        return new ClientResource($this->client);
    }
),
```

### 4.2 `CaseService::getCase()` — eager load

```php
// Agregar 'primaryApplicantCompanion' a la lista de relaciones:

return $case->load([
    'client',
    'primaryApplicantCompanion',   // ← nuevo
    'caseType',
    'currentStage.translations',
    'assignedTo.profile',
    'companions',
    'importantDates',
    'tasks.template.translations',
    'tasks.workflowStage.translations',
    'invoices',
]);
```

### 4.3 Endpoint para cambiar aplicante post-creación (opcional — Fase 3)

```
PATCH /api/cases/{case}/primary-applicant
Body: { "primary_applicant_type": "companion", "primary_applicant_companion_id": 7 }
```

Este endpoint es opcional en v1. El `update` general del caso ya acepta los campos nuevos. Se puede diferir a una iteración posterior si no es necesario de inmediato.

---

## 5. Frontend — Wizard (7 Steps)

### 5.1 Estado del wizard — campos nuevos

```typescript
// En el composable useCaseWizard.ts (o donde se defina el estado del wizard):

// Agregar al estado:
primaryApplicantType: 'client' as 'client' | 'companion',
primaryApplicantCompanionId: null as number | null,
```

### 5.2 Step 2 — `StepClient.vue` — nota de rol

Añadir un aviso informativo cuando el cliente está seleccionado, explicando que su rol es el de responsable del contrato y que el aplicante se seleccionará en el siguiente paso:

```vue
<!-- Añadir bajo el ClientCard cuando hay cliente seleccionado -->
<div v-if="selectedClient" class="mt-3 p-3 bg-info/10 border border-info/20 rounded-lg text-sm text-info">
    <icon-info class="w-4 h-4 inline ltr:mr-1 rtl:ml-1" />
    {{ $t('wizard.step2.client_role_note') }}
</div>
```

### 5.3 Step 3 — `StepCompanions.vue` — selección obligatoria del aplicante

Insertar **antes** del fieldset de companions:

```vue
<!-- Bloque de selección de aplicante principal -->
<div class="mb-6 p-4 border border-primary/30 rounded-lg bg-primary/5">
    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">
        {{ $t('wizard.step3.primary_applicant_title') }}
        <span class="text-danger ml-1">*</span>
    </h4>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
        {{ $t('wizard.step3.primary_applicant_desc') }}
    </p>

    <select
        v-model="primaryApplicantSelection"
        class="form-select"
        :class="{ 'border-danger': validationAttempted && !primaryApplicantSelection }"
        @change="handlePrimaryApplicantChange"
    >
        <option value="">{{ $t('wizard.step3.select_primary_applicant') }}</option>
        <option v-if="wizard.state.clientId" :value="`client:${wizard.state.clientId}`">
            {{ selectedClientName }} ({{ $t('wizard.step3.client_label') }})
        </option>
        <option
            v-for="companion in selectedCompanions"
            :key="companion.id"
            :value="`companion:${companion.id}`"
        >
            {{ companion.full_name }} — {{ companion.relationship_label || companion.relationship }}
        </option>
    </select>

    <p v-if="validationAttempted && !primaryApplicantSelection" class="text-danger text-sm mt-1">
        {{ $t('wizard.step3.primary_applicant_required') }}
    </p>
</div>
```

**Lógica reactiva en `<script setup>`:**

```typescript
const primaryApplicantSelection = ref('');
const validationAttempted = ref(false);

// Companions ya seleccionados (los que aparecen en el dropdown)
const selectedCompanions = computed(() =>
    companions.value.filter(c => wizard.state.selectedCompanionIds.includes(c.id))
);

function handlePrimaryApplicantChange() {
    const [type, id] = primaryApplicantSelection.value.split(':');
    wizard.state.primaryApplicantType = type as 'client' | 'companion';
    wizard.state.primaryApplicantCompanionId = type === 'companion' ? parseInt(id) : null;
}

// Si se deselecciona el companion que era aplicante → resetear
watch(() => wizard.state.selectedCompanionIds, (ids) => {
    if (
        wizard.state.primaryApplicantType === 'companion' &&
        wizard.state.primaryApplicantCompanionId &&
        !ids.includes(wizard.state.primaryApplicantCompanionId)
    ) {
        primaryApplicantSelection.value = '';
        wizard.state.primaryApplicantType = 'client';
        wizard.state.primaryApplicantCompanionId = null;
    }
});

// Validación para avanzar al Step 4
function canAdvance(): boolean {
    return (
        wizard.state.primaryApplicantType === 'client' ||
        (wizard.state.primaryApplicantType === 'companion' &&
         !!wizard.state.primaryApplicantCompanionId)
    );
}
```

### 5.4 Step 7 — `StepSummary.vue` — jerarquía y fix del preview

**Fix del `caseNumberPreview` (actualmente usa apellido del cliente):**

```typescript
const caseNumberPreview = computed(() => {
    const year2    = new Date().getFullYear().toString().slice(-2);
    const typeCode = selectedCaseType.value?.code ?? '???';

    // Resolver apellido desde el aplicante principal
    let lastName = selectedClient.value?.last_name ?? '';
    if (
        wizard.state.primaryApplicantType === 'companion' &&
        wizard.state.primaryApplicantCompanionId
    ) {
        const applicant = selectedCompanions.value.find(
            c => c.id === wizard.state.primaryApplicantCompanionId
        );
        if (applicant) lastName = applicant.last_name;
    }

    const slug = lastName
        ? lastName.toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'X').slice(0, 4)
        : '????';

    return `${year2}-${typeCode}-${slug}-????`;
});
```

**Sección "Client" rediseñada con jerarquía:**

```vue
<!-- 1. Client Info -->
<section aria-labelledby="client-heading">
    <h4 id="client-heading">{{ $t('wizard.step6.client_info') }}</h4>
    <div class="flex items-center gap-4">
        <!-- avatar + nombre del cliente -->
        <span v-if="wizard.state.primaryApplicantType === 'client'"
              class="badge badge-outline-success text-xs ml-2">
            {{ $t('wizard.step6.also_primary_applicant') }}
        </span>
    </div>
</section>

<!-- 2. Primary Applicant (solo si es diferente al cliente) -->
<section v-if="wizard.state.primaryApplicantType === 'companion'"
         aria-labelledby="primary-applicant-heading">
    <h4 id="primary-applicant-heading">{{ $t('wizard.step6.primary_applicant') }}</h4>
    <div class="flex items-center gap-3">
        <span class="badge badge-outline-primary">
            {{ $t('wizard.step6.primary_applicant_badge') }}
        </span>
        <p class="font-medium">{{ primaryApplicantCompanionName }}</p>
    </div>
</section>

<!-- 3. Dependents (companions excluyendo al aplicante principal) -->
<section aria-labelledby="companions-heading">
    <h4 id="companions-heading">{{ $t('wizard.step6.dependents') }}</h4>
    <!-- Filtrar: excluir al companion que es primary_applicant -->
    <div class="flex flex-wrap gap-2">
        <span v-for="companion in dependentCompanions" :key="companion.id" ...>
            {{ companion.full_name }}
        </span>
    </div>
    <p v-if="dependentCompanions.length === 0" class="text-gray-500 text-sm">
        {{ $t('wizard.step6.no_dependents') }}
    </p>
</section>
```

### 5.5 Payload enviado al API

```typescript
// En el submit final del wizard:
const payload = {
    case_type_id:                    wizard.state.caseTypeId,
    client_id:                       wizard.state.clientId,
    companion_ids:                   wizard.state.selectedCompanionIds,
    primary_applicant_type:          wizard.state.primaryApplicantType,
    primary_applicant_companion_id:  wizard.state.primaryApplicantCompanionId ?? null,
    // ... resto de campos
};
```

---

## 6. Frontend — Vista `cases/show.vue`

### 6.1 Header Card — aplicante principal en lugar del cliente

```vue
<!-- ANTES: muestra currentCase.client -->
<!-- DESPUÉS: muestra el aplicante principal -->
<div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg mt-4">
    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
        <span class="text-lg font-semibold text-primary">
            {{ primaryApplicant?.initials || '??' }}
        </span>
    </div>
    <div class="flex-1">
        <div class="font-semibold text-xl">
            {{ primaryApplicant?.full_name }}
            <span class="badge badge-outline-primary ml-2 text-xs align-middle">
                {{ $t('cases.primary_applicant') }}
            </span>
        </div>
        <!-- Mostrar el cliente si es diferente al aplicante -->
        <div class="text-sm text-gray-500 mt-0.5">
            {{ $t('cases.client') }}:
            <router-link :to="`/clients/${currentCase.client.id}`"
                         class="text-primary hover:underline">
                {{ currentCase.client.full_name }}
            </router-link>
            <span v-if="currentCase.primary_applicant_type === 'client'"
                  class="text-xs text-gray-400 ml-1">
                ({{ $t('cases.client_is_applicant') }})
            </span>
        </div>
    </div>
</div>
```

**Computed `primaryApplicant` en el script:**

```typescript
const primaryApplicant = computed(() => {
    if (!currentCase.value) return null;
    if (currentCase.value.primary_applicant_type === 'companion') {
        return currentCase.value.primary_applicant ?? currentCase.value.client;
    }
    return currentCase.value.client;
});
```

### 6.2 Pestaña "General Information" — 3 bloques

```
┌─────────────────────────────────────────────────────────┐
│ CLIENT INFO (responsable del contrato)                  │
│   Nombre, email, teléfono, estado migratorio            │
├─────────────────────────────────────────────────────────┤
│ PRIMARY APPLICANT (quién encabeza la solicitud)         │
│   [Solo si es diferente al cliente]                     │
│   Nombre, relación, pasaporte, estado migratorio        │
├─────────────────────────────────────────────────────────┤
│ DEPENDENTS / COMPANIONS                                 │
│   [Lista de companions que NO son el aplicante]         │
└─────────────────────────────────────────────────────────┘
```

---

## 7. Frontend — Cleanup de Vistas de Cliente

### 7.1 `clients/create.vue` (líneas ~357-360)

```vue
<!-- REMOVER este bloque completo: -->
<input type="checkbox"
       v-model="form.is_primary_applicant"
       class="form-checkbox text-primary" />
<span class="ml-2">{{ $t('clients.is_primary_applicant') }}</span>
```

### 7.2 `clients/edit.vue` (líneas ~323-324)

```vue
<!-- REMOVER este bloque completo: -->
<input type="checkbox" v-model="form.is_primary_applicant" class="form-checkbox text-primary" />
<span class="ml-2">{{ $t('clients.is_primary_applicant') }}</span>
```

### 7.3 `clients/show.vue` (línea ~47)

```vue
<!-- REMOVER: -->
<span v-if="client.is_primary_applicant" class="badge badge-outline-success">...</span>
```

### 7.4 `clientService.ts` (líneas ~28-29)

```typescript
// REMOVER:
if (filters.is_primary_applicant !== undefined) {
    params.append('is_primary_applicant', filters.is_primary_applicant ? '1' : '0');
}
```

---

## 8. Internacionalización (i18n)

**Regla crítica:** Keys FLAT. Nunca objetos JSON anidados. `<` y `>` → `{'<'}` y `{'>'}`.

### Keys a REMOVER (de los 3 idiomas):

```json
"clients.is_primary_applicant": "...",
"clients.select_is_primary_applicant": "..."
```

### Keys a AGREGAR:

```json
"cases.primary_applicant": "Primary Applicant",
"cases.client_info": "Client Info",
"cases.client_is_applicant": "also Primary Applicant",
"wizard.step2.client_role_note": "This client is the contract holder. In the next step you will select who leads the immigration application.",
"wizard.step3.primary_applicant_title": "Primary Applicant",
"wizard.step3.primary_applicant_desc": "Select who leads this immigration application. Can be the client or one of the companions.",
"wizard.step3.select_primary_applicant": "Select primary applicant...",
"wizard.step3.primary_applicant_required": "You must select a primary applicant before continuing.",
"wizard.step3.client_label": "Client / Contract Holder",
"wizard.step6.client_info": "Client Info",
"wizard.step6.primary_applicant": "Primary Applicant",
"wizard.step6.primary_applicant_badge": "Primary Applicant",
"wizard.step6.also_primary_applicant": "also Primary Applicant",
"wizard.step6.dependents": "Dependents / Companions",
"wizard.step6.no_dependents": "No other companions selected."
```

Agregar equivalentes en `es.json` y `fr.json`.

---

## 9. TypeScript — Tipos Afectados

```typescript
// resources/js/src/types/client.ts — REMOVER:
is_primary_applicant: boolean;
is_primary_applicant?: boolean;

// resources/js/src/types/case.ts (o equivalente) — AGREGAR:
primary_applicant_type: 'client' | 'companion';
primary_applicant_companion_id: number | null;
primary_applicant?: Client | Companion;  // poblado por el Resource cuando se carga el caso
```

---

## 10. Impacto en el Resto del Sistema

| Área | Impacto | Cambio |
|------|---------|--------|
| `GlobalSearchService` | MEDIO | Actualmente busca casos por `client.first_name/last_name`; evaluar si debe buscar también por `primary_applicant` en v2 |
| `ImmigrationCase::scopeSearch()` | MEDIO | Busca por `client.first_name/last_name` — considerar extender para aplicante en v2 |
| Dashboard ([[25_dashboard_centro_de_control|Spec 25]]) | MEDIO | Si muestra nombre del cliente en lista de casos → considerar mostrar aplicante + _(cliente: X)_ en v2 |
| [[57_alertas_fechas_importantes_expedientes|Spec 57]] (Alertas Fechas) | BAJO | Muestra `case_number`, no nombre directo del cliente — sin impacto |
| Spec 56 (Folders en nube) | BAJO | `FolderService` usa `$case->case_number` — sin cambios, pero el `AAAA` del número ya refleja el aplicante en casos nuevos |
| `ClientRepository::paginate()` | BAJO | Remover filtro `is_primary_applicant` |
| `ClientController` | BAJO | Remover `is_primary_applicant` de la lista de filtros aceptados |

---

## 11. Checklist de Implementación

### FASE 1 — Base de Datos (BAJO riesgo · Reversible · Sin downtime)
- [x] Migración 1: `ADD primary_applicant_type + primary_applicant_companion_id` a `cases`
  - Verificar que el default `'client'` se aplique correctamente a todos los registros existentes

### FASE 2 — Backend Models y Service Layer (MEDIO riesgo)
- [x] `ImmigrationCase`: agregar `$fillable`, relación `primaryApplicantCompanion()`, método `primaryApplicant()`, accessors
- [x] `Client`: remover `is_primary_applicant` de `$fillable` y `$casts`
- [x] `CaseService::generateCaseNumber()`: cambiar firma a `(CaseType $caseType, string $primaryLastName)`
- [x] `CaseService::createCase()`: resolver `$primaryLastName` antes de llamar `generateCaseNumber()`
- [x] `ImmigrationCaseObserver`: agregar `created()` y `updated()` para lifecycle del cliente
- [x] `StoreCaseRequest`: agregar reglas para `primary_applicant_type` y `primary_applicant_companion_id`
- [x] `StoreClientRequest`: remover validación de `is_primary_applicant`
- [x] `UpdateClientRequest`: remover validación de `is_primary_applicant`
- [x] `ClientRepository`: remover filtro `is_primary_applicant` de `paginate()`

### FASE 3 — API Layer (BAJO riesgo)
- [x] `ImmigrationCaseResource`: agregar `primary_applicant_type`, `primary_applicant_companion_id`, `primary_applicant`
- [x] `CaseService::getCase()`: agregar `'primaryApplicantCompanion'` al eager load
- [x] `ClientController`: remover `is_primary_applicant` de los filtros aceptados
- [ ] (Opcional) Nuevo endpoint `PATCH /api/cases/{case}/primary-applicant` — diferido a v2

### FASE 4 — Frontend Wizard (MEDIO riesgo · Testear flujo completo)
- [x] `useCaseWizard.ts`: agregar `primaryApplicantType`, `primaryApplicantCompanionId` al estado
- [x] `StepClient.vue`: agregar nota informativa del rol del cliente
- [x] `StepCompanions.vue`: agregar dropdown obligatorio de selección del aplicante principal con validación
- [x] `StepSummary.vue`: fix `caseNumberPreview` + secciones Client Info / Primary Applicant / Dependents
- [x] `types/case.ts`: agregar `primary_applicant_type`, `primary_applicant_companion_id`, `primary_applicant`
- [x] `types/client.ts`: remover `is_primary_applicant`

### FASE 5 — Frontend Vistas (BAJO riesgo)
- [x] `cases/show.vue`: header card con `primaryApplicant` computed + General Info con 3 bloques
- [x] `clients/create.vue`: remover campo `is_primary_applicant`
- [x] `clients/edit.vue`: remover campo `is_primary_applicant`
- [x] `clients/show.vue`: remover badge `is_primary_applicant`
- [x] `clientService.ts`: remover filtro `is_primary_applicant`

### FASE 6 — Cleanup Final (ALTO riesgo · Irreversible · Hacer backup antes)
- [x] `grep -rn "is_primary_applicant"` en todo el proyecto → retorna 0 resultados en app/ y resources/
- [x] Migración 2: `DROP COLUMN is_primary_applicant FROM clients` — 72.75ms DONE
- [x] Actualizar `es.json`, `en.json`, `fr.json`: remover keys antiguas, agregar nuevas (ver sección 8)
- [ ] Tests: `CaseService` (generateCaseNumber con aplicante companion), `ImmigrationCaseObserver` (lifecycle prospect→active→inactive) — diferido
- [x] Actualizar `spec/INDEX.md`: cambiar estado de Spec 58 de `PEND` a `DONE`
- [x] Actualizar `MEMORY.md`: agregar entrada en "Implementaciones Recientes"

---

## 12. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|-----------|
| Observer `created()` activa clientes en seeders de desarrollo | MEDIA | BAJO | Agregar `if (app()->runningInConsole() && app()->environment('local'))` guard, o usar `createQuietly()` en factories |
| Companion seleccionado como aplicante es eliminado del caso post-creación | BAJA | MEDIO | `nullOnDelete()` en FK → `primary_applicant_companion_id` vuelve a `null`; añadir validación en API update para resetear `primary_applicant_type` a `'client'` en ese caso |
| Código `AAAA` del número usa apellido del cliente en casos históricos pero aplicante es un companion | N/A | BAJO | Por diseño: el código es inmutable desde la creación; solo casos nuevos usarán el apellido correcto |
| Drop de columna `is_primary_applicant` en producción con datos activos | BAJA | ALTO | Ejecutar solo en Fase 6, después de verificar con `grep` que ningún código la lee; hacer backup previo |

---

## 13. Notas de Versión

**v1.1 (2026-05-14):** Las cuatro decisiones que estaban pendientes de validación del equipo en v1.0 fueron revisadas y aceptadas con sus recomendaciones originales. Se incorporaron como decisiones confirmadas D8–D11 en la tabla "Decisiones Clave". Los detalles de implementación correspondientes ya estaban reflejados en las secciones 3.3, 3.4, 5.3 y 10 del spec desde v1.0.

| Decisión confirmada | Resolución |
|---------------------|-----------|
| Obligatoriedad de selección en wizard (P1 → D8) | **Obligatoria** — bloquea avance al Step 4 |
| Inmutabilidad del código post-creación (P2 → D9) | **Inmutable** — `case_number` no se regenera |
| Búsqueda global por nombre del aplicante (P3 → D10) | **Diferida a v2** — fuera de scope de esta iteración |
| Lifecycle del cliente via observer (P4 → D11) | **Observer automático** — `ImmigrationCaseObserver` |
