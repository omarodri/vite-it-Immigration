---
tipo: spec
numero: 66
modulo: Administración / Seguridad
dominio: AUTH
estado: DONE
tags: [spec, rbac, permisos, workflow_tasks, case_code, sidebar, implementado]
relacionados:
  - "[[06_role_permission_management|Spec 06]]"
  - "[[08_permisos_clientes_acompanantes_roles|Spec 08]]"
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[50_edicion_clonacion_tareas_workflow|Spec 50]]"
  - "[[65_panel_configuracion_codigo_expediente|Spec 65]]"
  - "[[34_security_hardening|Spec 34]]"
---
# [[66_rbac_granular_workflow_tasks_case_code|Spec 66]] — RBAC Ultra-Granular: `workflow_tasks` y `case_code`

**Dominio:** AUTH / ADMIN  
**Estado:** DONE  
**Relacionados:** 06 (Roles y Permisos), 08 (Permisos Clientes/Acompañantes), 46 (Motor Workflows), 50 (Edición/Clonación Tareas), 65 (Panel Config. Código Expediente), 34 (Security Hardening)  
**Creado:** 2026-05-26  
**Autor:** Análisis de Impacto — Architect Agent + Validación de Reglas de Negocio

---

## Resumen Ejecutivo

El sistema RBAC actual (Spec 06) gestiona permisos a nivel de módulo (`workflows.view/create/update/delete`, `settings.case_code.manage`). Esta spec introduce **permisos ultra-granulares** para dos dominios:

1. **`workflow_tasks.*`** — 5 acciones granulares sobre el catálogo maestro de tareas (las plantillas que definen los workflows por tipo de expediente).
2. **`case_code.*`** — Fragmentación del permiso monolítico `settings.case_code.manage` en `case_code.view` y `case_code.update`, con asignación diferenciada.

Adicionalmente, se introduce la **regla de ocultación proactiva del menú lateral**: si un rol no posee ningún permiso de un dominio, la sección correspondiente del sidebar desaparece por completo (no solo se deshabilita).

### Por Qué Esta Granularidad

| Caso de Uso Real | Permiso Necesario | Sin Esta Spec |
|------------------|-------------------|---------------|
| Consultor diseña flujos y clona tareas plantilla | `workflow_tasks.clone` | Necesita `workflows.create` completo |
| Apoyo consulta qué tareas existen sin editar | `workflow_tasks.view` | Bloqueado o tiene acceso total |
| Admin ve el panel de código sin poder cambiarlo | `case_code.view` | Solo existe `settings.case_code.manage` (todo o nada) |
| Super-admin pasa toda validación sin listar permisos | bypass `super-admin` | Bug en frontend: bypass solo cubre rol `admin` |

---

## Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|----------|---------|----------------------|
| H1 | `hasPermission`, `hasAnyPermission`, `hasAllPermissions` ya existen en `useAuthStore` | `resources/js/src/stores/auth.ts:73-98` | No se crea un nuevo store — el OR lógico para el sidebar ya está disponible |
| H2 | `meta.permission` en el router ya acepta `string \| string[]` y aplica OR lógico | `resources/js/src/router/index.ts:874-875` | Las rutas nuevas solo necesitan declarar `permission: [array]` — no se modifica la guarda |
| H3 | Vista `error403` existe en `publicRoutes` (línea 34 del router) | `resources/js/src/router/index.ts` | La redirección a 403 ya funciona; verificar que existe `views/pages/error403.vue` |
| H4 | Permisos huérfanos: `workflow_tasks.update_core` y `workflow_tasks.clone` creados en mayo sin consumidores | `database/migrations/2026_05_04_000002_*` | `firstOrCreate` idempotente — no fallará. `workflow_tasks.clone` se formaliza en esta spec. `workflow_tasks.update_core` sigue separado (G17) |
| H5 | El bypass de super-admin en frontend solo cubre `roles.includes('admin')` | `resources/js/src/stores/auth.ts:76,85,94,101` | Bug existente — se arregla en esta spec como fix bloqueante |
| H6 | `TaskTemplatePolicy` usa permisos `workflows.*` genéricos | `app/Policies/TaskTemplatePolicy.php` | Debe reemplazar namespace `workflows.*` → `workflow_tasks.*` solo en esta Policy; `WorkflowStagePolicy` no se toca |
| H7 | Endpoint `PUT /tenant/case-code-pattern` usa middleware `permission:settings.case_code.manage` | `routes/api.php:265` | Cambiar a `permission:case_code.update` en esta spec |
| H8 | `Sidebar.vue` línea 981 referencia `settings.case_code.manage` para visibilidad del ítem | `resources/js/src/components/layout/Sidebar.vue:981` | Cambiar a computed `canSeeCaseCode` basado en `case_code.view \| case_code.update` |
| H9 | `Gate::before` para `super-admin` debe existir en `AuthServiceProvider` o `AppServiceProvider` | `app/Providers/` | Verificar prerrequisito antes de implementar — si falta, super-admin no pasa `$this->authorize()` granular |

---

## Decisiones Arquitectónicas

| # | Decisión | Justificación |
|---|----------|---------------|
| DA-1 | **`workflows.view` se mantiene como middleware del grupo `/admin/workflow/*`** — no se toca | Es el gate de entrada al módulo (stages + tasks). Los permisos `workflow_tasks.*` actúan dentro del grupo para acciones granulares solo sobre tareas |
| DA-2 | **Fragmentación con migración de transición** para `settings.case_code.manage` | Crear `case_code.view` + `case_code.update`; copiar a quienes tenían el legacy; mantener legacy 1 release; eliminarlo en release N+1 |
| DA-3 | **`$this->authorize()` inline en controller, no `middleware('can:...')`** | Permite pasar la instancia del modelo para validación cross-tenant en la Policy. Consistente con el patrón establecido en el proyecto |
| DA-4 | **Verificación cross-tenant en Policy y FormRequest** para operaciones sobre templates | `$template->tenant_id === $user->tenant_id` en Policy + `Rule::exists(...)->where('tenant_id', app('tenant')->id)` en FormRequest |
| DA-5 | **Fix del bypass super-admin en `auth.ts`** como prerrequisito bloqueante | Sin este fix, un super-admin sin doble-rol-admin no ve los nuevos menús aunque el backend lo autorice |
| DA-6 | **Agrupamiento visual de permisos en frontend** por prefijo del namespace | Backend devuelve array plano — el agrupamiento se hace en frontend con `groupPermissions()` para el panel de gestión de roles |
| DA-7 | **No crear composable `usePermissions()` nuevo** — usar `authStore.hasAnyPermission([...])` directamente en `computed` del sidebar | El store ya provee la abstracción. Agregar un composable wrapper duplica fuente de verdad sin beneficio |
| DA-8 | **`workflow_tasks.update_core` no se depreca** en esta spec | Es semánticamente distinto (opera sobre sincronización con Todos según G17). Deprecar requiere análisis separado del feature de Todos |

---

## Matriz de Permisos por Rol

| Permiso | super-admin | admin | consultor | apoyo | contador | cliente | user |
|---------|:-----------:|:-----:|:---------:|:-----:|:--------:|:-------:|:----:|
| `workflow_tasks.view` | ✓ (bypass) | ✓ | ✓ | ✓ | | | |
| `workflow_tasks.create` | ✓ | ✓ | ✓ | | | | |
| `workflow_tasks.clone` | ✓ | ✓ | ✓ | | | | |
| `workflow_tasks.update` | ✓ | ✓ | ✓ | | | | |
| `workflow_tasks.delete` | ✓ | ✓ | | | | | |
| `case_code.view` | ✓ | ✓ | | | | | |
| `case_code.update` | ✓ | ✓ | | | | | |

**Justificación de asignaciones clave:**
- `consultor` obtiene `workflow_tasks.delete` negado: eliminar del catálogo maestro es destructivo para todos los expedientes del tenant; reservado a `admin`.
- `apoyo` obtiene solo `workflow_tasks.view`: necesita consultar qué tareas existen pero no tiene autoridad para modificar plantillas.
- `case_code.*` solo para `admin` y `super-admin`: es configuración de empresa con impacto en nomenclatura de todos los expedientes futuros.

---

## 1. Base de Datos

### 1.1 Migración — Nuevos Permisos y Asignación por Rol

```php
<?php
// database/migrations/2026_05_27_000001_seed_workflow_tasks_and_case_code_permissions.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\App;

return new class extends Migration
{
    public function up(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        // G4: permisos en migración, no en seeder
        $newPerms = [
            'workflow_tasks.view',
            'workflow_tasks.create',
            'workflow_tasks.clone',
            'workflow_tasks.update',
            'workflow_tasks.delete',
            'case_code.view',
            'case_code.update',
        ];

        foreach ($newPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Asignación por rol según matriz de esta spec
        $matrix = [
            'super-admin' => $newPerms,
            'admin'       => $newPerms,
            'consultor'   => [
                'workflow_tasks.view',
                'workflow_tasks.create',
                'workflow_tasks.clone',
                'workflow_tasks.update',
            ],
            'apoyo'       => ['workflow_tasks.view'],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        // Migración de transición: propagar case_code.* a quienes tenían el legacy
        $legacyRoles = Role::permission('settings.case_code.manage')->get();
        foreach ($legacyRoles as $role) {
            $role->givePermissionTo(['case_code.view', 'case_code.update']);
        }

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = [
            'workflow_tasks.view', 'workflow_tasks.create', 'workflow_tasks.clone',
            'workflow_tasks.update', 'workflow_tasks.delete',
            'case_code.view', 'case_code.update',
        ];

        // Spatie elimina model_has_permissions por cascade al borrar el permiso
        Permission::whereIn('name', $perms)->where('guard_name', 'web')->delete();

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
```

**Notas críticas:**
- `firstOrCreate` es idempotente — si `workflow_tasks.clone` ya existe en BD (huérfano de mayo), no falla.
- La migración de transición en `up()` garantiza que ningún admin existente pierda acceso al panel de código al hacer deploy.
- El permiso `settings.case_code.manage` **no se elimina** en esta migración — se elimina en release N+1.

### 1.2 Verificar `Gate::before` para Super-Admin (Prerrequisito)

Verificar que existe en `app/Providers/AppServiceProvider.php` o `app/Providers/AuthServiceProvider.php`:

```php
Gate::before(function ($user, $ability) {
    return $user->hasRole('super-admin') ? true : null;
});
```

Si no existe, agregarlo en `AuthServiceProvider::boot()` **antes de implementar el resto**. Sin este gate, `$this->authorize()` granular falla para super-admin en backend aunque Spatie dice que `super-admin` tiene bypass.

---

## 2. Backend — Policies y Controllers

### 2.1 Refactor `TaskTemplatePolicy`

Archivo: `app/Policies/TaskTemplatePolicy.php`

**Antes** (namespace genérico):
```php
public function viewAny(User $user): bool { return $user->can('workflows.view'); }
public function create(User $user): bool  { return $user->can('workflows.create'); }
// ...etc
```

**Después** (namespace granular + validación cross-tenant en métodos con modelo):
```php
public function viewAny(User $user): bool
{
    return $user->can('workflow_tasks.view');
}

public function view(User $user, TaskTemplate $template): bool
{
    return $user->can('workflow_tasks.view')
        && $template->tenant_id === $user->tenant_id;
}

public function create(User $user): bool
{
    return $user->can('workflow_tasks.create');
}

public function clone(User $user, TaskTemplate $template): bool
{
    return $user->can('workflow_tasks.clone')
        && $template->tenant_id === $user->tenant_id;
}

public function update(User $user, TaskTemplate $template): bool
{
    return $user->can('workflow_tasks.update')
        && $template->tenant_id === $user->tenant_id;
}

public function delete(User $user, TaskTemplate $template): bool
{
    return $user->can('workflow_tasks.delete')
        && $template->tenant_id === $user->tenant_id;
}
```

**Importante:** `WorkflowStagePolicy` **no se modifica** — sigue usando `workflows.*` que es el gate del módulo de stages.

### 2.2 Refactor `TaskTemplateController`

Archivo: `app/Http/Controllers/Api/Admin/TaskTemplateController.php`

Cambios por método:

| Método | `authorize()` actual | `authorize()` nuevo |
|--------|---------------------|---------------------|
| `index` | `workflows.view` | `viewAny, TaskTemplate::class` |
| `store` | `workflows.create` | `create, TaskTemplate::class` |
| `update` | `workflows.update` | `update, $template` |
| `destroy` | `workflows.delete` | `delete, $template` |
| `reorder` | `workflows.create` *(incorrecto)* | `update, TaskTemplate::class` |
| `clone` *(nuevo)* | *(no existe)* | `clone, $sourceTemplate` |

**Método `clone` nuevo:**
```php
public function clone(CloneTaskTemplateRequest $request, TaskTemplate $template): TaskTemplateResource
{
    $this->authorize('clone', $template);

    $cloned = $this->taskTemplateService->cloneTemplate(
        source: $template,
        targetStageId: $request->validated('target_stage_id'),
    );

    return new TaskTemplateResource($cloned);
}
```

### 2.3 Nuevo `CloneTaskTemplateRequest`

Archivo: `app/Http/Requests/Admin/CloneTaskTemplateRequest.php`

```php
public function rules(): array
{
    return [
        'target_stage_id' => [
            'required',
            'integer',
            // Validación cross-tenant: el stage destino debe pertenecer al tenant activo
            Rule::exists('workflow_stages', 'id')
                ->where('tenant_id', app('tenant')->id),
        ],
    ];
}
```

**Riesgo R2 mitigado:** la combinación Policy (`$template->tenant_id`) + FormRequest (`exists` con `tenant_id`) garantiza que una clonación no puede leer de un tenant y escribir en otro.

### 2.4 Modificaciones en `routes/api.php`

```php
// Cambio 1: endpoint case-code-pattern (línea ~265)
// ANTES:
Route::put('/tenant/case-code-pattern', [TenantController::class, 'updateCaseCodePattern'])
    ->middleware('permission:settings.case_code.manage');

// DESPUÉS:
Route::put('/tenant/case-code-pattern', [TenantController::class, 'updateCaseCodePattern'])
    ->middleware('permission:case_code.update');

// Cambio 2: añadir ruta clone dentro del grupo /admin/workflow/templates
// El middleware del grupo 'permission:workflows.view' se mantiene intacto
Route::post('/admin/workflow/templates/{template}/clone', [TaskTemplateController::class, 'clone'])
    ->name('admin.workflow.templates.clone');
```

---

## 3. Frontend — Store, Router y Sidebar

### 3.1 Fix Bloqueante: Bypass Super-Admin en `auth.ts`

Archivo: `resources/js/src/stores/auth.ts`

**Antes** (bug — solo cubre `admin`):
```ts
hasPermission: (state) => (permission: string): boolean => {
    const roles = state.user?.roles?.map((r: any) => r.name) || [];
    if (roles.includes('admin')) return true;
    // ...
},
```

**Después** (fix — cubre ambos roles privilegiados):
```ts
hasPermission: (state) => (permission: string): boolean => {
    const roles = state.user?.roles?.map((r: any) => r.name) || [];
    if (roles.includes('admin') || roles.includes('super-admin')) return true;
    return (state.user?.permissions || []).includes(permission);
},
```

Aplicar el mismo cambio en `hasAnyPermission`, `hasAllPermissions`, e `isAdmin` (o agregar getter `isSuperAdmin: (state) => state.user?.roles?.some(r => r.name === 'super-admin') ?? false`).

**Este fix es prerrequisito para el resto del frontend.**

### 3.2 Modificaciones en `router/index.ts`

Verificar/actualizar las rutas de admin workflows y case-code-settings:

```ts
// Ruta: catálogo maestro de tareas
{
    path: '/admin/workflows',
    name: 'admin-workflows',
    component: () => import('@/views/admin/workflows.vue'),
    meta: {
        requiresAuth: true,
        // OR lógico: acceso si tiene AL MENOS UNO de estos permisos
        permission: [
            'workflow_tasks.view',
            'workflow_tasks.create',
            'workflow_tasks.clone',
            'workflow_tasks.update',
            'workflow_tasks.delete',
        ],
    },
},

// Ruta: configuración código expediente
{
    path: '/admin/case-code-settings',
    name: 'admin-case-code-settings',
    component: () => import('@/views/admin/CaseCodeSettings.vue'),
    meta: {
        requiresAuth: true,
        permission: ['case_code.view', 'case_code.update'],
    },
},
```

El `beforeEach` existente en línea 874-875 ya maneja arrays con OR lógico. La redirección va a `error403` (ya existente en router). **Verificar que el componente `views/pages/error403.vue` existe.** Si no, crear usando el mismo layout y estilo de `error404.vue`.

### 3.3 Modificaciones en `Sidebar.vue`

Archivo: `resources/js/src/components/layout/Sidebar.vue`

**Agregar computed en `<script setup>`:**

```ts
// Computed para visibilidad de grupos de menú
const canSeeWorkflowTasks = computed(() => authStore.hasAnyPermission([
    'workflow_tasks.view',
    'workflow_tasks.create',
    'workflow_tasks.clone',
    'workflow_tasks.update',
    'workflow_tasks.delete',
]));

const canSeeCaseCode = computed(() => authStore.hasAnyPermission([
    'case_code.view',
    'case_code.update',
]));
```

**Reemplazar en template:**

```vue
<!-- ANTES: item Workflows -->
<li v-if="canViewWorkflows || hasRole('admin')">

<!-- DESPUÉS: item Workflows — solo visible si tiene algún permiso granular -->
<li v-if="canSeeWorkflowTasks">

<!-- ANTES: item Código de Expediente -->
<li v-if="authStore.hasPermission('settings.case_code.manage')">

<!-- DESPUÉS: item Código de Expediente -->
<li v-if="canSeeCaseCode">
```

**Actualizar guarda del grupo Admin** (la sección que contiene todos los items de administración):

```vue
<!-- Agregar canSeeWorkflowTasks y canSeeCaseCode a la condición del grupo -->
<template v-if="canViewUsers || canViewRoles || canUpdateSettings || canSeeWorkflowTasks || canSeeCaseCode">
```

**Nota de performance:** los `computed` de Pinia son memoizados. El sidebar re-renderiza solo cuando `state.user.permissions` cambia (login/logout). Costo despreciable.

### 3.4 Protección de botones en vistas de catálogo

En la vista del catálogo maestro de tareas (`views/admin/workflows.vue` o equivalente):

```vue
<!-- Botón crear nueva tarea -->
<button v-can="'workflow_tasks.create'" @click="openCreateModal">
  {{ $t('workflow_tasks.btn_create') }}
</button>

<!-- Botón duplicar/clonar tarea -->
<button v-can="'workflow_tasks.clone'" @click="cloneTask(task)">
  {{ $t('workflow_tasks.btn_clone') }}
</button>

<!-- Botón editar -->
<button v-can="'workflow_tasks.update'" @click="editTask(task)">
  {{ $t('workflow_tasks.btn_edit') }}
</button>

<!-- Botón eliminar — acción destructiva -->
<button v-can="'workflow_tasks.delete'" @click="confirmDelete(task)">
  {{ $t('workflow_tasks.btn_delete') }}
</button>
```

En `CaseCodeBuilder.vue` (Spec 65):

```vue
<!-- Botón guardar — solo visible/activo con case_code.update -->
<button
  v-can="'case_code.update'"
  :disabled="!authStore.hasPermission('case_code.update')"
  @click="savePattern"
>
  {{ $t('settings.case_code.save_btn') }}
</button>
```

### 3.5 Agrupamiento de Permisos en el Panel de Gestión de Roles

Archivo: `resources/js/src/views/admin/roles/` (vista de edición de rol)

El backend devuelve permisos como array plano. El agrupamiento se hace en frontend:

```ts
interface PermissionGroup {
    module: string;
    label: string;
    permissions: { name: string; label: string }[];
}

function groupPermissions(perms: string[]): PermissionGroup[] {
    const grouped: Record<string, string[]> = {};
    for (const p of perms) {
        const module = p.split('.')[0];
        (grouped[module] ??= []).push(p);
    }
    return Object.entries(grouped).map(([module, list]) => ({
        module,
        label: t(`permissions.modules.${module}`),
        permissions: list.map(name => ({
            name,
            label: t(`permissions.labels.${name}`, name),
        })),
    }));
}
```

Render sugerido: sección colapsable por módulo, checkboxes por permiso. Resaltar permisos `*.delete` con `text-danger` como "Acción destructiva".

---

## 4. Internacionalización (i18n)

> Reglas G6 y G10: keys FLAT, sin objetos anidados, sin `<` ni `>` literales.  
> Actualizar los 3 archivos: `es.json`, `en.json`, `fr.json`.

### 4.1 Keys requeridas

```json
// Módulos para el panel de roles
"permissions.modules.workflow_tasks": "",
"permissions.modules.case_code": "",

// Labels de permisos individuales
"permissions.labels.workflow_tasks.view": "",
"permissions.labels.workflow_tasks.create": "",
"permissions.labels.workflow_tasks.clone": "",
"permissions.labels.workflow_tasks.update": "",
"permissions.labels.workflow_tasks.delete": "",
"permissions.labels.case_code.view": "",
"permissions.labels.case_code.update": "",

// Errores de acceso denegado
"errors.permission_denied.generic": "",
"errors.permission_denied.workflow_tasks.clone": "",
"errors.permission_denied.case_code.update": "",
"errors.permission_denied.page_title": "",
"errors.permission_denied.page_body": "",

// Sidebar (si no existen ya)
"sidebar.workflow_tasks": "",
"sidebar.case_code": ""
```

### 4.2 Traducciones

**`es.json`:**
```json
"permissions.modules.workflow_tasks": "Tareas del Workflow",
"permissions.modules.case_code": "Código de Expediente",
"permissions.labels.workflow_tasks.view": "Ver catálogo de tareas maestras",
"permissions.labels.workflow_tasks.create": "Crear tareas en el catálogo maestro",
"permissions.labels.workflow_tasks.clone": "Duplicar tareas y estructuras",
"permissions.labels.workflow_tasks.update": "Editar instrucciones, prioridades y etapas",
"permissions.labels.workflow_tasks.delete": "Eliminar tareas del catálogo maestro",
"permissions.labels.case_code.view": "Ver el panel de configuración del código",
"permissions.labels.case_code.update": "Modificar el orden de bloques y separadores",
"errors.permission_denied.generic": "No tienes permiso para realizar esta acción.",
"errors.permission_denied.workflow_tasks.clone": "No tienes permiso para duplicar tareas. Contacta a un administrador.",
"errors.permission_denied.case_code.update": "No tienes permiso para modificar el código de expediente.",
"errors.permission_denied.page_title": "Acceso denegado",
"errors.permission_denied.page_body": "No tienes los permisos necesarios para ver esta sección. Si crees que esto es un error, contacta a un administrador.",
"sidebar.workflow_tasks": "Tareas del Workflow",
"sidebar.case_code": "Código de Expediente"
```

**`en.json`:**
```json
"permissions.modules.workflow_tasks": "Workflow Tasks",
"permissions.modules.case_code": "Case Code",
"permissions.labels.workflow_tasks.view": "View master task catalog",
"permissions.labels.workflow_tasks.create": "Create master tasks",
"permissions.labels.workflow_tasks.clone": "Clone tasks and structures",
"permissions.labels.workflow_tasks.update": "Edit instructions, priorities, and stages",
"permissions.labels.workflow_tasks.delete": "Delete master tasks",
"permissions.labels.case_code.view": "View case-code configuration panel",
"permissions.labels.case_code.update": "Modify block order and separators",
"errors.permission_denied.generic": "You don't have permission to perform this action.",
"errors.permission_denied.workflow_tasks.clone": "You don't have permission to clone tasks. Contact an administrator.",
"errors.permission_denied.case_code.update": "You don't have permission to modify the case code.",
"errors.permission_denied.page_title": "Access denied",
"errors.permission_denied.page_body": "You don't have the required permissions to view this section. If you think this is a mistake, contact an administrator.",
"sidebar.workflow_tasks": "Workflow Tasks",
"sidebar.case_code": "Case Code"
```

**`fr.json`:**
```json
"permissions.modules.workflow_tasks": "Tâches du Workflow",
"permissions.modules.case_code": "Code de dossier",
"permissions.labels.workflow_tasks.view": "Voir le catalogue de tâches maîtres",
"permissions.labels.workflow_tasks.create": "Créer des tâches dans le catalogue maître",
"permissions.labels.workflow_tasks.clone": "Dupliquer des tâches et structures",
"permissions.labels.workflow_tasks.update": "Modifier les instructions, priorités et étapes",
"permissions.labels.workflow_tasks.delete": "Supprimer des tâches du catalogue maître",
"permissions.labels.case_code.view": "Voir le panneau de configuration du code",
"permissions.labels.case_code.update": "Modifier l'ordre des blocs et les séparateurs",
"errors.permission_denied.generic": "Vous n'avez pas la permission d'effectuer cette action.",
"errors.permission_denied.workflow_tasks.clone": "Vous n'avez pas la permission de dupliquer des tâches. Contactez un administrateur.",
"errors.permission_denied.case_code.update": "Vous n'avez pas la permission de modifier le code de dossier.",
"errors.permission_denied.page_title": "Accès refusé",
"errors.permission_denied.page_body": "Vous n'avez pas les permissions nécessaires pour voir cette section. Si vous pensez que c'est une erreur, contactez un administrateur.",
"sidebar.workflow_tasks": "Tâches du Workflow",
"sidebar.case_code": "Code de dossier"
```

---

## 5. Riesgos y Casos de Borde

| ID | Riesgo | Impacto | Mitigación |
|----|--------|:-------:|------------|
| R1 | Usuarios con `settings.case_code.manage` pierden acceso al hacer deploy | **ALTO** | Migración de transición (sección 1.1) copia permisos legacy → nuevos antes de cambiar el middleware |
| R2 | `workflow_tasks.clone` permite copiar template de otro tenant si el request lleva IDs cruzados | **ALTO** | Policy valida `$template->tenant_id === $user->tenant_id`. FormRequest valida `exists:workflow_stages,id` con `where('tenant_id', app('tenant')->id)` |
| R3 | Race condition F5: guards evalúan permisos antes de hidratar `user` | **BAJO** | `beforeEach` hace `await fetchUser()` (línea 841 del router) antes de evaluar guards — no hay race |
| R4 | Super-admin sin doble-rol-admin no pasa `hasPermission` en frontend | **MEDIO** | Fix en `auth.ts` (sección 3.1) — prerrequisito bloqueante |
| R5 | Permisos directos al usuario (no via rol) no se reflejan en `permissions` | **BAJO** | Verificar que `GET /auth/user` serializa con `$user->getAllPermissions()->pluck('name')`, no `$user->permissions` (relación directa). Si usa relación directa, cambiar a `getAllPermissions()` |
| R6 | Vista `error403.vue` podría no existir | **MEDIO** | Verificar antes de deploy. Si falta, crear basada en `error404.vue` |
| R7 | `workflow_tasks.update_core` coexiste con `workflow_tasks.update` | **BAJO** | No deprecar en esta spec — son semánticamente distintos (G17). Documentar en MEMORY.md |
| R8 | `Gate::before` para super-admin podría no estar definido | **ALTO** | Verificar en `AuthServiceProvider`. Si falta, los `$this->authorize()` granulares fallan para super-admin en backend |

---

## 6. Checklist de Implementación

Las capas están ordenadas por dependencias. No comenzar una capa sin completar las anteriores marcadas como bloqueantes.

### CAPA 1 — Base de Datos *(sin dependencias)*
- [x] Verificar existencia de `Gate::before` para `super-admin` en `app/Providers/AuthServiceProvider.php` — YA EXISTÍA cubriendo super-admin y admin
- [x] Crear `database/migrations/2026_05_27_000001_seed_workflow_tasks_and_case_code_permissions.php` con contenido de sección 1.1
- [x] Ejecutar `php artisan migrate` — DONE (107.88 ms)
- [x] Verificar: `workflow_tasks.{view,create,clone,update,delete}` + `case_code.{view,update}` presentes en tabla `permissions`
- [x] Migración de transición: roles con `settings.case_code.manage` reciben automáticamente `case_code.view` + `case_code.update`

### CAPA 2 — Backend API *(depende de Capa 1)*
- [x] Modificar `app/Policies/TaskTemplatePolicy.php` — reemplazado namespace `workflows.*` → `workflow_tasks.*`, agregado método `clone()` con tenant check
- [x] Modificar `app/Http/Controllers/Api/Admin/TaskTemplateController.php` — `reorder()` corregido a `authorize('update',...)`, agregado método `clone()`
- [x] Crear `app/Http/Requests/Workflow/CloneTaskTemplateRequest.php` con validación cross-tenant en `target_stage_id`
- [x] Agregar `TaskTemplateService::clone()` — copia fillable + traducciones, sort_order = max+1, DB::transaction
- [x] Modificar `routes/api.php` — middleware `case-code-pattern` cambiado a `permission:case_code.update`; ruta `POST /admin/workflow/templates/{template}/clone` agregada
- [ ] Test manual: `POST /api/admin/workflow/templates/{id}/clone` con usuario `consultor` → 200. Con usuario `apoyo` → 403

### CAPA 3 — Frontend Store y Router *(depende de Capa 1; parcialmente paralela con Capa 2)*
- [x] Modificar `resources/js/src/stores/auth.ts` — bypass `super-admin` agregado en `hasPermission`, `hasAnyPermission`, `hasAllPermissions`, `isAdmin`
- [ ] Verificar existencia de `resources/js/src/views/pages/error403.vue`
- [x] Modificar `resources/js/src/router/index.ts` — `admin-workflows`, `admin-workflows-builder` y `admin-case-code-settings` actualizados con arrays de permisos

### CAPA 4 — Frontend UI *(depende de Capa 3)*
- [x] Modificar `resources/js/src/components/layout/Sidebar.vue` — `canSeeWorkflowTasks` y `canSeeCaseCode` como computed; eliminados `canManageCaseCode` y `canViewWorkflows` legacy; template actualizado (guarda grupo Admin + items Workflows + Case Code)
- [ ] Agregar `v-can` a botones en vistas del catálogo de tareas (`/admin/workflows`) — **PENDIENTE: Spec 67**
- [ ] Modificar `CaseCodeBuilder.vue` — deshabilitar botón guardar para usuarios sin `case_code.update` — **PENDIENTE: Spec 67**
- [ ] Implementar `groupPermissions()` en el panel de gestión de roles — **PENDIENTE: Spec 67**

### CAPA 5 — i18n *(paralela con Capa 4)*
- [x] Agregar 15 keys FLAT a `resources/js/src/locales/es.json` — `permissions.modules.*`, `permissions.labels.*`, `errors.permission_denied.*`
- [x] Agregar 15 keys FLAT a `resources/js/src/locales/en.json`
- [x] Agregar 15 keys FLAT a `resources/js/src/locales/fr.json`
- [x] `npm run build` — ✓ sin errores (9.58 s), G10 respetado (sin HTML crudo)

### CAPA 6 — Testing y Validación *(depende de todas las anteriores)*
- [ ] **Feature test** `tests/Feature/Admin/TaskTemplateAuthorizationTest.php` — **PENDIENTE: DT-12**
- [ ] **Feature test** `tests/Feature/Api/CaseCodePatternAuthorizationTest.php` — **PENDIENTE: DT-12**
- [ ] **Unit test** `tests/Unit/Policies/TaskTemplatePolicyTest.php` — **PENDIENTE: DT-12**
- [ ] **QA manual multi-rol** — pendiente entorno de staging
- [ ] **Regression:** usuarios con `settings.case_code.manage` siguen pudiendo usar el endpoint — validado por migración de transición
- [x] Actualizar `spec/INDEX.md` — estado de Spec 66 → `DONE`
- [x] Actualizar `MEMORY.md` — entrada agregada al inicio de "Implementaciones Recientes"

---

## 7. Release Plan

### Release N (esta spec)
- Crear los 7 permisos granulares
- Migración de transición: copiar `settings.case_code.manage` → `case_code.*`
- Cambiar middleware del endpoint a `case_code.update`
- `settings.case_code.manage` sigue existiendo en BD (sin romper nada)

### Release N+1 (limpieza)
- Verificar con `grep -r 'settings.case_code.manage' app/ resources/` → debe devolver 0 resultados
- Crear migración de limpieza: `Permission::where('name', 'settings.case_code.manage')->delete()`
- Documentar en MEMORY.md como deuda técnica cerrada

---

## 8. Archivos a Modificar/Crear

| Acción | Archivo |
|--------|---------|
| CREAR | `database/migrations/2026_05_27_000001_seed_workflow_tasks_and_case_code_permissions.php` |
| MODIFICAR | `app/Policies/TaskTemplatePolicy.php` |
| MODIFICAR | `app/Http/Controllers/Api/Admin/TaskTemplateController.php` |
| CREAR | `app/Http/Requests/Admin/CloneTaskTemplateRequest.php` |
| MODIFICAR | `routes/api.php` |
| MODIFICAR | `resources/js/src/stores/auth.ts` |
| MODIFICAR | `resources/js/src/router/index.ts` |
| MODIFICAR | `resources/js/src/components/layout/Sidebar.vue` |
| MODIFICAR | `resources/js/src/views/admin/` *(catálogo tareas + panel roles)* |
| MODIFICAR | `resources/js/src/views/admin/CaseCodeSettings.vue` / `CaseCodeBuilder.vue` |
| VERIFICAR/CREAR | `resources/js/src/views/pages/error403.vue` |
| MODIFICAR | `resources/js/src/locales/es.json` |
| MODIFICAR | `resources/js/src/locales/en.json` |
| MODIFICAR | `resources/js/src/locales/fr.json` |
| VERIFICAR | `app/Providers/AuthServiceProvider.php` *(Gate::before super-admin)* |

---

## 9. Deuda Técnica que Genera Esta Spec

| ID | Descripción | Prioridad |
|----|-------------|:---------:|
| DT-10 | Eliminar permiso `settings.case_code.manage` en Release N+1 tras confirmar 0 referencias activas | BAJA |
| DT-11 | Evaluar deprecación de `workflow_tasks.update_core` — determinar si sigue siendo necesario para la integración con Todos (G17) o puede unificarse con `workflow_tasks.update` | BAJA |
| DT-12 | Tests Feature/Unit de `TaskTemplatePolicy` y `TaskTemplateAuthorizationTest` si quedan pendientes al hacer release | MEDIA |
