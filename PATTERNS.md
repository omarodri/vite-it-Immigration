# PATTERNS.md — Estándares de Diseño y Patrones de Código

> Referencia rápida para implementaciones. Detalle completo en CLAUDE.md.
> Actualizado: 2026-05-14

---

## 1. Nomenclatura

| Artefacto | Patrón | Ejemplo |
|-----------|--------|---------|
| Código de expediente | `YY-TT-AAAA-NNNN` | `26-PR-2024-0001` |
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

## 8. Anti-patterns a Evitar

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
