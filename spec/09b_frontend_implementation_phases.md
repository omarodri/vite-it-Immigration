# Epic 2.2 - Case Wizard: Plan de Implementacion Frontend

## Resumen Ejecutivo

Este documento detalla el plan de implementacion frontend para el Case Wizard de creacion de expedientes migratorios. El wizard consta de 5 pasos que guian al usuario en la seleccion del tipo de caso, cliente, acompanantes, detalles y confirmacion final.

**Tiempo estimado total:** 36 horas

---

## 1. Analisis de Archivos Existentes

### 1.1 Archivos a Revisar como Referencia

#### Tipos y Modelos (Ya Existentes)
| Archivo | Proposito | Estado |
|---------|-----------|--------|
| `resources/js/src/types/case.ts` | Interfaces ImmigrationCase, CaseType, CreateCaseData | Completo |
| `resources/js/src/types/client.ts` | Interfaces Client, CreateClientData | Completo |
| `resources/js/src/types/companion.ts` | Interfaces Companion, RelationshipType | Completo |
| `resources/js/src/types/pagination.ts` | Interfaces de paginacion | Completo |

#### Servicios API (Ya Existentes)
| Archivo | Proposito | Metodos Relevantes |
|---------|-----------|-------------------|
| `resources/js/src/services/caseService.ts` | Operaciones de casos | getCaseTypes(), createCase() |
| `resources/js/src/services/clientService.ts` | Operaciones de clientes | getClients(), createClient() |
| `resources/js/src/services/companionService.ts` | Operaciones de acompanantes | getCompanions() |

#### Stores Pinia (Ya Existentes)
| Archivo | Proposito | Acciones Relevantes |
|---------|-----------|---------------------|
| `resources/js/src/stores/case.ts` | Estado de casos | fetchCaseTypes(), createCase() |
| `resources/js/src/stores/client.ts` | Estado de clientes | fetchClients(), createClient() |
| `resources/js/src/stores/companion.ts` | Estado de acompanantes | fetchCompanions() |

#### Composables de Referencia
| Archivo | Proposito | Patron a Seguir |
|---------|-----------|-----------------|
| `resources/js/src/composables/useNotification.ts` | Notificaciones toast | Estructura de composable |
| `resources/js/src/composables/useDebounce.ts` | Debounce para busquedas | Debounce pattern |
| `resources/js/src/composables/use-meta.ts` | Meta tags de pagina | useMeta() |

#### Componentes de Referencia UI
| Archivo | Elementos a Reutilizar |
|---------|----------------------|
| `resources/js/src/views/cases/list.vue` | DataTable, filtros, badges, skeleton loaders |
| `resources/js/src/views/cases/create.vue` | Formularios, validacion, manejo de errores |
| `resources/js/src/views/clients/show.vue` | Tabs, modales HeadlessUI, cards de companeros |
| `resources/js/src/views/forms/wizards.vue` | vue3-form-wizard, FormWizard, TabContent |

### 1.2 Patrones Existentes a Seguir

#### Estructura de Componentes Vue
```vue
<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useNotification } from '@/composables/useNotification';

useMeta({ title: 'Page Title' });
const { t } = useI18n();
const { success, error } = useNotification();
</script>
```

#### Manejo de Modales (HeadlessUI)
- Usar `Dialog`, `DialogPanel`, `TransitionRoot`, `TransitionChild` de `@headlessui/vue`
- Patron visible en `views/clients/show.vue` lineas 411-589

#### Estilos de Badges
```typescript
const getStatusBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
    };
    return classes[status] || 'badge-outline-primary';
};
```

#### Skeleton Loaders
- Usar `animate-pulse` con divs de fondo gris
- Patron visible en `views/cases/list.vue` lineas 130-163

---

## 2. Estructura de Directorios a Crear

```
resources/js/src/
├── views/cases/wizard/
│   ├── CaseWizard.vue              # Container principal del wizard
│   ├── steps/
│   │   ├── StepCaseType.vue        # Paso 1: Seleccion de tipo de caso
│   │   ├── StepClient.vue          # Paso 2: Busqueda/seleccion de cliente
│   │   ├── StepCompanions.vue      # Paso 3: Seleccion de acompanantes
│   │   ├── StepDetails.vue         # Paso 4: Detalles del caso
│   │   └── StepSummary.vue         # Paso 5: Resumen y confirmacion
│   └── components/
│       ├── CaseTypeCard.vue        # Card para mostrar tipo de caso
│       ├── ClientSearchInput.vue   # Input de busqueda de clientes
│       ├── ClientCard.vue          # Card de cliente seleccionado
│       ├── CompanionCheckbox.vue   # Checkbox para acompanante
│       ├── WizardProgress.vue      # Indicador de progreso personalizado
│       └── CreateClientModal.vue   # Modal para crear cliente rapido
├── composables/
│   └── useCaseWizard.ts            # Composable principal del wizard
└── types/
    └── wizard.ts                   # Tipos especificos del wizard
```

---

## 3. Fases de Implementacion

### FASE 1: Tipos e Infraestructura Base (2h)
**Dependencias:** Ninguna

#### 3.1.1 Crear tipos del wizard
**Archivo:** `resources/js/src/types/wizard.ts`

```typescript
// Interfaces a definir:
export interface WizardState {
    currentStep: number;
    caseTypeId: number | null;
    clientId: number | null;
    selectedCompanionIds: number[];
    caseDetails: CaseDetailsForm;
    isSubmitting: boolean;
    errors: Record<string, string[]>;
}

export interface CaseDetailsForm {
    priority: CasePriority;
    language: string;
    description: string;
    hearing_date: string;
    fda_deadline: string;
    brown_sheet_date: string;
    evidence_deadline: string;
}

export interface WizardStep {
    id: number;
    key: string;
    title: string;
    icon: string;
    isValid: boolean;
    isCompleted: boolean;
}
```

**Checklist de verificacion:**
- [x] Interfaces WizardState, CaseDetailsForm, WizardStep creadas
- [x] Exportaciones correctas
- [x] Sin errores de TypeScript

---

### FASE 2: Composable useCaseWizard (4h)
**Dependencias:** FASE 1

#### 3.2.1 Crear composable principal
**Archivo:** `resources/js/src/composables/useCaseWizard.ts`

**Responsabilidades:**
1. Manejar estado del wizard (reactive)
2. Validacion por paso
3. Navegacion entre pasos
4. Persistencia temporal (sessionStorage)
5. Envio final del formulario

**Estructura:**
```typescript
export function useCaseWizard() {
    // State
    const state = reactive<WizardState>({...});
    const steps = computed<WizardStep[]>(() => [...]);

    // Navigation
    const canGoNext = computed(() => boolean);
    const canGoPrev = computed(() => boolean);
    const goToStep = (step: number) => void;
    const nextStep = () => void;
    const prevStep = () => void;

    // Validation
    const validateCurrentStep = () => boolean;
    const getStepErrors = (step: number) => string[];

    // Data setters
    const setCaseType = (id: number) => void;
    const setClient = (id: number) => void;
    const toggleCompanion = (id: number) => void;
    const updateDetails = (data: Partial<CaseDetailsForm>) => void;

    // Persistence
    const saveToSession = () => void;
    const loadFromSession = () => void;
    const clearSession = () => void;

    // Submission
    const submit = async () => Promise<ImmigrationCase>;
    const reset = () => void;

    return { state, steps, ... };
}
```

**Checklist de verificacion:**
- [x] Estado reactivo funcionando
- [x] Navegacion entre pasos
- [x] Validacion por paso
- [x] Persistencia en sessionStorage
- [x] Funcion submit conectada al store

---

### FASE 3: Wizard Container y Navegacion (6h)
**Dependencias:** FASE 2

#### 3.3.1 Crear componente WizardProgress
**Archivo:** `resources/js/src/views/cases/wizard/components/WizardProgress.vue`

**Caracteristicas:**
- Indicador visual de pasos (circulos conectados con lineas)
- Estados: pendiente, actual, completado
- Responsive (horizontal en desktop, vertical en mobile)
- Iconos personalizados por paso

**Props:**
```typescript
interface Props {
    steps: WizardStep[];
    currentStep: number;
}
```

#### 3.3.2 Crear componente CaseWizard
**Archivo:** `resources/js/src/views/cases/wizard/CaseWizard.vue`

**Estructura:**
```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <!-- WizardProgress -->
        <div class="panel">
            <!-- Contenido dinamico del paso actual -->
            <component :is="currentStepComponent" />

            <!-- Navegacion -->
            <div class="flex justify-between mt-6 pt-6 border-t">
                <button @click="prevStep" :disabled="!canGoPrev">
                    {{ $t('wizard.previous') }}
                </button>
                <button v-if="!isLastStep" @click="nextStep" :disabled="!canGoNext">
                    {{ $t('wizard.next') }}
                </button>
                <button v-else @click="submit" :disabled="isSubmitting">
                    {{ $t('wizard.create_case') }}
                </button>
            </div>
        </div>
    </div>
</template>
```

#### 3.3.3 Agregar ruta al router
**Archivo:** `resources/js/src/router/index.ts`

```typescript
{
    path: '/cases/wizard',
    name: 'case-wizard',
    component: () => import('../views/cases/wizard/CaseWizard.vue'),
    meta: {
        requiresAuth: true,
        permission: 'cases.create'
    }
}
```

**Checklist de verificacion:**
- [x] WizardProgress renderiza correctamente
- [x] CaseWizard carga dinamicamente los pasos
- [x] Navegacion prev/next funciona
- [x] Ruta accesible en /cases/wizard
- [x] Breadcrumb visible

---

### FASE 4: Step 1 - Seleccion de Tipo de Caso (4h)
**Dependencias:** FASE 3

#### 3.4.1 Crear componente CaseTypeCard
**Archivo:** `resources/js/src/views/cases/wizard/components/CaseTypeCard.vue`

**Props:**
```typescript
interface Props {
    caseType: CaseType;
    isSelected: boolean;
}

interface Emits {
    (e: 'select', id: number): void;
}
```

**Diseno:**
- Card con borde que cambia color al seleccionar
- Icono representativo de la categoria
- Nombre del tipo de caso
- Descripcion truncada
- Badge de categoria (temporary_residence, permanent_residence, humanitarian)

#### 3.4.2 Crear StepCaseType
**Archivo:** `resources/js/src/views/cases/wizard/steps/StepCaseType.vue`

**Funcionalidad:**
1. Cargar tipos de caso desde store
2. Agrupar por categoria
3. Grid de cards seleccionables
4. Solo una seleccion permitida
5. Filtro de busqueda opcional

**Layout:**
```vue
<template>
    <div>
        <h3>{{ $t('wizard.step1.title') }}</h3>
        <p class="text-gray-500 mb-6">{{ $t('wizard.step1.description') }}</p>

        <!-- Agrupados por categoria -->
        <div v-for="category in categories" :key="category">
            <h4 class="font-semibold mb-3">{{ $t(`case_types.${category}`) }}</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <CaseTypeCard
                    v-for="type in typesByCategory[category]"
                    :key="type.id"
                    :case-type="type"
                    :is-selected="selectedTypeId === type.id"
                    @select="selectCaseType"
                />
            </div>
        </div>
    </div>
</template>
```

**Checklist de verificacion:**
- [x] Tipos de caso se cargan del store
- [x] Cards se renderizan agrupadas por categoria
- [x] Seleccion visual funciona
- [x] Solo un tipo seleccionado a la vez
- [x] Puede avanzar al siguiente paso

---

### FASE 5: Step 2 - Seleccion de Cliente (8h)
**Dependencias:** FASE 4

#### 3.5.1 Crear componente ClientSearchInput
**Archivo:** `resources/js/src/views/cases/wizard/components/ClientSearchInput.vue`

**Caracteristicas:**
- Input con icono de busqueda
- Debounce de 300ms
- Dropdown con resultados
- Indicador de carga
- Opcion "Crear nuevo cliente"

**Props/Emits:**
```typescript
interface Props {
    modelValue: string;
    loading?: boolean;
}

interface Emits {
    (e: 'update:modelValue', value: string): void;
    (e: 'search', query: string): void;
    (e: 'select', client: Client): void;
    (e: 'create-new'): void;
}
```

#### 3.5.2 Crear componente ClientCard
**Archivo:** `resources/js/src/views/cases/wizard/components/ClientCard.vue`

**Muestra:**
- Avatar con iniciales
- Nombre completo
- Email y telefono
- Status badge
- Nacionalidad
- Casos activos (count)
- Boton para cambiar seleccion

#### 3.5.3 Crear componente CreateClientModal
**Archivo:** `resources/js/src/views/cases/wizard/components/CreateClientModal.vue`

**Campos minimos:**
- first_name (requerido)
- last_name (requerido)
- email
- phone
- nationality
- language

**Basado en:** Modal de companion en `views/clients/show.vue`

#### 3.5.4 Crear StepClient
**Archivo:** `resources/js/src/views/cases/wizard/steps/StepClient.vue`

**Flujo:**
1. Mostrar buscador de clientes
2. Al escribir, buscar con debounce
3. Mostrar resultados en dropdown
4. Al seleccionar, mostrar ClientCard
5. Opcion de "Crear nuevo cliente"
6. Modal para creacion rapida

**Estados:**
- Sin cliente seleccionado (mostrar buscador)
- Cliente seleccionado (mostrar card + opcion cambiar)
- Modal de creacion abierto

**Checklist de verificacion:**
- [x] Busqueda de clientes funciona
- [x] Debounce implementado
- [x] Dropdown con resultados
- [x] Seleccion de cliente funciona
- [x] Card de cliente seleccionado
- [x] Modal de crear cliente abre/cierra
- [x] Creacion de cliente funciona
- [x] Cliente creado se selecciona automaticamente

---

### FASE 6: Step 3 - Seleccion de Acompanantes (4h)
**Dependencias:** FASE 5

#### 3.6.1 Crear componente CompanionCheckbox
**Archivo:** `resources/js/src/views/cases/wizard/components/CompanionCheckbox.vue`

**Props:**
```typescript
interface Props {
    companion: Companion;
    isSelected: boolean;
}
```

**Muestra:**
- Checkbox
- Avatar con iniciales
- Nombre completo
- Relacion (spouse, child, etc.)
- Edad (si disponible)

#### 3.6.2 Crear StepCompanions
**Archivo:** `resources/js/src/views/cases/wizard/steps/StepCompanions.vue`

**Funcionalidad:**
1. Cargar acompanantes del cliente seleccionado
2. Mostrar lista con checkboxes
3. Seleccion multiple permitida
4. Paso opcional (puede continuar sin seleccion)
5. Si no hay acompanantes, mostrar mensaje informativo

**Layout:**
```vue
<template>
    <div>
        <h3>{{ $t('wizard.step3.title') }}</h3>
        <p class="text-gray-500 mb-6">{{ $t('wizard.step3.description') }}</p>

        <div v-if="loading" class="animate-pulse">...</div>

        <div v-else-if="companions.length === 0" class="text-center py-10">
            <icon-users class="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <p>{{ $t('wizard.step3.no_companions') }}</p>
            <p class="text-sm text-gray-500">{{ $t('wizard.step3.skip_message') }}</p>
        </div>

        <div v-else class="space-y-3">
            <CompanionCheckbox
                v-for="companion in companions"
                :key="companion.id"
                :companion="companion"
                :is-selected="isSelected(companion.id)"
                @toggle="toggleCompanion(companion.id)"
            />
        </div>

        <div class="mt-4 p-3 bg-info/10 rounded-lg">
            <p class="text-sm">
                <strong>{{ selectedCount }}</strong> {{ $t('wizard.step3.companions_selected') }}
            </p>
        </div>
    </div>
</template>
```

**Checklist de verificacion:**
- [x] Acompanantes se cargan del cliente seleccionado
- [x] Checkboxes funcionan
- [x] Seleccion multiple funciona
- [x] Contador de seleccionados
- [x] Mensaje cuando no hay acompanantes
- [x] Puede continuar sin seleccion

---

### FASE 7: Step 4 - Detalles del Caso (4h)
**Dependencias:** FASE 6

#### 3.7.1 Crear StepDetails
**Archivo:** `resources/js/src/views/cases/wizard/steps/StepDetails.vue`

**Campos del formulario:**

| Campo | Tipo | Requerido | Descripcion |
|-------|------|-----------|-------------|
| priority | select | No | Prioridad (urgent, high, medium, low) |
| language | select | No | Idioma del caso (es, en, fr) |
| description | textarea | No | Descripcion/notas del caso |
| hearing_date | date | No | Fecha de audiencia |
| fda_deadline | date | No | Fecha limite FDA |
| brown_sheet_date | date | No | Fecha brown sheet |
| evidence_deadline | date | No | Fecha limite evidencia |

**Layout:**
```vue
<template>
    <div>
        <h3>{{ $t('wizard.step4.title') }}</h3>
        <p class="text-gray-500 mb-6">{{ $t('wizard.step4.description') }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Columna izquierda: Priority, Language, Description -->
            <div class="space-y-5">
                <!-- Priority -->
                <div>
                    <label class="block text-sm font-medium mb-2">
                        {{ $t('cases.priority') }}
                    </label>
                    <select v-model="form.priority" class="form-select">
                        <option value="medium">{{ $t('cases.medium') }}</option>
                        <option value="low">{{ $t('cases.low') }}</option>
                        <option value="high">{{ $t('cases.high') }}</option>
                        <option value="urgent">{{ $t('cases.urgent') }}</option>
                    </select>
                </div>

                <!-- Language -->
                <!-- Description -->
            </div>

            <!-- Columna derecha: Fechas -->
            <div class="space-y-5">
                <!-- hearing_date -->
                <!-- fda_deadline -->
                <!-- brown_sheet_date -->
                <!-- evidence_deadline -->
            </div>
        </div>
    </div>
</template>
```

**Checklist de verificacion:**
- [x] Todos los campos renderizan
- [x] Valores por defecto correctos
- [x] Validacion de fechas (no pasadas)
- [x] Datos se guardan en wizard state
- [x] Puede avanzar al resumen

---

### FASE 8: Step 5 - Resumen y Envio (6h)
**Dependencias:** FASE 7

#### 3.8.1 Crear StepSummary
**Archivo:** `resources/js/src/views/cases/wizard/steps/StepSummary.vue`

**Secciones:**
1. **Tipo de Caso** - Card con nombre, codigo, categoria
2. **Cliente Principal** - Card con datos del cliente
3. **Acompanantes** - Lista de acompanantes seleccionados (si hay)
4. **Detalles del Caso** - Grid con prioridad, idioma, fechas
5. **Descripcion** - Texto completo (si existe)

**Acciones:**
- Boton "Editar" por seccion (vuelve al paso correspondiente)
- Boton "Crear Caso" (submit)
- Indicador de carga durante submit

**Layout:**
```vue
<template>
    <div>
        <h3>{{ $t('wizard.step5.title') }}</h3>
        <p class="text-gray-500 mb-6">{{ $t('wizard.step5.description') }}</p>

        <div class="space-y-6">
            <!-- Seccion: Tipo de Caso -->
            <div class="panel">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold">{{ $t('wizard.step5.case_type') }}</h4>
                    <button @click="goToStep(1)" class="text-primary text-sm">
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <CaseTypeCard :case-type="selectedCaseType" :is-selected="false" />
            </div>

            <!-- Seccion: Cliente -->
            <div class="panel">
                <div class="flex justify-between items-center mb-4">
                    <h4 class="font-semibold">{{ $t('wizard.step5.client') }}</h4>
                    <button @click="goToStep(2)" class="text-primary text-sm">
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <ClientCard :client="selectedClient" :show-actions="false" />
            </div>

            <!-- Seccion: Acompanantes -->
            <!-- Seccion: Detalles -->
            <!-- Seccion: Descripcion -->
        </div>

        <!-- Alerta de confirmacion -->
        <div class="mt-6 p-4 bg-warning/10 rounded-lg">
            <p class="text-sm">
                {{ $t('wizard.step5.confirmation_message') }}
            </p>
        </div>
    </div>
</template>
```

#### 3.8.2 Implementar logica de submit
**En:** `useCaseWizard.ts`

**Flujo:**
1. Validar todos los pasos
2. Construir payload CreateCaseData
3. Llamar a caseStore.createCase()
4. Limpiar sessionStorage
5. Redirect a /cases/:id con mensaje de exito

**Manejo de errores:**
- Mostrar errores de validacion del backend
- Permitir corregir y reintentar
- No perder datos del wizard

**Checklist de verificacion:**
- [x] Resumen muestra todos los datos
- [x] Botones "Editar" navegan correctamente
- [x] Submit funciona
- [x] Loading state durante submit
- [x] Errores se muestran correctamente
- [x] Redirect al caso creado
- [x] Mensaje de exito
- [x] SessionStorage se limpia

---

## 4. Traducciones i18n

### 4.1 Claves a Agregar en `en.json`

```json
{
    "wizard": {
        "title": "Create New Case",
        "previous": "Previous",
        "next": "Next",
        "create_case": "Create Case",
        "edit": "Edit",
        "creating": "Creating...",

        "step1": {
            "title": "Select Case Type",
            "description": "Choose the type of immigration case you want to create"
        },
        "step2": {
            "title": "Select Client",
            "description": "Search and select the primary client for this case",
            "search_placeholder": "Search by name, email or phone...",
            "create_new": "Create New Client",
            "no_results": "No clients found",
            "change_client": "Change Client"
        },
        "step3": {
            "title": "Select Companions",
            "description": "Select family members to include in this case (optional)",
            "no_companions": "This client has no registered companions",
            "skip_message": "You can continue without selecting companions",
            "companions_selected": "companions selected"
        },
        "step4": {
            "title": "Case Details",
            "description": "Enter additional details for this case (all fields optional)"
        },
        "step5": {
            "title": "Review & Confirm",
            "description": "Review the case information before creating",
            "case_type": "Case Type",
            "client": "Client",
            "companions": "Companions",
            "details": "Case Details",
            "confirmation_message": "Please review all information before creating the case. You can edit any section by clicking the Edit button.",
            "no_companions_selected": "No companions selected"
        },

        "success": {
            "case_created": "Case created successfully",
            "client_created": "Client created successfully"
        },
        "errors": {
            "select_case_type": "Please select a case type",
            "select_client": "Please select a client",
            "create_failed": "Failed to create case"
        }
    },

    "case_types": {
        "temporary_residence": "Temporary Residence",
        "permanent_residence": "Permanent Residence",
        "humanitarian": "Humanitarian"
    }
}
```

### 4.2 Claves a Agregar en `es.json`

```json
{
    "wizard": {
        "title": "Crear Nuevo Expediente",
        "previous": "Anterior",
        "next": "Siguiente",
        "create_case": "Crear Expediente",
        "edit": "Editar",
        "creating": "Creando...",

        "step1": {
            "title": "Seleccionar Tipo de Caso",
            "description": "Elija el tipo de caso migratorio que desea crear"
        },
        "step2": {
            "title": "Seleccionar Cliente",
            "description": "Busque y seleccione el cliente principal para este caso",
            "search_placeholder": "Buscar por nombre, email o telefono...",
            "create_new": "Crear Nuevo Cliente",
            "no_results": "No se encontraron clientes",
            "change_client": "Cambiar Cliente"
        },
        "step3": {
            "title": "Seleccionar Acompanantes",
            "description": "Seleccione familiares para incluir en este caso (opcional)",
            "no_companions": "Este cliente no tiene acompanantes registrados",
            "skip_message": "Puede continuar sin seleccionar acompanantes",
            "companions_selected": "acompanantes seleccionados"
        },
        "step4": {
            "title": "Detalles del Caso",
            "description": "Ingrese detalles adicionales para este caso (todos los campos opcionales)"
        },
        "step5": {
            "title": "Revisar y Confirmar",
            "description": "Revise la informacion del caso antes de crear",
            "case_type": "Tipo de Caso",
            "client": "Cliente",
            "companions": "Acompanantes",
            "details": "Detalles del Caso",
            "confirmation_message": "Por favor revise toda la informacion antes de crear el expediente. Puede editar cualquier seccion haciendo clic en el boton Editar.",
            "no_companions_selected": "Sin acompanantes seleccionados"
        },

        "success": {
            "case_created": "Expediente creado exitosamente",
            "client_created": "Cliente creado exitosamente"
        },
        "errors": {
            "select_case_type": "Por favor seleccione un tipo de caso",
            "select_client": "Por favor seleccione un cliente",
            "create_failed": "Error al crear el expediente"
        }
    },

    "case_types": {
        "temporary_residence": "Residencia Temporal",
        "permanent_residence": "Residencia Permanente",
        "humanitarian": "Humanitario"
    }
}
```

---

## 5. Dependencias entre Componentes

```
FASE 1: types/wizard.ts
    │
    ▼
FASE 2: composables/useCaseWizard.ts
    │
    ▼
FASE 3: CaseWizard.vue + WizardProgress.vue + router
    │
    ├─────────────────────────────────────────────┐
    ▼                                             │
FASE 4: StepCaseType.vue                          │
    │   └── CaseTypeCard.vue                      │
    ▼                                             │
FASE 5: StepClient.vue                            │
    │   ├── ClientSearchInput.vue                 │
    │   ├── ClientCard.vue                        │
    │   └── CreateClientModal.vue                 │
    ▼                                             │
FASE 6: StepCompanions.vue                        │
    │   └── CompanionCheckbox.vue                 │
    ▼                                             │
FASE 7: StepDetails.vue                           │
    ▼                                             │
FASE 8: StepSummary.vue ◄─────────────────────────┘
        └── (reutiliza CaseTypeCard, ClientCard)
```

---

## 6. Checklist Final de Verificacion

### 6.1 Funcionalidad Core
- [x] Wizard navega correctamente entre todos los pasos
- [x] Validacion funciona en cada paso
- [x] Estado se persiste en sessionStorage
- [x] Creacion de caso funciona end-to-end
- [x] Redirect correcto despues de crear

### 6.2 UX/UI
- [x] Progress indicator muestra estado correcto
- [x] Botones prev/next deshabilitados apropiadamente
- [x] Loading states visibles
- [x] Mensajes de error claros
- [x] Responsive en mobile

### 6.3 i18n
- [x] Todas las strings traducidas en en.json
- [x] Todas las strings traducidas en es.json
- [x] Sin hardcoded strings en templates

### 6.4 Accesibilidad
- [x] Labels en todos los inputs
- [x] aria-labels en botones de icono
- [x] Tab navigation funciona
- [x] Screen reader friendly

### 6.5 Permisos
- [x] Ruta protegida con permission: 'cases.create'
- [x] Modal de cliente respeta permission: 'clients.create'

---

## 7. Estimacion de Tiempo por Fase

| Fase | Componentes | Tiempo Estimado |
|------|-------------|-----------------|
| 1 | Types & Infrastructure | 2h |
| 2 | useCaseWizard composable | 4h |
| 3 | CaseWizard + WizardProgress + Router | 6h |
| 4 | StepCaseType + CaseTypeCard | 4h |
| 5 | StepClient + Search + Card + Modal | 8h |
| 6 | StepCompanions + CompanionCheckbox | 4h |
| 7 | StepDetails | 4h |
| 8 | StepSummary + Submit logic | 6h |
| **Total** | | **38h** |

---

## 8. Notas de Implementacion

### 8.1 Libreria de Wizard
Se recomienda **NO** usar `vue3-form-wizard` directamente para el Case Wizard porque:
- Necesitamos control total sobre la validacion
- Los pasos tienen logica asincrona (carga de datos)
- Queremos un diseno mas personalizado

En su lugar, implementar un wizard custom con:
- Composable para estado y navegacion
- Componentes de paso como `<component :is="...">`
- Progress indicator personalizado

### 8.2 Manejo de Estado
```typescript
// El estado del wizard debe ser limpiado cuando:
// 1. Se crea el caso exitosamente
// 2. El usuario navega fuera del wizard (con confirmacion)
// 3. El usuario hace logout

// Patron para beforeRouteLeave:
onBeforeRouteLeave((to, from, next) => {
    if (hasUnsavedChanges.value) {
        const confirmed = await confirm('Tienes cambios sin guardar...');
        if (!confirmed) return next(false);
    }
    clearSession();
    next();
});
```

### 8.3 Optimizacion de Carga
- Lazy load de componentes de pasos
- Prefetch de datos del siguiente paso
- Skeleton loaders durante carga

---

## 9. Referencias

- **Vristo Template:** Wizard patterns en `/forms/wizards`
- **HeadlessUI Dialog:** Modales en `/clients/show.vue`
- **Pinia Patterns:** Stores en `/stores/*.ts`
- **Composables:** Patterns en `/composables/*.ts`
- **i18n:** Traducciones en `/locales/*.json`
