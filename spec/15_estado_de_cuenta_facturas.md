---
tipo: spec
numero: 15
modulo: Documentos y Storage
dominio: DOCS
estado: PEND
tags: [spec, documentos, pendiente]
relacionados:
  - "[[13_campos_operativos_financieros|Spec 13]]"
---
# Analisis de Impacto Tecnico: Modulo de Facturacion ("Estado de Cuenta")

**Fecha:** 2026-03-13
**Autor:** Arquitecto de Software
**Estado:** ✅ COMPLETADO
**Modulo afectado:** Cases > Estado de Cuenta (Invoices)

---

## 0. Campo `cases.fees` — Valor del Expediente

El campo `fees` (`DECIMAL(10,2)`, permiso `cases.view-fees`) es la fuente primaria del "Total Expediente" en el módulo de facturación. Actualmente implementado (Epic 3.3) en las siguientes ubicaciones:

| Vista | Ubicacion | Tipo | Permiso | Visible por defecto |
|-------|-----------|------|---------|---------------------|
| **Wizard Paso 4** (`StepDetails.vue`) | Sección "Información Financiera" | Input `number` (step=0.01, min=0) | `cases.view-fees` | Condicional |
| **Edición** (`edit.vue`) | Sección "Información Financiera" | Input `number` con prefijo `$` | `cases.view-fees` | Condicional |
| **Consulta** (`show.vue`) | Tab "Info" > subsección financiera | Display formateado `$X,XXX.XX` | Sin restriccion (display) | Oculto si null |
| **Lista** (`list.vue`) | Columna "Valor" en datatable | Display `$X,XXX.XX` via column chooser | `cases.view-fees` | **Oculta por defecto** |

**Relacion con el modulo de facturas:**
- `financial_summary.fees` en el API response proviene directamente de `cases.fees`
- La columna "Total expediente" del componente `InvoiceTable.vue` consume `financialSummary.fees`
- El `balance = fees - total_invoiced` se recalcula al vuelo en `CaseResource` — si `fees` cambia en `edit.vue`, el balance se actualiza automáticamente en la proxima carga de `show.vue`

**Estado:** ✅ Ya implementado en Epic 3.3 — no requiere trabajo adicional en este Epic

---

## 1. Analisis de Decisiones Arquitectonicas

| Dimension | Opciones | Decision Recomendada | Justificacion |
|-----------|----------|---------------------|---------------|
| **Nombre de tabla** | `case_invoices` vs `invoices` | `case_invoices` | Sigue el patron establecido: `case_tasks`, `case_important_dates`. Las facturas son hijas de `cases` y no entidades independientes. Prefijo `case_` mantiene consistencia. |
| **Tipo de dato para montos** | `decimal(10,2)` vs `integer` (centavos) | `decimal(10,2)` | El campo `cases.fees` ya usa `decimal(10,2)` con cast `'fees' => 'decimal:2'`. Mezclar representaciones (centavos vs decimales) generaria bugs de conversion. Consistencia manda. |
| **Calculos financieros** | Aggregates en query (al vuelo) vs denormalizados en `cases` | Aggregates en query (al vuelo) | Con pocas facturas por caso (<50), un `SUM()` es trivial. Denormalizar introduce riesgo de inconsistencia y necesidad de recalcular manualmente. Los aggregates se calculan en el Service y se exponen via `CaseResource` como campos virtuales. No se agrega columna a `cases`. |
| **Estrategia de sync** | Delete-and-insert bulk vs upsert por fila | Delete-and-insert bulk | Patron identico a `CaseTaskService::syncTasks()` y `CaseImportantDateService`. Simplifica la logica: el frontend envia el array completo, el backend borra y reinserta. Evita logica de diff/merge. |
| **UI pattern** | Inline editing (como imagen) vs modal | Inline editing | La imagen de referencia lo especifica explicitamente. Consistente con `DateManager.vue` y `LifecycleChecklist.vue` que ya usan edicion inline con tabla. |
| **Permisos** | Nuevo `cases.manage-invoices` vs reutilizar `cases.view-fees` | Nuevo permiso `cases.manage-invoices` + reutilizar `cases.view-fees` para lectura | `cases.view-fees` controla visibilidad de lectura. Se necesita un permiso separado para escritura (CRUD) de facturas. El rol `contador` ya tiene `cases.view-fees`; se le agrega `cases.manage-invoices`. Admin ya tiene full access. |
| **Unicidad numero de factura** | Scope tenant vs scope global | Scope tenant | Cada tenant opera independientemente. Dos tenants pueden tener factura `F-001`. El constraint `UNIQUE(tenant_id, invoice_number)` requiere que `tenant_id` este en la tabla, pero siguiendo el patron de `case_tasks`/`case_important_dates`, estas tablas NO tienen `tenant_id` — se infiere desde `case_id -> cases.tenant_id`. Por lo tanto, la unicidad se valida en el FormRequest via query join, no via constraint DB. |
| **Numero de factura** | Autoincremental del sistema vs ingresado por usuario | Ingresado por usuario | La imagen muestra un campo de texto para "Factura" (numero). El usuario ingresa el numero manualmente (puede ser del sistema contable externo). Validacion: required, string, max:50, unico por tenant. |
| **Estatus de recaudacion** | Booleano `is_collected` vs enum | Booleano `is_collected` | Requerimiento dice "booleano". Solo dos estados: cobrado/no cobrado. Un enum agregaria complejidad sin beneficio actual. Si en el futuro se necesitan estados intermedios, se migra a enum. |
| **Endpoints** | Resource anidado `/api/cases/{case}/invoices` vs bulk unico | Bulk unico `PUT /api/cases/{case}/invoices` | Patron identico a `PUT /api/cases/{case}/tasks`. Un solo endpoint recibe el array completo y ejecuta delete-and-insert. Para lectura, las invoices se incluyen en `GET /api/cases/{case}` via `whenLoaded()`. |

---

## 2. Esquema de Base de Datos

### Tabla `case_invoices`

```sql
CREATE TABLE `case_invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `case_id` BIGINT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `is_collected` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    CONSTRAINT `case_invoices_case_id_foreign`
        FOREIGN KEY (`case_id`) REFERENCES `cases` (`id`) ON DELETE CASCADE,
    INDEX `case_invoices_case_id_index` (`case_id`),
    INDEX `case_invoices_case_id_is_collected_index` (`case_id`, `is_collected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Justificacion de cada campo

| Campo | Tipo | Justificacion |
|-------|------|---------------|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | PK estandar Laravel. |
| `case_id` | `BIGINT UNSIGNED NOT NULL` | FK hacia `cases.id`. ON DELETE CASCADE igual que `case_tasks`. Sin `tenant_id` — se infiere desde case. |
| `invoice_number` | `VARCHAR(50) NOT NULL` | Ingresado por usuario. Max 50 caracteres suficiente para codigos contables (ej: `FAC-2026-0001`). Unicidad validada en FormRequest por tenant. |
| `invoice_date` | `DATE NOT NULL` | Fecha de emision. No nullable porque toda factura debe tener fecha. |
| `amount` | `DECIMAL(10,2) NOT NULL DEFAULT 0.00` | Monto en CAD. Mismo tipo que `cases.fees`. No negativo validado en FormRequest. Rango max 99,999,999.99 suficiente. |
| `is_collected` | `TINYINT(1) NOT NULL DEFAULT 0` | Estatus de recaudacion booleano. Default: no cobrado. |
| `sort_order` | `TINYINT UNSIGNED DEFAULT 0` | Orden de visualizacion, patron identico a `case_tasks.sort_order`. |
| `created_at/updated_at` | `TIMESTAMP NULL` | Timestamps estandar Laravel. |

### Migracion Laravel

```php
Schema::create('case_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
    $table->string('invoice_number', 50);
    $table->date('invoice_date');
    $table->decimal('amount', 10, 2)->default(0);
    $table->boolean('is_collected')->default(false);
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index('case_id');
    $table->index(['case_id', 'is_collected']);
});
```

---

## 3. API Contract

### 3.1 GET /api/cases/{case} (Modificado)

Las invoices se incluyen como relacion condicional en `CaseResource`, igual que tasks e important_dates. El resumen financiero se calcula y se incluye condicionalmente bajo el permiso `cases.view-fees`.

**Response** (campos nuevos marcados con `[NEW]`):

```json
{
    "data": {
        "id": 1,
        "case_number": "IMM-2026-001",
        "fees": 5000.00,
        "...": "...campos existentes...",

        "invoices": [                          // [NEW] whenLoaded('invoices')
            {
                "id": 1,
                "invoice_number": "FAC-001",
                "invoice_date": "2026-03-01",
                "amount": "1500.00",
                "is_collected": true,
                "sort_order": 0
            },
            {
                "id": 2,
                "invoice_number": "FAC-002",
                "invoice_date": "2026-03-10",
                "amount": "2000.00",
                "is_collected": false,
                "sort_order": 1
            }
        ],

        "financial_summary": {                  // [NEW] when(can('cases.view-fees'))
            "fees": 5000.00,
            "total_invoiced": 3500.00,
            "total_collected": 1500.00,
            "balance": 1500.00
        }
    }
}
```

**Notas sobre `financial_summary`:**
- `fees`: valor de `cases.fees` (puede ser `null` si no se ha definido)
- `total_invoiced`: `SUM(case_invoices.amount)` para el case
- `total_collected`: `SUM(case_invoices.amount) WHERE is_collected = true`
- `balance`: `fees - total_invoiced` (puede ser negativo si se factura de mas)
- Solo se incluye si el usuario tiene permiso `cases.view-fees`
- Si `fees` es `null`, `balance` tambien es `null`

### 3.2 PUT /api/cases/{case}/invoices (Nuevo - Bulk Update)

**Autorizacion:** Requiere permiso `cases.manage-invoices`

**Request:**

```json
{
    "invoices": [
        {
            "invoice_number": "FAC-001",
            "invoice_date": "2026-03-01",
            "amount": 1500.00,
            "is_collected": true
        },
        {
            "invoice_number": "FAC-002",
            "invoice_date": "2026-03-10",
            "amount": 2000.00,
            "is_collected": false
        }
    ]
}
```

**Reglas de validacion:**

```
invoices                    => required | array | max:100
invoices.*.invoice_number   => required | string | max:50
invoices.*.invoice_date     => required | date | date_format:Y-m-d
invoices.*.amount           => required | numeric | min:0 | max:99999999.99
invoices.*.is_collected     => boolean
```

**Validacion adicional en FormRequest:**
- Unicidad de `invoice_number` dentro del array enviado (no duplicados en el mismo request)
- Unicidad de `invoice_number` por tenant: query `case_invoices` JOIN `cases` WHERE `cases.tenant_id` = auth tenant, excluyendo invoices del case actual

**Response (200):**

```json
{
    "data": {
        "id": 1,
        "case_number": "IMM-2026-001",
        "...": "...CaseResource completo con invoices y financial_summary..."
    },
    "message": "Invoices updated successfully."
}
```

**Error Response (422):**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "invoices.0.invoice_number": ["The invoice number has already been taken."],
        "invoices.1.amount": ["The amount must be at least 0."]
    }
}
```

---

## 4. Lista Completa de Archivos Impactados

### 4.1 Backend — Nuevos

| # | Archivo | Descripcion |
|---|---------|-------------|
| 1 | `database/migrations/YYYY_MM_DD_HHMMSS_create_case_invoices_table.php` | Migracion para tabla `case_invoices` |
| 2 | `app/Models/CaseInvoice.php` | Modelo Eloquent. `fillable`, `casts`, relacion `belongsTo(ImmigrationCase)`. Sin `BelongsToTenant` trait (se infiere desde case). |
| 3 | `app/Services/Case/CaseInvoiceService.php` | Servicio con metodo `syncInvoices(ImmigrationCase $case, array $invoices): void`. Patron identico a `CaseTaskService::syncTasks()`. |
| 4 | `app/Http/Controllers/Api/CaseInvoiceController.php` | Controller con metodo `bulkUpdate()`. Inyecta `CaseInvoiceService`. Patron identico a `CaseTaskController`. |
| 5 | `app/Http/Requests/Case/BulkUpdateCaseInvoicesRequest.php` | FormRequest con reglas de validacion, autorizacion via `can('cases.manage-invoices')`, validacion de unicidad de invoice_number por tenant. |
| 6 | `app/Http/Resources/CaseInvoiceResource.php` | API Resource para serializar invoices. |

### 4.2 Backend — Modificados

| # | Archivo | Cambio Especifico |
|---|---------|-------------------|
| 1 | `app/Models/ImmigrationCase.php` | Agregar relacion `invoices(): HasMany` retornando `$this->hasMany(CaseInvoice::class, 'case_id')->orderBy('sort_order')`. |
| 2 | `app/Http/Resources/CaseResource.php` | (a) Agregar `'invoices' => CaseInvoiceResource::collection($this->whenLoaded('invoices'))`. (b) Agregar bloque `'financial_summary' => $this->when($request->user()?->can('cases.view-fees'), fn() => [...])` con fees, total_invoiced, total_collected, balance. |
| 3 | `app/Http/Controllers/Api/CaseController.php` | En metodo `show()`: agregar `'invoices'` al array de `$case->load([...])` para que se incluya en la respuesta. |
| 4 | `routes/api.php` | Agregar ruta `Route::put('/cases/{case}/invoices', [CaseInvoiceController::class, 'bulkUpdate']);` dentro del grupo protegido. |
| 5 | `database/seeders/RolePermissionSeeder.php` | (a) Agregar permiso `['name' => 'cases.manage-invoices', 'display_name' => 'Manage Case Invoices']`. (b) Asignar a roles `admin` y `contador`. |

### 4.3 Frontend — Nuevos

| # | Archivo | Descripcion |
|---|---------|-------------|
| 1 | `resources/js/src/types/invoice.ts` | Interfaz `CaseInvoice`, `FinancialSummary`, y DTO para bulk update. |
| 2 | `resources/js/src/views/cases/components/InvoiceTable.vue` | Componente de tabla inline editable. Recibe `invoices[]`, `financialSummary`, `readonly`, `caseId`. Emite `@saved`. |

### 4.4 Frontend — Modificados

| # | Archivo | Cambio Especifico |
|---|---------|-------------------|
| 1 | `resources/js/src/types/case.ts` | Agregar campos opcionales `invoices?: CaseInvoice[]` y `financial_summary?: FinancialSummary` a interfaz `ImmigrationCase`. Importar tipos de `invoice.ts`. |
| 2 | `resources/js/src/services/caseService.ts` | Agregar metodo `bulkUpdateInvoices(caseId: number, invoices: ...): Promise<CaseResponse>` que hace `PUT /cases/{caseId}/invoices`. |
| 3 | `resources/js/src/stores/case.ts` | Agregar action `bulkUpdateInvoices(caseId, invoices)` que llama al service y actualiza `currentCase`. |
| 4 | `resources/js/src/views/cases/show.vue` | (a) Agregar tab `{ id: 'invoices', label: 'cases.tab_invoices' }` al array `tabs`. (b) Agregar seccion condicional `v-else-if="activeTab === 'invoices'"` con componente `<InvoiceTable>`. (c) Proteger tab con `v-can="'cases.view-fees'"`. |
| 5 | `resources/js/src/locales/en.json` | Agregar claves `cases.tab_invoices`, `cases.invoice_*`, `cases.financial_*` (ver seccion 8). |
| 6 | `resources/js/src/locales/es.json` | Traducciones al espanol de las mismas claves. |
| 7 | `resources/js/src/locales/fr.json` | Traducciones al frances de las mismas claves. |

---

## 5. Analisis de Casos de Borde y Riesgos

| # | Caso de Borde | Comportamiento Esperado | Implementacion |
|---|---------------|------------------------|----------------|
| 1 | `fees` modificado en `edit.vue` despues de crear invoices | El saldo (`balance`) se recalcula automaticamente en la proxima carga de `show.vue`. No se invalidan invoices existentes. | `financial_summary.balance` se calcula al vuelo en `CaseResource` como `fees - total_invoiced`. Cualquier cambio en `fees` se refleja automaticamente. |
| 2 | Factura eliminada del array y se guarda | Delete-and-insert reemplaza todo. Las facturas no enviadas en el array se eliminan. El `total_invoiced` y `balance` se recalculan. | El Service ejecuta `$case->invoices()->delete()` y luego reinserta solo lo que viene en el request. |
| 3 | Caso sin `fees` definido (`null`) | Mostrar `fees` como "N/A" o "--". `balance` se muestra como `null`/no disponible. `total_invoiced` sigue siendo valido. | En `CaseResource`: `'balance' => $this->fees !== null ? $this->fees - $totalInvoiced : null`. En UI: condicion `v-if="financialSummary.fees !== null"` para mostrar balance. |
| 4 | Numero de factura duplicado en mismo request | Error de validacion 422. | Regla personalizada en FormRequest: `Rule::unique` custom + validacion de duplicados intra-array con `distinct` rule en `invoices.*.invoice_number`. |
| 5 | Numero de factura duplicado en otro caso del mismo tenant | Error de validacion 422 con mensaje claro. | En FormRequest: query `CaseInvoice::whereHas('case', fn($q) => $q->where('tenant_id', auth()->user()->tenant_id))->where('invoice_number', $number)->where('case_id', '!=', $currentCaseId)->exists()`. |
| 6 | Monto negativo | Error de validacion 422. | Regla `'invoices.*.amount' => ['required', 'numeric', 'min:0']`. |
| 7 | Usuario sin permiso `cases.view-fees` | Tab "Estado de Cuenta" no visible en el UI. API no incluye `invoices` ni `financial_summary` en response. | Tab protegido con `v-can`. En `CaseResource`, invoices y financial_summary estan dentro de `$this->when($request->user()?->can('cases.view-fees'), ...)`. |
| 8 | Usuario con `cases.view-fees` pero sin `cases.manage-invoices` | Puede ver la tab y los datos pero el boton "Guardar" y los campos de edicion estan deshabilitados. | Prop `readonly` en `InvoiceTable.vue`. En el componente: `const canManage = userStore.can('cases.manage-invoices')`. Inputs y botones condicionados a `canManage`. |
| 9 | Total facturado > fees (sobrefacturacion) | Se permite pero se muestra un warning visual (badge rojo en balance negativo). | No se bloquea. En UI: `balance < 0` muestra badge `text-danger`. El negocio puede tener razones validas para facturar mas (ajustes, extras). |
| 10 | Array de invoices vacio `{ "invoices": [] }` | Se permite. Borra todas las facturas del caso. | Validacion `'invoices' => ['required', 'array']` permite array vacio (no tiene `min:1`). El service ejecuta delete sin insertar. Correccion: cambiar a `['present', 'array']` para permitir array vacio explicitamente. |
| 11 | Caso con soft delete eliminado | Las invoices se eliminan en cascada por FK. | `ON DELETE CASCADE` en la FK. Si el caso se restaura (SoftDeletes), las invoices ya no existiran. Riesgo aceptable dado que el proyecto no usa soft delete para entidades operativas. |
| 12 | Concurrencia: dos usuarios editando invoices simultaneamente | Ultimo en guardar gana (last-write-wins). | Patron delete-and-insert es inherentemente last-write-wins. Aceptable para el volumen esperado. Si se necesita optimistic locking en el futuro, se agrega un campo `version` al case. |

---

## 6. Componentes Vue Necesarios

### 6.1 InvoiceTable.vue — Componente principal

**Ubicacion:** `resources/js/src/views/cases/components/InvoiceTable.vue`

#### Template (estructura)

```
<div>
  <!-- Header: Titulo + Resumen Financiero -->
  <div class="flex justify-between items-start mb-5">
    <h3>{{ $t('cases.tab_invoices') }}</h3>
    <!-- Resumen financiero (3 badges) -->
    <div v-if="financialSummary" class="flex gap-4">
      <div class="text-center">
        <div class="text-xs text-gray-500">{{ $t('cases.invoice_total_case') }}</div>
        <div class="font-semibold text-primary">${{ formatMoney(financialSummary.fees) }}</div>
      </div>
      <div class="text-center">
        <div class="text-xs text-gray-500">{{ $t('cases.invoice_total_invoiced') }}</div>
        <div class="font-semibold text-info">${{ formatMoney(financialSummary.total_invoiced) }}</div>
      </div>
      <div class="text-center">
        <div class="text-xs text-gray-500">{{ $t('cases.invoice_balance') }}</div>
        <div class="font-semibold" :class="balanceClass">${{ formatMoney(financialSummary.balance) }}</div>
      </div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="table-responsive">
    <table class="table-hover">
      <thead>
        <tr>
          <th>#</th>
          <th>{{ $t('cases.invoice_number') }}</th>
          <th>{{ $t('cases.invoice_date') }}</th>
          <th>{{ $t('cases.invoice_amount') }}</th>
          <th>{{ $t('cases.invoice_collected') }}</th>
          <th v-if="canManage">{{ $t('cases.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(invoice, index) in localInvoices" :key="index">
          <td>{{ index + 1 }}</td>
          <td>
            <input v-if="canManage" v-model="invoice.invoice_number" class="form-input" />
            <span v-else>{{ invoice.invoice_number }}</span>
          </td>
          <td>
            <flat-pickr v-if="canManage" v-model="invoice.invoice_date" class="form-input" />
            <span v-else>{{ invoice.invoice_date }}</span>
          </td>
          <td>
            <input v-if="canManage" v-model.number="invoice.amount" type="number"
                   step="0.01" min="0" class="form-input" />
            <span v-else>${{ formatMoney(invoice.amount) }}</span>
          </td>
          <td>
            <label class="w-12 h-6 relative">
              <input type="checkbox" v-model="invoice.is_collected"
                     :disabled="!canManage" class="custom_switch" />
              <span class="...slider classes..."></span>
            </label>
          </td>
          <td v-if="canManage">
            <button @click="removeInvoice(index)" class="btn btn-sm btn-outline-danger">
              <icon-x class="w-4 h-4" />
            </button>
          </td>
        </tr>
        <!-- Fila "sin facturas" -->
        <tr v-if="localInvoices.length === 0">
          <td :colspan="canManage ? 6 : 5" class="text-center text-gray-500 py-4">
            {{ $t('cases.invoice_none') }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Footer: Botones -->
  <div v-if="canManage" class="flex justify-between items-center mt-4">
    <button @click="addInvoice" class="btn btn-sm btn-outline-primary gap-1">
      <icon-plus class="w-4 h-4" />
      {{ $t('cases.invoice_add') }}
    </button>
    <button @click="save" :disabled="isSaving" class="btn btn-primary gap-1">
      <icon-save v-if="!isSaving" class="w-4 h-4" />
      <span v-if="isSaving" class="animate-spin ..."></span>
      {{ isSaving ? $t('cases.saving') : $t('cases.save') }}
    </button>
  </div>
</div>
```

#### Estado reactivo

```typescript
// Props
const props = defineProps<{
    invoices: CaseInvoice[];
    financialSummary: FinancialSummary | null;
    caseId: number;
}>();

const emit = defineEmits<{
    saved: [];
}>();

// State
const localInvoices = ref<CaseInvoice[]>([]);    // Copia local editable
const isSaving = ref(false);
const userStore = useUserStore();

// Computed
const canManage = computed(() => userStore.can('cases.manage-invoices'));

const balanceClass = computed(() => {
    if (!props.financialSummary || props.financialSummary.balance === null) return 'text-gray-500';
    return props.financialSummary.balance > 0 ? 'text-success' :
           props.financialSummary.balance < 0 ? 'text-danger' : 'text-dark';
});

// Watchers
watch(() => props.invoices, (newVal) => {
    localInvoices.value = JSON.parse(JSON.stringify(newVal));
}, { immediate: true, deep: true });
```

#### Funciones requeridas

```typescript
function addInvoice(): void {
    localInvoices.value.push({
        invoice_number: '',
        invoice_date: new Date().toISOString().split('T')[0],
        amount: 0,
        is_collected: false,
    });
}

function removeInvoice(index: number): void {
    localInvoices.value.splice(index, 1);
}

async function save(): Promise<void> {
    isSaving.value = true;
    try {
        await caseStore.bulkUpdateInvoices(props.caseId, localInvoices.value);
        success(t('cases.invoice_saved'));
        emit('saved');
    } catch (err: any) {
        if (err.response?.status === 422) {
            // Mostrar errores de validacion
            error(err.response.data.message || t('cases.invoice_save_failed'));
        } else {
            error(t('cases.invoice_save_failed'));
        }
    } finally {
        isSaving.value = false;
    }
}

function formatMoney(value: number | null): string {
    if (value === null || value === undefined) return '--';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
```

#### Integracion con show.vue

```html
<!-- En show.vue, dentro del tab content -->
<div v-else-if="activeTab === 'invoices'">
    <InvoiceTable
        :invoices="currentCase.invoices ?? []"
        :financial-summary="currentCase.financial_summary ?? null"
        :case-id="currentCase.id"
        @saved="() => caseStore.fetchCase(currentCase!.id)"
    />
</div>
```

---

## 7. Checklist de Implementacion por Fases

### Fase 0: Verificacion de Prerequisitos (campo `fees`)

- [x] `fees` input en Wizard Paso 4 (`StepDetails.vue`) — seccion "Información Financiera" ✅ Epic 3.3
- [x] `fees` input en edicion (`edit.vue`) — seccion "Información Financiera" ✅ Epic 3.3
- [x] `fees` display en consulta (`show.vue`) — tab Info, subseccion financiera ✅ Epic 3.3
- [x] `fees` columna oculta por defecto en lista (`list.vue`) via column chooser ✅ Epic 3.3
- [x] Permiso `cases.view-fees` aplicado correctamente en los 4 puntos ✅ Epic 3.3

### Fase 1: Backend Core (Migracion, Modelo, Resource) ✅

- [x] Crear migracion `create_case_invoices_table` con schema definido en seccion 2
- [x] Crear modelo `app/Models/CaseInvoice.php` con `$fillable`, `$casts`, relacion `case(): BelongsTo`
- [x] Agregar relacion `invoices(): HasMany` en `app/Models/ImmigrationCase.php`
- [x] Crear resource `app/Http/Resources/CaseInvoiceResource.php`
- [x] Modificar `app/Http/Resources/CaseResource.php`:
  - [x] Agregar `invoices` con `whenLoaded()` protegido por `cases.view-fees`
  - [x] Agregar `financial_summary` protegido por `cases.view-fees`
- [x] Ejecutar `php artisan migrate` para crear la tabla

### Fase 2: Backend Service + API (Service, Controller, Request, Rutas) ✅

- [x] Crear `app/Services/Case/CaseInvoiceService.php` con metodo `syncInvoices()`
- [x] Crear `app/Http/Requests/Case/BulkUpdateCaseInvoicesRequest.php` con validacion completa
- [x] Crear `app/Http/Controllers/Api/CaseInvoiceController.php` con metodo `bulkUpdate()`
- [x] Modificar `app/Services/Case/CaseService.php` — agregar `'invoices'` al `load()` en `getCase()`
- [x] Agregar ruta `PUT /cases/{case}/invoices` en `routes/api.php`
- [x] Agregar permiso `cases.manage-invoices` en `RolePermissionSeeder.php`
- [x] Asignar permiso a roles `admin` y `contador`

### Fase 3: Frontend Types + i18n ✅

- [x] Crear `resources/js/src/types/invoice.ts` con interfaces `CaseInvoice`, `FinancialSummary`, `BulkUpdateInvoicesData`
- [x] Modificar `resources/js/src/types/case.ts` — agregar campos `invoices?` y `financial_summary?` a `ImmigrationCase`
- [x] Agregar claves i18n en `en.json`, `es.json`, `fr.json` (16 keys: `cases.tab_invoices` + `cases.invoice_*`)

### Fase 4: Frontend Componente + Tab en show.vue ✅

- [x] Agregar metodo `bulkUpdateInvoices()` en `resources/js/src/services/caseService.ts`
- [x] Agregar action `bulkUpdateInvoices()` en `resources/js/src/stores/case.ts`
- [x] Crear `resources/js/src/views/cases/components/InvoiceTable.vue`
- [x] Modificar `resources/js/src/views/cases/show.vue`:
  - [x] Agregar tab `invoices` al array `tabs` (computed con filtrado por permiso)
  - [x] Agregar seccion de contenido con componente `<InvoiceTable>`
  - [x] Importar componente `InvoiceTable`

### Fase 5: Testing + QA ✅

- [x] `./vendor/bin/phpunit --filter=CaseTest` — 61 tests, todos pasando
- [x] `npm run build` — sin errores TypeScript ni de compilacion
- [ ] Test manual: flujo completo de agregar/editar/eliminar facturas en UI
- [ ] Test manual: balance negativo muestra warning visual
- [ ] Test manual: caso sin fees muestra balance como N/A

---

## Verificacion Final

**Fecha:** 2026-03-13

**Tests backend:** 61 tests pasando (58 CaseTest + 3 [[CaseTask]]/[[CaseType]]). Fallas pre-existentes sin cambio (ActivityLog, Profile, TwoFactor — no relacionadas).

**Frontend build:** `npm run build` — ✅ completado en ~8s sin errores TypeScript.

**Nota de implementacion:** El array `tabs` en `show.vue` fue convertido a `computed` para permitir filtrado condicional por permiso. La prop `canManage` en `InvoiceTable.vue` usa la directiva `v-can` del proyecto para control de acceso a nivel DOM.

**Archivos nuevos creados:**
- `database/migrations/2026_03_13_200000_create_case_invoices_table.php`
- `app/Models/CaseInvoice.php`
- `app/Http/Resources/CaseInvoiceResource.php`
- `app/Services/Case/CaseInvoiceService.php`
- `app/Http/Controllers/Api/CaseInvoiceController.php`
- `app/Http/Requests/Case/BulkUpdateCaseInvoicesRequest.php`
- `resources/js/src/types/invoice.ts`
- `resources/js/src/views/cases/components/InvoiceTable.vue`

**Archivos modificados:**
- `app/Models/ImmigrationCase.php` (relacion `invoices()`)
- `app/Http/Resources/CaseResource.php` (invoices + financial_summary)
- `app/Services/Case/CaseService.php` (eager load invoices)
- `routes/api.php` (nueva ruta PUT)
- `database/seeders/RolePermissionSeeder.php` (permiso `cases.manage-invoices`)
- `resources/js/src/types/case.ts` (invoices?, financial_summary?)
- `resources/js/src/services/caseService.ts` (bulkUpdateInvoices)
- `resources/js/src/stores/case.ts` (action bulkUpdateInvoices)
- `resources/js/src/views/cases/show.vue` (tab invoices + InvoiceTable)
- `resources/js/src/locales/en.json`, `es.json`, `fr.json` (16 keys invoice_*)

---

## 8. i18n Keys Necesarias

| Key | English (en) | Espanol (es) | Frances (fr) |
|-----|-------------|--------------|--------------|
| `cases.tab_invoices` | Account Statement | Estado de Cuenta | Releve de Compte |
| `cases.invoice_number` | Invoice # | N de Factura | N de Facture |
| `cases.invoice_date` | Date | Fecha | Date |
| `cases.invoice_amount` | Amount (CAD) | Monto (CAD) | Montant (CAD) |
| `cases.invoice_collected` | Collected | Cobrado | Encaisse |
| `cases.invoice_total_case` | Total Case | Total Expediente | Total Dossier |
| `cases.invoice_total_invoiced` | Total Invoiced | Total Facturado | Total Facture |
| `cases.invoice_total_collected` | Total Collected | Total Cobrado | Total Encaisse |
| `cases.invoice_balance` | Outstanding Balance | Saldo Pendiente | Solde Restant |
| `cases.invoice_add` | Add Invoice | Agregar Factura | Ajouter Facture |
| `cases.invoice_none` | No invoices registered | Sin facturas registradas | Aucune facture enregistree |
| `cases.invoice_saved` | Invoices saved successfully | Facturas guardadas exitosamente | Factures enregistrees avec succes |
| `cases.invoice_save_failed` | Failed to save invoices | Error al guardar facturas | Echec de l'enregistrement des factures |
| `cases.invoice_confirm_delete` | Remove this invoice? | Eliminar esta factura? | Supprimer cette facture? |
| `cases.invoice_duplicate_number` | Invoice number already exists | El numero de factura ya existe | Le numero de facture existe deja |
| `cases.invoice_negative_balance` | Invoiced amount exceeds total case fees | El monto facturado excede el total del expediente | Le montant facture depasse le total du dossier |

---

## Diagrama de Flujo de Datos

```
show.vue (Tab: Estado de Cuenta)
    |
    |--- onMounted/fetchCase --> GET /api/cases/{id}
    |                              |
    |                              v
    |                         CaseController::show()
    |                              |
    |                              +-- $case->load([..., 'invoices'])
    |                              +-- CaseResource (invoices, financial_summary)
    |                              |
    |                              v
    |                         Response JSON con invoices[] y financial_summary{}
    |
    |--- InvoiceTable.vue
    |       |
    |       |--- "Guardar" click --> PUT /api/cases/{id}/invoices
    |                                   |
    |                                   v
    |                              BulkUpdateCaseInvoicesRequest (validacion)
    |                                   |
    |                                   v
    |                              CaseInvoiceController::bulkUpdate()
    |                                   |
    |                                   v
    |                              CaseInvoiceService::syncInvoices()
    |                                   |
    |                                   +-- $case->invoices()->delete()
    |                                   +-- foreach: $case->invoices()->create(...)
    |                                   |
    |                                   v
    |                              Response: CaseResource (actualizado)
    |
    |--- @saved --> caseStore.fetchCase(id) --> actualiza currentCase
```

---

## Consideraciones de Seguridad

1. **Autorizacion por capas:**
   - Ruta protegida por `auth:sanctum` y middleware `tenant`
   - FormRequest valida `$this->user()->can('cases.manage-invoices')`
   - `CaseResource` protege datos financieros con `$request->user()?->can('cases.view-fees')`

2. **Aislamiento por tenant:**
   - Middleware `tenant` filtra automaticamente por `tenant_id` en el query de `cases`
   - La validacion de unicidad de invoice_number filtra explicitamente por tenant

3. **Validacion de input:**
   - Montos limitados a `decimal(10,2)` con `min:0`
   - Invoice number limitado a 50 caracteres
   - Array de invoices limitado a 100 items
   - Fechas validadas con formato `Y-m-d`

4. **Proteccion contra inyeccion:**
   - Eloquent parametriza todas las queries
   - FormRequest sanitiza inputs

---

## Notas Finales para el Implementador

1. **No crear `tenant_id` en `case_invoices`** — seguir patron de `case_tasks` donde se infiere desde `case_id`.
2. **No usar soft delete** en `case_invoices` — patron establecido para entidades operativas.
3. **Usar `array_key_exists`** (no `isset`) para detectar campos opcionales como `is_collected` en el servicio.
4. **Usar `updateQuietly`** si en el futuro se agregan campos denormalizados en `cases` que dependan de invoices.
5. **El `financial_summary`** no se persiste en BD — se calcula al vuelo en `CaseResource` usando `$this->invoices->sum('amount')`.
6. **En `CaseResource`**, para calcular `financial_summary` sin N+1, asegurarse de que `invoices` este loaded antes de acceder a `$this->invoices`. Usar `$this->whenLoaded('invoices')` y calcular solo si la relacion esta cargada.
7. **Flatpickr** (`vue-flatpickr-component`) ya esta instalado en el proyecto; usarlo para el campo de fecha en `InvoiceTable.vue`.
