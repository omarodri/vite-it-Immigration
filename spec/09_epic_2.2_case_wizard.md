# Plan de Implementación: Epic 2.2 - Case Wizard

## Metadata
- **Fecha:** 2026-02-11
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 2.2 - Case Wizard
- **PRD Coverage:** FR10-FR13, FR15a-c
- **Story Points:** 21
- **Tiempo Total Estimado:** 5-7 días (~40-56 horas)

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad | Status |
|------|--------|--------|-----------|--------|
| 1 | Backend Foundation | 8h | CRITICO | ⬜ PENDIENTE |
| 2 | Frontend Types & Services | 4h | CRITICO | ⬜ PENDIENTE |
| 3 | Wizard Container & Navigation | 6h | CRITICO | ⬜ PENDIENTE |
| 4 | Step 1: Case Type Selection | 4h | ALTO | ⬜ PENDIENTE |
| 5 | Step 2: Client Selection | 8h | ALTO | ⬜ PENDIENTE |
| 6 | Step 3: Companions Selection | 4h | MEDIO | ⬜ PENDIENTE |
| 7 | Step 4: Case Details | 4h | MEDIO | ⬜ PENDIENTE |
| 8 | Step 5: Summary & Submit | 6h | ALTO | ⬜ PENDIENTE |
| 9 | Testing & Polish | 4h | ALTO | ⬜ PENDIENTE |

---

## Estado Actual del Proyecto

### Lo que YA existe:
```
✅ app/Models/ImmigrationCase.php
   - Modelo completo con relaciones client, caseType, assignedTo
   - Scopes, accessors, constantes de status/priority

✅ app/Models/CaseType.php
   - 15 tipos de caso sembrados
   - Categorías: temporary_residence, permanent_residence, humanitarian

✅ app/Models/Client.php
   - Relación companions() HasMany
   - Relación cases() HasMany

✅ app/Models/Companion.php
   - Vinculado a clientes
   - Campos: first_name, last_name, relationship, etc.

✅ app/Services/Case/CaseService.php
   - createCase() con generación automática de case_number
   - CRUD completo

✅ app/Http/Controllers/Api/CaseController.php
   - Endpoints: index, store, show, update, destroy, assign

✅ resources/js/src/stores/case.ts
   - Pinia store con actions CRUD

✅ resources/js/src/views/cases/create.vue
   - Formulario simple (será reemplazado por wizard)
```

### Lo que FALTA crear:
```
❌ database/migrations/xxxx_create_case_companions_table.php
❌ app/Http/Controllers/Api/UserController.php::staff()
❌ resources/js/src/composables/useCaseWizard.ts
❌ resources/js/src/views/cases/wizard/CaseWizard.vue
❌ resources/js/src/views/cases/wizard/steps/StepCaseType.vue
❌ resources/js/src/views/cases/wizard/steps/StepClient.vue
❌ resources/js/src/views/cases/wizard/steps/StepCompanions.vue
❌ resources/js/src/views/cases/wizard/steps/StepDetails.vue
❌ resources/js/src/views/cases/wizard/steps/StepSummary.vue
❌ resources/js/src/views/cases/wizard/components/CaseTypeCard.vue
❌ resources/js/src/views/cases/wizard/components/ClientSearchInput.vue
❌ resources/js/src/views/cases/wizard/components/ClientCard.vue
❌ resources/js/src/views/cases/wizard/components/CompanionCheckbox.vue
❌ resources/js/src/views/cases/wizard/components/CreateClientModal.vue
```

---

## User Stories

| Story | Descripción | Puntos |
|-------|-------------|--------|
| US-2.2.1 | Wizard Step 1 - Selección de Tipo de Caso | 3 |
| US-2.2.2 | Wizard Step 2 - Selección/Creación de Cliente | 5 |
| US-2.2.3 | Wizard Step 3 - Selección de Acompañantes | 3 |
| US-2.2.4 | Wizard Step 4 - Detalles del Caso | 3 |
| US-2.2.5 | Wizard Step 5 - Resumen y Confirmación | 7 |

---

## FASE 1: Backend Foundation (8h) ⬜ PENDIENTE

### Objetivo
Crear la tabla pivot para case-companions y extender la API para soportar el wizard.

### Prerequisitos
- Epic 2.1 (Cases CRUD) completado
- Epic 1.2 (Clients) completado
- Epic 1.3 (Companions) completado

### Tareas

#### 1.1 Migración case_companions (1h)
- [ ] Crear `database/migrations/xxxx_create_case_companions_table.php`
- [ ] Campos: id, case_id, companion_id, timestamps
- [ ] Foreign keys con cascadeOnDelete
- [ ] Índice único en [case_id, companion_id]
- [ ] Ejecutar migración

```php
Schema::create('case_companions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('case_id')->constrained()->cascadeOnDelete();
    $table->foreignId('companion_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['case_id', 'companion_id']);
});
```

#### 1.2 Actualizar Modelo ImmigrationCase (1h)
- [ ] Agregar relación `companions(): BelongsToMany`
- [ ] Usar tabla pivot `case_companions`
- [ ] Incluir `withTimestamps()`

```php
public function companions(): BelongsToMany
{
    return $this->belongsToMany(Companion::class, 'case_companions')
        ->withTimestamps();
}
```

#### 1.3 Actualizar Modelo Companion (30min)
- [ ] Agregar relación `cases(): BelongsToMany`
- [ ] Relación inversa al ImmigrationCase

```php
public function cases(): BelongsToMany
{
    return $this->belongsToMany(ImmigrationCase::class, 'case_companions', 'companion_id', 'case_id')
        ->withTimestamps();
}
```

#### 1.4 Extender StoreCaseRequest (1.5h)
- [ ] Agregar regla `assigned_to` (nullable, exists:users,id)
- [ ] Agregar regla `companion_ids` (nullable, array)
- [ ] Agregar regla `companion_ids.*` (integer, exists:companions,id)
- [ ] Validar que companions pertenecen al cliente seleccionado

```php
public function rules(): array
{
    return [
        // Existentes...
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        'companion_ids' => ['nullable', 'array'],
        'companion_ids.*' => ['integer', 'exists:companions,id'],
    ];
}

// En withValidator():
$validator->after(function ($validator) {
    if ($this->companion_ids && $this->client_id) {
        $clientCompanionIds = Companion::where('client_id', $this->client_id)
            ->pluck('id')->toArray();
        foreach ($this->companion_ids as $companionId) {
            if (!in_array($companionId, $clientCompanionIds)) {
                $validator->errors()->add('companion_ids',
                    'One or more companions do not belong to the selected client.');
                break;
            }
        }
    }
});
```

#### 1.5 Actualizar CaseService (1h)
- [ ] Modificar `createCase()` para extraer y procesar `companion_ids`
- [ ] Usar `$case->companions()->attach($companionIds)` después de crear caso
- [ ] Cargar companions en la respuesta

```php
public function createCase(array $data): ImmigrationCase
{
    return DB::transaction(function () use ($data) {
        $companionIds = $data['companion_ids'] ?? [];
        unset($data['companion_ids']);

        // Lógica existente de creación...
        $case = $this->caseRepository->create($data);

        if (!empty($companionIds)) {
            $case->companions()->attach($companionIds);
        }

        return $case->load(['client', 'caseType', 'assignedTo', 'companions']);
    });
}
```

#### 1.6 Actualizar CaseResource (30min)
- [ ] Agregar `companions` a la respuesta cuando esté cargado
- [ ] Mapear datos básicos: id, full_name, relationship, relationship_label

```php
'companions' => $this->whenLoaded('companions', fn () =>
    $this->companions->map(fn ($companion) => [
        'id' => $companion->id,
        'first_name' => $companion->first_name,
        'last_name' => $companion->last_name,
        'full_name' => $companion->full_name,
        'relationship' => $companion->relationship,
        'relationship_label' => $companion->relationship_label,
    ])
),
```

#### 1.7 Crear Endpoint GET /api/users/staff (1.5h)
- [ ] Agregar método `staff()` en UserController
- [ ] Filtrar usuarios del mismo tenant
- [ ] Filtrar por permiso `cases.view`
- [ ] Retornar: id, name, email
- [ ] Agregar ruta en api.php

```php
// app/Http/Controllers/Api/UserController.php
public function staff(Request $request): JsonResponse
{
    $users = User::where('tenant_id', Auth::user()->tenant_id)
        ->whereHas('roles', function ($query) {
            $query->whereHas('permissions', function ($q) {
                $q->where('name', 'cases.view');
            });
        })
        ->select('id', 'name', 'email')
        ->orderBy('name')
        ->get();

    return response()->json(['data' => $users]);
}

// routes/api.php
Route::get('/users/staff', [UserController::class, 'staff'])->name('users.staff');
```

#### 1.8 Tests Backend (2h)
- [ ] Test: crear caso con companions
- [ ] Test: validar companions pertenecen al cliente
- [ ] Test: companions de otro cliente rechazados
- [ ] Test: endpoint /api/users/staff retorna usuarios correctos

---

## FASE 2: Frontend Types & Services (4h) ⬜ PENDIENTE

### Objetivo
Extender tipos TypeScript y servicios para soportar el wizard.

### Tareas

#### 2.1 Extender types/case.ts (1h)
- [ ] Agregar interface `CaseWizardState`
- [ ] Agregar interface `CreateCaseDataWizard` extendiendo `CreateCaseData`
- [ ] Agregar tipo `StaffUser`

```typescript
export interface CaseWizardState {
    currentStep: number;
    caseType: CaseType | null;
    client: Client | null;
    selectedCompanionIds: number[];
    details: {
        assigned_to: number | null;
        priority: CasePriority;
        language: string;
        description: string;
        hearing_date: string;
        fda_deadline: string;
        brown_sheet_date: string;
        evidence_deadline: string;
    };
    isSubmitting: boolean;
}

export interface CreateCaseDataWizard extends CreateCaseData {
    companion_ids?: number[];
    assigned_to?: number;
}

export interface StaffUser {
    id: number;
    name: string;
    email: string;
}
```

#### 2.2 Extender services/caseService.ts (30min)
- [ ] Agregar método `getStaffUsers()`
- [ ] Retornar array de StaffUser

```typescript
async getStaffUsers(): Promise<StaffUser[]> {
    const response = await api.get<{ data: StaffUser[] }>('/users/staff');
    return response.data.data;
},
```

#### 2.3 Crear composables/useCaseWizard.ts (2h)
- [ ] Estado reactivo con `reactive<CaseWizardState>`
- [ ] Computed `canProceed` para validar cada paso
- [ ] Métodos: goToStep, nextStep, prevStep
- [ ] Métodos: selectCaseType, selectClient, toggleCompanion
- [ ] Método: updateDetails
- [ ] Método: getCreateData (construir payload final)
- [ ] Método: reset

#### 2.4 Agregar traducciones i18n (30min)
- [ ] en.json: wizard keys (select_case_type, select_client, etc.)
- [ ] es.json: traducciones en español
- [ ] Keys: previous, next, step_x_of_y, select_case_type_description, etc.

---

## FASE 3: Wizard Container & Navigation (6h) ⬜ PENDIENTE

### Objetivo
Crear el contenedor principal del wizard con navegación entre pasos.

### Tareas

#### 3.1 Crear estructura de carpetas (15min)
```
resources/js/src/views/cases/wizard/
├── CaseWizard.vue
├── steps/
│   ├── StepCaseType.vue (placeholder)
│   ├── StepClient.vue (placeholder)
│   ├── StepCompanions.vue (placeholder)
│   ├── StepDetails.vue (placeholder)
│   └── StepSummary.vue (placeholder)
└── components/
```

#### 3.2 Implementar CaseWizard.vue (4h)
- [ ] Breadcrumb navigation
- [ ] Step indicators con estados: pendiente, actual, completado
- [ ] Indicadores clickeables para pasos completados
- [ ] Líneas de conexión entre pasos
- [ ] Área de contenido dinámica (`v-if` por paso)
- [ ] Botones: Previous, Cancel, Next
- [ ] Botón Next deshabilitado si `!canProceed`
- [ ] Handler `handleSubmit` para paso final

#### 3.3 Crear placeholders de pasos (1h)
- [ ] StepCaseType.vue - placeholder con título
- [ ] StepClient.vue - placeholder con título
- [ ] StepCompanions.vue - placeholder con título
- [ ] StepDetails.vue - placeholder con título
- [ ] StepSummary.vue - placeholder con título y botón submit

#### 3.4 Actualizar router (15min)
- [ ] Cambiar ruta `/cases/create` para apuntar a `wizard/CaseWizard.vue`
- [ ] Mantener meta: permission 'cases.create'

#### 3.5 Verificar navegación funciona (30min)
- [ ] Probar avance/retroceso entre pasos
- [ ] Probar indicadores de progreso
- [ ] Probar botón cancelar

---

## FASE 4: Step 1 - Case Type Selection (4h) ⬜ PENDIENTE

### Objetivo
Implementar US-2.2.1: selección visual de tipo de caso por categoría.

### Tareas

#### 4.1 Crear CaseTypeCard.vue (1.5h)
- [ ] Props: caseType, selected
- [ ] Emit: select
- [ ] Diseño: card con borde, icono, nombre, código
- [ ] Estado selected: borde primary, fondo primary/10
- [ ] Hover effect

#### 4.2 Implementar StepCaseType.vue (2.5h)
- [ ] Cargar case types del store al montar
- [ ] Tabs/botones para filtrar por categoría
- [ ] Grid de CaseTypeCard (3 columnas en desktop)
- [ ] Al seleccionar: llamar `wizard.selectCaseType()`
- [ ] Mostrar descripción del tipo seleccionado
- [ ] Mensaje si no hay tipos en categoría

---

## FASE 5: Step 2 - Client Selection (8h) ⬜ PENDIENTE

### Objetivo
Implementar US-2.2.2: búsqueda de cliente con autocomplete y creación inline.

### Tareas

#### 5.1 Crear ClientSearchInput.vue (3h)
- [ ] Input con debounce (300ms)
- [ ] Dropdown de resultados
- [ ] Llamar API de búsqueda de clientes
- [ ] Mostrar: nombre, email, status badge
- [ ] Emit: select(client)
- [ ] Loading state mientras busca
- [ ] "No results" message

#### 5.2 Crear ClientCard.vue (1h)
- [ ] Props: client
- [ ] Mostrar: avatar/iniciales, nombre completo, email, teléfono
- [ ] Badge de status
- [ ] Botón "Change" para cambiar selección

#### 5.3 Crear CreateClientModal.vue (2.5h)
- [ ] Modal con HeadlessUI Dialog
- [ ] Formulario básico: first_name, last_name, email, phone
- [ ] Validación inline
- [ ] Submit crea cliente via API
- [ ] On success: emit client creado, cerrar modal

#### 5.4 Implementar StepClient.vue (1.5h)
- [ ] Si no hay cliente: mostrar ClientSearchInput + botón "Create New"
- [ ] Si hay cliente: mostrar ClientCard
- [ ] Botón "Create New" abre CreateClientModal
- [ ] Al crear/seleccionar: `wizard.selectClient(client)`

---

## FASE 6: Step 3 - Companions Selection (4h) ⬜ PENDIENTE

### Objetivo
Implementar US-2.2.3: selección de acompañantes del cliente.

### Tareas

#### 6.1 Crear CompanionCheckbox.vue (1h)
- [ ] Props: companion, selected
- [ ] Emit: toggle
- [ ] Checkbox con label: nombre, relación, edad si disponible
- [ ] Badge de relación

#### 6.2 Implementar StepCompanions.vue (3h)
- [ ] Cargar companions del cliente seleccionado
- [ ] Loading state
- [ ] Lista de CompanionCheckbox
- [ ] Empty state si cliente no tiene acompañantes
- [ ] Mensaje: "Este paso es opcional"
- [ ] Al toggle: `wizard.toggleCompanion(id)`
- [ ] Contador de seleccionados

---

## FASE 7: Step 4 - Case Details (4h) ⬜ PENDIENTE

### Objetivo
Implementar US-2.2.4: configuración de detalles del caso.

### Tareas

#### 7.1 Implementar StepDetails.vue (4h)
- [ ] Cargar staff users para dropdown
- [ ] Select: Asignar a (assigned_to) - default: usuario actual
- [ ] Select: Prioridad (low, medium, high, urgent)
- [ ] Select: Idioma (es, en, fr)
- [ ] Date pickers:
  - [ ] Fecha de audiencia (hearing_date)
  - [ ] FDA Deadline (fda_deadline)
  - [ ] Brown Sheet Date (brown_sheet_date)
  - [ ] Evidence Deadline (evidence_deadline)
- [ ] Textarea: Descripción/notas
- [ ] Usar Flatpickr para date pickers
- [ ] Guardar cambios en `wizard.updateDetails()`

---

## FASE 8: Step 5 - Summary & Submit (6h) ⬜ PENDIENTE

### Objetivo
Implementar US-2.2.5: resumen y confirmación final.

### Tareas

#### 8.1 Implementar StepSummary.vue (6h)
- [ ] Sección: Tipo de Caso
  - [ ] Mostrar nombre, código, categoría
  - [ ] Botón "Edit" → goToStep(1)
- [ ] Sección: Cliente
  - [ ] Mostrar nombre, email, teléfono
  - [ ] Botón "Edit" → goToStep(2)
- [ ] Sección: Acompañantes
  - [ ] Lista de nombres seleccionados o "None selected"
  - [ ] Botón "Edit" → goToStep(3)
- [ ] Sección: Detalles
  - [ ] Mostrar asignado, prioridad, idioma, fechas
  - [ ] Botón "Edit" → goToStep(4)
- [ ] Sección placeholder: "Auto-Generated Tasks" (coming soon)
- [ ] Sección placeholder: "Folder Structure" (coming soon)
- [ ] Botón "Create Case" principal
  - [ ] Loading state mientras submitting
  - [ ] Emit: submit
- [ ] Estilos de tarjetas con borde y shadow

---

## FASE 9: Testing & Polish (4h) ⬜ PENDIENTE

### Objetivo
Asegurar calidad y pulir detalles.

### Tareas

#### 9.1 Testing Manual E2E (2h)
- [ ] Flujo completo: crear caso con todos los datos
- [ ] Flujo sin acompañantes
- [ ] Crear cliente inline durante wizard
- [ ] Navegación hacia atrás y edición
- [ ] Verificar caso creado en base de datos
- [ ] Verificar companions attached

#### 9.2 Error Handling (1h)
- [ ] Errores de API mostrados al usuario
- [ ] Validación por paso
- [ ] Manejo de conexión perdida

#### 9.3 Responsive & Polish (1h)
- [ ] Revisar en móvil
- [ ] Revisar en tablet
- [ ] Ajustar espaciados y tipografía
- [ ] Verificar dark mode

---

## Contrato de API

### POST /api/cases (Extendido)

**Request:**
```json
{
    "client_id": 123,
    "case_type_id": 1,
    "assigned_to": 5,
    "companion_ids": [1, 2, 3],
    "priority": "medium",
    "language": "es",
    "description": "Optional description",
    "hearing_date": "2026-06-15",
    "fda_deadline": "2026-05-01",
    "brown_sheet_date": null,
    "evidence_deadline": null
}
```

**Response:**
```json
{
    "message": "Case created successfully.",
    "data": {
        "id": 42,
        "case_number": "2026-ASYLUM-00042",
        "client_id": 123,
        "case_type_id": 1,
        "assigned_to": 5,
        "status": "active",
        "priority": "medium",
        "companions": [
            { "id": 1, "first_name": "Maria", "last_name": "Garcia", "relationship": "spouse" },
            { "id": 2, "first_name": "Carlos", "last_name": "Garcia", "relationship": "child" }
        ]
    }
}
```

### GET /api/users/staff

**Response:**
```json
{
    "data": [
        { "id": 1, "name": "Admin User", "email": "admin@example.com" },
        { "id": 5, "name": "Consultant One", "email": "consultant1@example.com" }
    ]
}
```

---

## Dependencias

- **Completadas:**
  - Epic 2.1: Expedientes Core
  - Epic 1.2: Clientes Management
  - Epic 1.3: Acompañantes Management

- **Librerías (ya instaladas):**
  - HeadlessUI (modales, transiciones)
  - Flatpickr (date pickers)
  - vue-i18n (traducciones)

---

## Definition of Done

- [ ] Migración ejecutada sin errores
- [ ] Todos los endpoints funcionando
- [ ] Tests backend pasando
- [ ] Wizard navegable de inicio a fin
- [ ] Caso creado con companions correctamente
- [ ] Redirect a detalle del caso tras crear
- [ ] Traducciones en ES y EN
- [ ] Sin errores en consola
- [ ] Responsive verificado
- [ ] Code review aprobado

---

## Riesgos y Mitigaciones

| Riesgo | Impacto | Probabilidad | Mitigación |
|--------|---------|--------------|------------|
| Búsqueda de clientes lenta | Medio | Bajo | Debounce 300ms, limitar resultados a 10 |
| Estado wizard perdido en refresh | Medio | Medio | Considerar localStorage (futuro) |
| Problemas layout mobile | Medio | Medio | Testear responsive temprano |
| Validaciones complejas fallan | Bajo | Medio | Mensajes de error claros por paso |
