# PATTERNS.md — Estándares de Diseño y Patrones de Código

> Referencia rápida para implementaciones. Detalle completo en CLAUDE.md.
> Actualizado: 2026-05-14

---

## 1. Nomenclatura

| Artefacto | Patrón | Ejemplo |
|-----------|--------|---------|
| Código de expediente | `YY-TT-AAAA-NNNN` | `26-PR-RODR-0001` |
| Rutas API | `kebab-case`, plural | `/api/immigration-cases` |
| Modelos Eloquent | `PascalCase`, singular | `ImmigrationCase` |
| Permisos | `resource.action` | `cases.create` |
| Keys i18n | FLAT `modulo.subkey` | `"case.status.active": "Activo"` |
| Stores Pinia | `use<Name>Store` | `useCaseStore` |
| Servicios Vue | `<name>Service.ts` | `caseService.ts` |
| Composables | `use<Name>()` | `usePermissions()` |
| Tabla física de ImmigrationCase | `cases` (no `immigration_cases`) | `DB::table('cases')` |

---

## 2. Backend

### Controladores
- Ubicación: `app/Http/Controllers/Api/`
- Un método por operación: `index`, `store`, `show`, `update`, `destroy`
- Siempre retornar Resource: `return new CaseResource($case)`
- Autorización: `$this->authorize('cases.view', $case)` — nunca omitir
- Sin lógica de negocio — solo delegan a servicios

### Servicios
- Ubicación: `app/Services/<Domain>/<Name>Service.php`
- No reciben objetos `Request` — reciben arrays o tipos primitivos
- Toda lógica de negocio vive aquí, no en controllers ni models

### Resources
- Todo modelo expuesto en la API tiene su Resource en `app/Http/Resources/`
- Nunca serializar `$model->toArray()` directamente desde un controller

### Observers
- Registrados SOLO en `AppServiceProvider::boot()`
- Para bypass sin disparar observer: `$model->saveQuietly()` / `updateQuietly()`
- NUNCA llamar `Model::observe()` en Jobs, Controllers o Services

### Jobs
- Implementar `ShouldQueue` siempre
- Usar `ShouldBeUnique` para jobs que no deben correr en paralelo
- Reintentos: `public $tries = 3; public $backoff = [60, 300, 600];`

---

## 3. Multi-Tenancy (OBLIGATORIO en modelos nuevos)

```php
// En el modelo:
use BelongsToTenant;

// En la migración:
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

// En el controller store():
$model->tenant_id = app('tenant')->id;
$model->save();
```

**Excepción documentada — `CaseImportantDate`:** sin `BelongsToTenant`.
Aislamiento via JOIN explícito en `cases.tenant_id`. Ver spec 57 y MEMORY.md D-07.
NUNCA `CaseImportantDate::find($id)` directo — siempre via scope o policy.

---

## 4. Base de Datos

### Drop FK + Índice (patrón obligatorio, gotcha G2)
```php
$table->dropForeign(['col']);
$table->dropIndex(['col']);          // o dropUnique si aplica
// recrear nuevo índice si es necesario
$table->foreign('col')->references('id')->on('tabla');
```

### Permisos: en migraciones, NUNCA solo en seeders
```php
// En una migración dedicada:
Permission::firstOrCreate(['name' => 'resource.action']);
```

### Convenciones
- Soft deletes: `Client`, `Companion`, `ImmigrationCase`, `Document`
- Nombres de tabla: `snake_case`, plural
- `ImmigrationCase` → tabla física: `cases` (no `immigration_cases`)

---

## 5. Frontend (Vue 3 + TypeScript)

### Estructura de componente estándar
```vue
<script lang="ts" setup>
import { ref, computed } from 'vue';
import { useMeta } from '@/composables/use-meta';
useMeta({ title: 'Título' });
</script>
```

### Import alias
```typescript
import { useCaseStore } from '@/stores/caseStore';
import api from '@/services/api';
```

### i18n — Reglas críticas
- Keys FLAT: `"modal.confirm_delete": "¿Eliminar?"` — NO anidar objetos JSON
- `<` y `>` → escapar como `{'<'}` y `{'>'}` (build falla con HTML crudo)
- Siempre actualizar los 3 idiomas: `es.json`, `en.json`, `fr.json`
- Ubicación: `resources/js/src/locales/*.json`

### Autorización en plantillas (UX solamente)
```vue
<button v-can="'cases.create'">Crear</button>
<!-- v-can / v-role ocultan UI — NO son seguridad real -->
<!-- La seguridad real: $this->authorize() en el controller -->
```

### Visibilidad de grupos de menú en Sidebar (Spec 66)
Usar `hasAnyPermission([...])` con OR lógico sobre todos los permisos del dominio.
**Nunca** mezclar `hasRole('admin')` con `hasPermission()` en un computed del sidebar — el bypass ya está en el store.

```ts
// ✅ Correcto: computed por grupo con OR lógico
const canSeeWorkflowTasks = computed(() => authStore.hasAnyPermission([
    'workflow_tasks.view', 'workflow_tasks.create',
    'workflow_tasks.clone', 'workflow_tasks.update', 'workflow_tasks.delete',
]));

// ❌ Incorrecto: mezcla role + permiso (anti-pattern eliminado en Spec 66)
// const canViewWorkflows = computed(() =>
//     authStore.hasPermission('workflows.view') || authStore.hasRole('admin')
// );
```

```vue
<li v-if="canSeeWorkflowTasks">...</li>
```

### Manejo de errores UI
- Alertas usuario: SweetAlert2
- Errores API: manejar en `catch` del servicio, propagar mensaje del backend

### Multi-tab sync
- `BroadcastChannel('timesheet')` — timer entre pestañas
- `BroadcastChannel('session')` — propagación de kick entre pestañas

---

## 6. Autenticación

- Sanctum SPA: cookies HttpOnly + CSRF — NO Bearer tokens
- NUNCA auth data en localStorage o Pinia
- 401 + `reason: session_revoked` → `useSessionStore.kickedOut = true`
- 401 genérico → limpiar stores ANTES de `router.push('/auth/login')`
- `EnsureSingleSession` middleware aplica a todos los grupos autenticados

---

## 7. Storage

- SIEMPRE usar `StorageService` (envuelto en `ResilientStorageProvider`)
- NUNCA llamar providers directamente (gotcha G9)
- Factory: `StorageProviderFactory::make($tenant)` → selecciona provider correcto
- Circuit breaker: 5 errores → status `'error'` → usuario debe reconectar

---

## 8. Computed Escribible para Formularios con Campo Condicional

Cuando un formulario debe escribir a campos diferentes según el tipo de entidad, usar un computed con getter/setter en lugar de lógica en el template:

```typescript
// Evitar: v-model directo a campos distintos según condición
// Usar: computed que enruta al campo correcto

const hasSnapshot = computed(() => !!(entity.value?.snapshot?.items?.length));

const selectedId = computed<number | null>({
    get: () => hasSnapshot.value ? form.template_id : form.instance_id,
    set: (val) => {
        if (hasSnapshot.value) form.template_id = val;
        else form.instance_id = val;
    },
});
// En el template: v-model="selectedId"
```

Aplicado en `cases/edit.vue` → `selectedStageId` (snapshot → `current_stage_id`, ad-hoc → `current_case_stage_id`). Ver gotcha G20.

---

## 9. Visualización de Nombres de Clientes — `full_name` vs `display_name`

`display_name` y `full_name` son campos distintos con propósitos distintos. **Nunca intercambiarlos.**

| Campo | Origen | Format-aware | Cuándo usar |
|-------|--------|:------------:|-------------|
| `display_name` | Columna MySQL `GENERATED STORED` | ❌ Siempre `first_last` | Empresas — resuelve `trade_name ?? company_name` |
| `full_name` | Accessor PHP en modelo | ✅ Respeta `name_format` del tenant | Personas — respeta preferencia Spec 44 |

**Patrón obligatorio en el frontend:**
```typescript
function displayNameOf(client: Client): string {
    if (client.type === 'company') {
        return client.display_name || client.trade_name || client.company_name || `#${client.id}`;
    }
    // Persons: full_name respects tenant name_format (Spec 44)
    return client.full_name || `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim() || `#${client.id}`;
}
```

**Reglas:**
- Nunca concatenar `first_name + last_name` directamente en templates para personas — viola Spec 44.
- `display_name` es correcto para ordenamiento y búsqueda backend (columna indexada), no para display de personas.
- El composable `useFormatName()` (`composables/useFormatName.ts`) aplica la misma lógica basada en `tenantStore.nameFormat` para preview o contextos sin llamada a API.

---

## 11. Autenticación — Flujo limpio sin 401 redundantes

El navigation guard en `router/index.ts` intenta restaurar la sesión llamando a `GET /api/user` en la primera navegación. Esto generaba un 401 en consola cuando el usuario cargaba la página de login o era redirigido tras el logout, ya que no existía sesión activa.

**Solución:** `localStorage.has_session` como proxy de "hubo sesión". El guard solo llama `fetchUser()` si el flag existe.

| Evento | Acción en `auth.ts` | Resultado |
|---|---|---|
| Login exitoso | `localStorage.setItem('has_session', '1')` | Siguiente load restaura sesión |
| 2FA verificado | `localStorage.setItem('has_session', '1')` | Ídem |
| `fetchUser()` exitoso | `localStorage.setItem('has_session', '1')` | Confirma sesión vigente |
| Logout | `localStorage.removeItem('has_session')` | Siguiente load no llama al API |
| `fetchUser()` falla (401) | `localStorage.removeItem('has_session')` | Limpia flag de sesión caducada |

```typescript
// router/index.ts — guard
if (!authChecked && !authStore.isAuthenticated) {
    authChecked = true;
    if (localStorage.getItem('has_session') === '1') {
        await authStore.fetchUser();   // solo si hubo sesión previa
    }
}
```

El interceptor de Axios también silencia el 401 de `GET /api/user` cuando `has_session` no existe (capa defensiva para cualquier llamada directa al endpoint fuera del guard).

**Casos correctos que mantienen el 401 visible:**
- Sesión caducada (servidor expira la cookie) → `has_session = '1'` existe → `fetchUser()` llamado → 401 → interceptor muestra "sesión expirada" ✓
- Acceso a ruta protegida sin sesión → redirect inmediato a login sin llamada API ✓

---

## 10. Modales Persistentes (Estándar Obligatorio)

Todo modal de formulario del proyecto **debe ser persistente**: no se cierra con `Escape` ni con clic en el backdrop. Solo se cierra mediante el botón X o el botón Cancelar/Cerrar.

**Patrón HeadlessUI (único correcto):**
```html
<!-- @close no-op → bloquea Escape y backdrop -->
<Dialog as="div" @close="() => {}" class="relative z-[51]">
    ...
    <!-- X y Cancelar llaman a handleClose() del composable useModalGuard -->
    <button @click="handleClose()"><icon-x /></button>
    <button @click="handleClose()">{{ $t('common.cancel') }}</button>
```

**Por qué:** Los formularios del sistema son multilingües y complejos (3 idiomas, múltiples campos). Un cierre accidental por Escape o clic en el backdrop genera pérdida de datos sin advertencia. La persistencia elimina este riesgo sin añadir fricción cuando el formulario está limpio (ver `useModalGuard` — Spec 42).

**Aplica a:** todos los modales del sistema — formularios Y vistas de solo lectura. El único exclusión son los diálogos de confirmación pura (SweetAlert2 nativo), que no usan HeadlessUI Dialog.

| Modal | Tipo | `@close` correcto |
|-------|------|-------------------|
| `CompanionFormModal` | Formulario | `() => {}` + `handleClose` en X y Cancelar |
| `CompanionViewModal` | Solo lectura | `() => {}` + `$emit('close')` en X y Cerrar |
| `EventFormModal` | Formulario | `() => {}` + `handleClose` en X y Cancelar |
| `ScrumTaskModal` | Formulario | `() => {}` + `handleClose` en X |
| `CreateClientModal` | Formulario | `() => {}` + `guardClose` en X y Cancelar |

---

## 12. Sanitización de Inputs — Copy&Paste desde PDF

Campos de texto críticos (nombres, códigos, email) deben tener dos capas de protección contra contenido corrupto copiado desde PDFs u otras fuentes externas.

**Capa 1 — Bloqueo de digitación (`@keypress`):**
```typescript
function onNameKeypress(e: KeyboardEvent) {
    if (e.key.length > 1 || e.ctrlKey || e.metaKey || e.altKey) return
    if (/[^\p{L}\p{M}\p{N}\s'\-.]/u.test(e.key)) e.preventDefault()
}
```

**Capa 2 — Filtro de paste (`@paste.prevent`):**
```typescript
function sanitizeName(raw: string): string {
    return raw
        .replace(/[ --﻿​-‍  ]/g, '')
        .replace(/[\r\n\t]+/g, ' ')
        .replace(/[^\p{L}\p{M}\p{N}\s'\-.]/gu, '')
        .replace(/\s{2,}/g, ' ')
        .trim()
}

function sanitizeCode(raw: string): string {  // para IUC, números de documento
    return raw
        .replace(/[\r\n\t]/g, '')
        .replace(/[ --﻿​-‍]/g, '')
        .replace(/[^A-Za-z0-9\s\-]/g, '')
        .replace(/\s{2,}/g, ' ')
        .trim()
}
```

**Capa 3 — Safety-net en `handleSave()` antes de la llamada API:**
```typescript
companionForm.value.first_name = sanitizeName(companionForm.value.first_name)
companionForm.value.last_name = sanitizeName(companionForm.value.last_name)
if (companionForm.value.iuc) companionForm.value.iuc = sanitizeCode(companionForm.value.iuc)
```

**Reglas:**
- `sanitizeName`: nombres de personas — permite `\p{L}\p{M}\p{N}` (Unicode), espacios, apóstrofe, guion, punto
- `sanitizeCode`: códigos alfanuméricos (IUC, números de documento) — solo ASCII alfanumérico + guion
- Email paste: strip espacios/newlines, strip control chars — no filtrar `@`, `.` ni caracteres de dominio
- La regex `\p{L}\p{M}` con flag `u` cubre nombres con acentos, ñ, árabe, chino, etc.

---

## 11. Anti-patterns a Evitar

| Anti-pattern | Corrección |
|-------------|-----------|
| Lógica de negocio en controllers | Mover a Service |
| `Model::observe()` en Job/Controller | Solo en `AppServiceProvider::boot()` |
| Permisos solo en seeders | Migración dedicada con `firstOrCreate` |
| `<` / `>` crudos en archivos i18n JSON | Usar `{'<'}` y `{'>'}` |
| Modelo nuevo sin `BelongsToTenant` | Agregar trait + `tenant_id` FK |
| DROP FK sin DROP INDEX previo | Ver patrón de gotcha G2 |
| Auth data en Pinia/localStorage | Solo cookies HttpOnly |
| `DB::table('immigration_cases')` | La tabla es `'cases'` |
| i18n con objetos anidados | Solo keys FLAT |
| Llamar provider storage directamente | Usar `StorageService` |
| `<Dialog @close="handleClose">` o `@close="$emit('close')"` | `@close="() => {}"` — X y Cancelar llaman al handler directamente |
