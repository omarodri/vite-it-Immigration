# Plan de Implementación: Filtrado `assigned_to` por Consultor Activo

## Metadata
- **Fecha:** 2026-03-12
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 3.1 - Restricción de Asignación a Consultores Activos
- **Alcance:** Filtrar el campo `assigned_to` en creación y edición de expedientes para mostrar únicamente usuarios con rol "consultor" y estado activo (`is_active = true`)
- **Flujos cubiertos:** Wizard de creación (StepDetails.vue) + Edición de expediente (edit.vue)
- **Tiempo Total Estimado:** ~4 horas
- **Estado:** ✅ COMPLETADO (2026-03-12)
- **Tests:** 62 tests, 272 assertions — todos pasan ✅

---

## Diagnóstico del Estado Actual

### Gaps identificados

| Componente | Estado actual | Gap |
|---|---|---|
| `users` table | Sin campo `is_active` / `status` | ❌ Requiere migración |
| `User` model | Sin scopes de rol/estado | ❌ |
| `UserService::getStaffMembers()` | Retorna TODOS los usuarios del tenant | ❌ Sin filtro de rol ni estado |
| `UserController::staff()` | Sin parámetros de filtrado | ❌ |
| `UpdateCaseRequest::assigned_to` | Solo valida `exists:users,id` + mismo tenant | ❌ Sin validación de rol/estado |
| `StoreCaseRequest::assigned_to` | Igual que arriba | ❌ |
| `edit.vue` select | Muestra todos los usuarios sin diferenciación | ❌ |
| `StepDetails.vue` select | Muestra todos los usuarios sin diferenciación | ❌ Sin loading state, empty state ni error handling |

### Hallazgos críticos del análisis

1. **El modelo `User` no tiene campo `is_active`** — La tabla `users` solo tiene: `name`, `email`, `password`, `tenant_id`, campos 2FA. Sin campo de estado. **Sin esta migración, nada funciona.**
2. **Spatie Laravel Permission está instalado** — `HasRoles` trait disponible, `role('consultor')` funciona en queries.
3. **`getStaffMembers()`** devuelve todos los usuarios del tenant sin filtro alguno.
4. **`StepDetails.vue`** no tiene estado de carga ni manejo visible de errores.
5. **Problema de datos históricos** — Un expediente puede tener asignado un consultor que posteriormente fue desactivado. El frontend debe manejarlo sin mostrar el campo vacío.

---

## Diagrama de Dependencias entre Fases

```
FASE 0: Migración is_active (PREREQUISITO CRÍTICO)
    │
    └──► FASE 1: Modelo User (scopes + fillable + cast)
              │
              ├──► FASE 2: UserService (filtros + include_user_id)
              │         │
              │         └──► FASE 3: UserController (query param)
              │                   │
              │                   └──► FASE 6: Frontend — userService.ts + tipo StaffMember
              │                             │
              │                             ├──► FASE 7: StepDetails.vue (creación)
              │                             └──► FASE 8: edit.vue (edición + fantasma)
              │
              ├──► FASE 4: StoreCaseRequest (sin cláusula abuelo)
              └──► FASE 5: UpdateCaseRequest (con cláusula abuelo)
                                                │
                                                └──► FASE 9: Tests
```

Las Fases 4, 5 y 6 pueden ejecutarse en paralelo una vez completadas las Fases 0–3.

---

## FASE 0 — Prerequisito Crítico: Migración `is_active`

**Objetivo:** Agregar la columna `is_active` a la tabla `users` con valor por defecto `true`, garantizando que todos los usuarios existentes queden como activos sin ningún cambio visible.

**Archivos a crear:**
- `database/migrations/YYYY_MM_DD_HHMMSS_add_is_active_to_users_table.php`

**Pasos:**
1. Generar la migración: `php artisan make:migration add_is_active_to_users_table --table=users`
2. En `up()`: agregar `$table->boolean('is_active')->default(true)->after('email_verified_at')`
3. En `down()`: `$table->dropColumn('is_active')`
4. Agregar índice compuesto para optimizar queries: `$table->index(['tenant_id', 'is_active'])`

**Criterio de verificación:**
- `php artisan migrate` ejecuta sin errores
- Todos los usuarios existentes tienen `is_active = 1`
- `php artisan migrate:rollback --step=1` funciona correctamente

**Riesgo:** Mínimo. La columna tiene `default(true)`, por lo que no rompe ninguna funcionalidad existente. En MySQL moderno es una operación instantánea (instant DDL).

**Tiempo estimado:** 15 minutos

---

## FASE 1 — Modelo User: Campo, Casts y Scopes

**Objetivo:** Hacer que el modelo `User` reconozca `is_active`, lo castee correctamente, y exponga query scopes reutilizables para el resto del sistema.

**Archivos a modificar:**
- `app/Models/User.php`

**Pasos:**
1. **Agregar `is_active` al array `$fillable`** (línea 27–32) — agregar `'is_active'` al final del array
2. **Agregar cast** en `$casts` (línea 51–56) — `'is_active' => 'boolean'`
3. **Agregar import** de `Illuminate\Database\Eloquent\Builder` al inicio del archivo
4. **Agregar `scopeActive(Builder $query): Builder`** — filtra `where('is_active', true)`. Ubicar después del método `belongsToTenant()` (línea 98)
5. **Agregar `scopeConsultors(Builder $query): Builder`** — filtra con `$query->role('consultor')` (método de Spatie). Ubicar inmediatamente después de `scopeActive`
6. **Agregar `scopeActiveConsultors(Builder $query): Builder`** — scope de conveniencia que combina: `$query->active()->consultors()`. Simplifica el uso en el servicio
7. **Actualizar `getActivitylogOptions`** (línea 116–123) — agregar `'is_active'` al array `logOnly` para que los cambios de estado queden registrados en el audit trail

**Criterio de verificación:**
- `User::active()->count()` retorna el total de usuarios (todos activos tras la migración)
- `User::consultors()->count()` retorna solo los que tienen rol `consultor`
- `User::activeConsultors()->where('tenant_id', X)->count()` retorna el subconjunto correcto
- `$user->is_active` retorna `true`/`false` (booleano, no 1/0)

**Riesgo:** Bajo. Los scopes son adiciones puras. Verificar que el nombre del rol sea exactamente `consultor` (no `consultant` ni otra variante) consultando la tabla `roles`.

**Tiempo estimado:** 20 minutos

---

## FASE 2 — `UserService::getStaffMembers()` con Filtros

**Objetivo:** Modificar el método para retornar solo consultores activos del tenant, con soporte para incluir un usuario específico aunque no cumpla los filtros (patrón "consultor fantasma" para datos históricos).

**Archivos a modificar:**
- `app/Services/User/UserService.php`

**Pasos:**
1. **Cambiar la firma** de `getStaffMembers(): Collection` a `getStaffMembers(?int $includeUserId = null): Collection`
2. **Reemplazar el cuerpo del método** con la nueva lógica:
   - Query principal: `User::activeConsultors()->where('tenant_id', Auth::user()->tenant_id)->select('id', 'name', 'email')->orderBy('name')->get()`
   - Mapear cada resultado agregando el atributo temporal `is_current_assignment = false`
   - Si `$includeUserId` no es null:
     - Verificar si ya está en la colección — si sí, no duplicar
     - Si no está: buscarlo con `User::where('id', $includeUserId)->where('tenant_id', Auth::user()->tenant_id)->select('id', 'name', 'email')->first()`
     - Si se encuentra: agregarle `is_current_assignment = true` y anteponerlo al inicio (prepend) de la colección
3. Retornar la colección resultante

**Criterio de verificación:**
- Sin `$includeUserId`: solo retorna consultores activos del tenant
- Con `$includeUserId` de un consultor activo ya en la lista: no duplica, `is_current_assignment = false`
- Con `$includeUserId` de un usuario inactivo o no-consultor: lo incluye con `is_current_assignment = true`
- Con `$includeUserId = null`: comportamiento equivalente al original pero filtrado

**Riesgo:** Medio. Buscar otros consumidores de `getStaffMembers()` en el codebase antes de implementar. Si hay consumidores que esperan la lista completa, el cambio los afecta.

**Tiempo estimado:** 30 minutos

---

## FASE 3 — `UserController::staff()` con Query Param

**Objetivo:** El controlador lee el parámetro opcional `include_user_id` del request y lo pasa al servicio.

**Archivos a modificar:**
- `app/Http/Controllers/Api/UserController.php`

**Pasos:**
1. **Modificar el método `staff()`** (líneas 30–35):
   - Inyectar `Request $request` en la firma
   - Leer el parámetro: `$includeUserId = $request->query('include_user_id') ? (int) $request->query('include_user_id') : null`
   - Pasar al servicio: `$this->userService->getStaffMembers($includeUserId)`
2. **Actualizar anotaciones OpenAPI/PHPDoc** del método `staff()`:
   - Agregar parámetro `include_user_id` (in: query, required: false, type: integer)
   - Actualizar summary: "Returns active consultants for case assignment. Optionally includes a specific user by ID."

**Criterio de verificación:**
- `GET /api/users/staff` (sin parámetros) retorna solo consultores activos — retrocompatible
- `GET /api/users/staff?include_user_id=5` incluye al usuario 5 aunque no sea consultor activo, con `is_current_assignment: true`
- `GET /api/users/staff?include_user_id=abc` ignora el parámetro inválido (cast a int da 0, no hay usuario con id 0)

**Riesgo:** Bajo. Cambio aditivo al endpoint. La ausencia del parámetro mantiene el comportamiento ya filtrado de la Fase 2.

**Tiempo estimado:** 15 minutos

---

## FASE 4 — `StoreCaseRequest`: Validación para Casos Nuevos

**Objetivo:** En la creación de expedientes, validar que `assigned_to` (si se proporciona) sea un consultor activo del mismo tenant. Sin cláusula abuelo — en creación toda asignación debe ser estricta.

**Archivos a modificar:**
- `app/Http/Requests/Case/StoreCaseRequest.php`

**Pasos:**
1. **Localizar el bloque de validación de `assigned_to`** en `withValidator()` (líneas 76–81)
2. **Reemplazar la validación actual** con la nueva lógica:
   - Encontrar al usuario: `$assignedUser = User::withoutGlobalScopes()->find($this->assigned_to)`
   - Validar tenant (ya existe): verificar que `$assignedUser->tenant_id === Auth::user()->tenant_id`
   - **NUEVO** — Validar rol consultor: `!$assignedUser->hasRole('consultor')` → error: `'The assigned user must have the consultor role.'`
   - **NUEVO** — Validar estado activo: `!$assignedUser->is_active` → error: `'The assigned user must be active.'`
3. **Agregar mensajes custom** en `messages()`:
   - `'assigned_to.consultor_role' => 'Solo se pueden asignar consultores a los expedientes.'`
   - `'assigned_to.active_status' => 'El consultor asignado debe estar activo.'`

**Criterio de verificación:**
- Crear caso con `assigned_to` de consultor activo del tenant: 201 ✅
- Crear caso con `assigned_to` de usuario con rol `admin`: 422 con error de rol ✅
- Crear caso con `assigned_to` de consultor inactivo: 422 con error de estado ✅
- Crear caso con `assigned_to = null`: 201 ✅
- Crear caso sin campo `assigned_to`: 201 ✅

**Riesgo:** Bajo. Verificar si hay seeders o tests existentes que creen casos asignados a usuarios no-consultores — deberán actualizarse.

**Tiempo estimado:** 20 minutos

---

## FASE 5 — `UpdateCaseRequest`: Cláusula Abuelo

**Objetivo:** En la edición de expedientes, si `assigned_to` no cambia respecto al valor actual del caso, permitirlo sin validación adicional (datos históricos). Si cambia (nueva asignación), validar que sea un consultor activo.

**Archivos a modificar:**
- `app/Http/Requests/Case/UpdateCaseRequest.php`

**Pasos:**
1. **Localizar el bloque de validación de `assigned_to`** en `withValidator()` (líneas 64–69)
2. **Reemplazar con la lógica condicional**:
   - Verificar si el campo viene en el request: `$this->has('assigned_to')` — si no viene, no ejecutar ninguna validación (el campo no se toca)
   - Obtener el caso actual: `$case = $this->route('case')`
   - Obtener el valor actual del campo: `$currentAssignedTo = $case->assigned_to`
   - Obtener el nuevo valor: `$newAssignedTo = $this->assigned_to`
   - **Si `$newAssignedTo == $currentAssignedTo`** → CLÁUSULA ABUELO: no validar (el consultor puede estar inactivo, se permite conservar la asignación existente)
   - **Si `$newAssignedTo` es `null`** → desasignación: permitir sin validación
   - **Si `$newAssignedTo != $currentAssignedTo` Y no es `null`** → validar tenant + rol consultor + is_active (igual que StoreCaseRequest)
3. **Detalle crítico de la comparación**: Usar cast explícito para evitar comparaciones tipo vs string: `(int) $newAssignedTo === (int) $currentAssignedTo` cuando ambos son no-null
4. **Mensajes de error** diferenciados para nuevas asignaciones:
   - Si no es consultor: `'New assignments must be to users with the consultor role.'`
   - Si no está activo: `'New assignments must be to active users.'`

**Criterio de verificación:**
- Editar caso sin enviar `assigned_to` en el payload: 200, campo no modificado ✅
- Editar caso enviando el mismo `assigned_to` (consultor ahora inactivo): 200 — CLÁUSULA ABUELO ✅
- Editar caso cambiando `assigned_to` a consultor activo: 200 ✅
- Editar caso cambiando `assigned_to` a consultor inactivo: 422 ✅
- Editar caso cambiando `assigned_to` a un admin activo: 422 ✅
- Editar caso poniendo `assigned_to = null` (desasignar): 200 ✅

**Riesgo:** Medio. La comparación "sin cambio" vs "nueva asignación" requiere manejo cuidadoso de tipos. Usar `$this->has('assigned_to')` para distinguir "no enviado" de "enviado como null".

**Tiempo estimado:** 30 minutos

---

## FASE 6 — Frontend: Tipo `StaffMember` y `userService.ts`

**Objetivo:** Extender el tipo `StaffMember` para soportar el flag de fantasma, y actualizar el servicio para aceptar `include_user_id` opcionalmente.

**Archivos a modificar:**
- `resources/js/src/types/wizard.ts`
- `resources/js/src/services/userService.ts`

### 6a. Tipo `StaffMember` (`wizard.ts`)

**Pasos:**
1. Localizar la interfaz `StaffMember` (línea 73–77)
2. Agregar propiedad opcional: `is_current_assignment?: boolean`

El tipo resultante:
```
id: number
name: string
email: string
is_current_assignment?: boolean   ← NUEVO
```

### 6b. Servicio (`userService.ts`)

**Pasos:**
1. Modificar la firma de `getStaff()` a: `async getStaff(includeUserId?: number | null): Promise<StaffMember[]>`
2. Construir la URL condicionalmente:
   - Si `includeUserId` es un número válido (no null, no undefined): llamar a `/users/staff?include_user_id=${includeUserId}`
   - Si no: llamar a `/users/staff` (sin parámetros, retrocompatible)

**Criterio de verificación:**
- `userService.getStaff()` → llama a `/users/staff` (retrocompatible) ✅
- `userService.getStaff(5)` → llama a `/users/staff?include_user_id=5` ✅
- `userService.getStaff(null)` → llama a `/users/staff` (ignora null) ✅
- TypeScript compila sin errores con la nueva firma ✅

**Tiempo estimado:** 15 minutos

---

## FASE 7 — `StepDetails.vue`: Wizard de Creación

**Objetivo:** Mejorar el componente con estados de carga, vacío y error para el select de `assigned_to`. La lista ya vendrá filtrada del backend (solo consultores activos). No se pasa `include_user_id` porque en creación no hay asignación previa.

**Archivo a modificar:**
- `resources/js/src/views/cases/wizard/steps/StepDetails.vue`

### Contexto del componente actual

```
Script setup (líneas 169–201):
  - staffMembers: ref<StaffMember[]>([])    ← sin estado de carga
  - onMounted: getStaff() sin params        ← correcto para creación nueva
  - Error: solo console.error()             ← silencioso para el usuario

Template (líneas 46–61):
  - <select> simple con v-for              ← sin skeleton, sin empty state
  - Si staffMembers = [], el select queda
    con solo "Sin asignar" sin explicación
```

### Cambios en el Script (líneas 169–201)

**Pasos:**
1. **Agregar refs de estado** después de la línea `const staffMembers = ref<StaffMember[]>([])` (línea 179):
   - `const isLoadingStaff = ref(false)`
   - `const staffError = ref(false)`

2. **Extraer la lógica de carga** a una función `loadStaff()` independiente del `onMounted`. Esta función reutilizable permite llamarla desde el botón "Reintentar":
   - Setear `isLoadingStaff = true` y `staffError = false` al inicio
   - Llamar a `userService.getStaff()` **SIN parámetros** — correcto para creación nueva, no hay asignación previa que preservar
   - En éxito: asignar resultado a `staffMembers.value`
   - En error: setear `staffError = true` + `console.error`
   - En `finally`: setear `isLoadingStaff = false`

3. **Simplificar `onMounted`** para que solo llame a `loadStaff()`

### Cambios en el Template — Bloque `<!-- Assigned To -->` (líneas 46–61)

**Pasos:**
1. **Reemplazar el `<select>` directo por un bloque condicional de 4 estados**:

   **Estado de carga** (`v-if="isLoadingStaff"`):
   - Skeleton con `animate-pulse` + `h-10` + `bg-gray-200 dark:bg-gray-700 rounded`
   - Consistente con los skeletons del resto del wizard

   **Estado de error** (`v-else-if="staffError"`):
   - Texto rojo en `text-sm text-danger` usando key `cases.staff_load_error`
   - Botón/link inline `cases.retry` que llame a `loadStaff()`

   **Estado vacío sin error** (`v-else-if="staffMembers.length === 0"`):
   - Mensaje informativo en `text-sm text-warning` usando key `cases.no_active_consultants`
   - El campo queda implícitamente como `null` (no se puede seleccionar a nadie)
   - No bloquea el avance al siguiente paso (el campo es opcional)

   **Estado normal** (`v-else`):
   - El `<select>` original con la opción "Sin asignar" + `v-for` sobre `staffMembers`

### Diagrama de estados del select en StepDetails.vue

```
onMounted → loadStaff()
    │
    ├── isLoadingStaff = true
    │       │
    │       ▼
    │   getStaff()  ← SIN include_user_id (creación nueva)
    │       │
    │   ┌───┴───────────────────────┐
    │   │ Éxito                     │ Error
    │   ▼                           ▼
    │ staffMembers.length?       staffError = true
    │   │                           │
    │   ├─ > 0                      └──► Template: mensaje error + "Reintentar"
    │   │    └──► select normal con lista filtrada
    │   │
    │   └─ = 0
    │        └──► Template: aviso "sin consultores activos"
    │
    └── isLoadingStaff = false (finally)
```

### Traducciones necesarias (en/es/fr)

Agregar dentro del bloque `cases.*` en los tres archivos de locales:

| Key | ES | EN | FR |
|-----|----|----|-----|
| `cases.no_active_consultants` | No hay consultores activos disponibles | No active consultants available | Aucun consultant actif disponible |
| `cases.staff_load_error` | Error al cargar consultores. Intenta de nuevo | Failed to load consultants. Please retry | Erreur lors du chargement des consultants |
| `cases.retry` | Reintentar | Retry | Réessayer |

**Criterio de verificación:**
| Escenario | Comportamiento esperado |
|---|---|
| Tenant con consultores activos | Select muestra lista filtrada (solo consultores activos) |
| Tenant sin consultores activos | Aviso amber visible, campo queda en null, no bloquea el wizard |
| Error de red al cargar | Mensaje de error visible + botón "Reintentar" funcional |
| Durante la carga | Skeleton visible, no flash de select vacío |
| Seleccionar consultor | `wizard.state.caseDetails.assigned_to` se actualiza |
| Step 5 (Resumen) | Nombre del consultor seleccionado aparece en el resumen |
| `KeepAlive` + navegación entre pasos | Estado preservado, no recarga innecesaria |

**Riesgo:** Bajo. El `KeepAlive` del wizard preserva el estado entre pasos — si falla la primera carga, el botón "Reintentar" es la vía de recuperación sin necesidad de navegar.

**Tiempo estimado:** 25 minutos

---

## FASE 8 — `edit.vue`: Edición + Consultor Fantasma

**Objetivo:** En la edición de expedientes, cargar los consultores activos incluyendo siempre al actualmente asignado (aunque ya no cumpla los filtros), y mostrar un indicador visual si es "fantasma".

**Archivo a modificar:**
- `resources/js/src/views/cases/edit.vue`

### El problema del "consultor fantasma"

```
Escenario: Expediente #100 tiene assigned_to = 42 (Juan, antes Consultor).
Juan fue desactivado. El usuario abre el expediente para editar.

Sin solución:
  - getStaff() retorna lista sin Juan
  - form.assigned_to = 42, pero ninguna <option> tiene value="42"
  - El <select> se muestra VACÍO → parece que no hay nadie asignado
  - FALSO POSITIVO de "sin asignar"

Con solución:
  - getStaff(42) retorna lista con Juan marcado is_current_assignment: true
  - El <select> muestra "Juan Pérez (inactivo)" preseleccionado con badge de advertencia
  - Si el usuario guarda sin cambiar: cláusula abuelo lo permite
  - Si el usuario reasigna: se aplica validación estándar
```

### Cambios en el Script

**Pasos:**
1. **Reorganizar la carga de staff**: El componente tiene dos bloques `onMounted`. El primero (líneas 268–270) carga el staff independientemente del caso. Esto es un problema porque necesitamos el `assigned_to` del caso para pasar `include_user_id`. Mover la carga de staff al segundo `onMounted` (línea ~360), dentro del bloque `if (currentCase.value)`, después de que el caso se haya cargado exitosamente.

2. **Actualizar la llamada**: En el segundo `onMounted`, después de `form.assigned_to = currentCase.value.assigned_to`, agregar:
   ```
   staffMembers.value = await userService.getStaff(currentCase.value.assigned_to)
   ```

3. **Eliminar el primer `onMounted`** que solo cargaba staff (ya no es necesario).

4. **Agregar refs de estado** (si no existen): `isLoadingStaff` e `staffError`, siguiendo el mismo patrón de la Fase 7.

### Cambios en el Template — Select de `assigned_to` (líneas 60–74)

**Pasos:**
1. **Agregar indicador visual del fantasma** en la `<option>` del `v-for`. En un `<select>` HTML nativo, el texto es la única forma de diferenciar. Si `staff.is_current_assignment === true`, concatenar al nombre el texto de la key de traducción `cases.inactive_consultant`:
   ```
   {{ staff.name }}{{ staff.is_current_assignment ? ` — ${$t('cases.inactive_consultant')}` : '' }}
   ```

2. **Agregar key de traducción** en los tres archivos de locales:

| Key | ES | EN | FR |
|-----|----|----|-----|
| `cases.inactive_consultant` | (inactivo — se recomienda reasignar) | (inactive — reassignment recommended) | (inactif — réassignation recommandée) |

**Criterio de verificación:**
| Escenario | Comportamiento esperado |
|---|---|
| Caso asignado a consultor activo | Aparece normal en la lista, sin badge |
| Caso asignado a consultor inactivo | Aparece con `— (inactivo)` al final del nombre, preseleccionado |
| Caso asignado a usuario no-consultor | Igual que arriba (fantasma) |
| Caso sin asignación | "Sin asignar" seleccionado, lista solo con consultores activos |
| Guardar sin cambiar asignación fantasma | 200 OK (cláusula abuelo) |
| Reasignar de fantasma a consultor activo | 200 OK, validación estándar |
| Reasignar a consultor inactivo | 422, error descriptivo |

**Riesgo:** Medio. La reorganización de los dos `onMounted` es el punto más delicado. La carga de staff debe estar dentro del `try` del segundo `onMounted` para garantizar ejecución secuencial y evitar race conditions.

**Tiempo estimado:** 30 minutos

---

## FASE 9 — Tests

**Objetivo:** Cubrir todos los escenarios críticos con tests automatizados (PHPUnit).

**Archivos a crear/modificar:**
- `tests/Feature/Api/UserStaffTest.php` (nuevo)
- `tests/Feature/CaseTest.php` (agregar tests)

### Tests en `UserStaffTest.php`

| # | Nombre del test | Setup | Assert |
|---|---|---|---|
| 1 | `test_staff_returns_only_active_consultants` | 3 usuarios mismo tenant: 1 consultor activo, 1 admin activo, 1 consultor inactivo | Retorna solo 1 usuario |
| 2 | `test_staff_with_include_user_id_adds_inactive_as_ghost` | Consultor inactivo ID=X + consultor activo | Retorna 2, el inactivo tiene `is_current_assignment: true` |
| 3 | `test_staff_with_include_user_id_does_not_duplicate_active` | Consultor activo ID=Y | GET con `include_user_id=Y` retorna 1 (no duplica) |
| 4 | `test_staff_does_not_return_other_tenant_users` | Consultor activo en tenant 1 y tenant 2 | Autenticado como tenant 1, solo retorna el del tenant 1 |

### Tests en `CaseTest.php` — `StoreCaseRequest` (Creación)

| # | Nombre del test | Escenario | Resultado esperado |
|---|---|---|---|
| 5 | `test_create_case_with_active_consultant` | `assigned_to` = consultor activo | 201 |
| 6 | `test_cannot_create_case_with_admin_as_assignee` | `assigned_to` = admin activo | 422, error en `assigned_to` |
| 7 | `test_cannot_create_case_with_inactive_consultant` | `assigned_to` = consultor inactivo | 422, error en `assigned_to` |
| 8 | `test_create_case_with_null_assigned_to` | `assigned_to = null` | 201 |

### Tests en `CaseTest.php` — `UpdateCaseRequest` (Edición, cláusula abuelo)

| # | Nombre del test | Escenario | Resultado esperado |
|---|---|---|---|
| 9 | `test_update_case_preserves_inactive_consultant_if_unchanged` | Caso con consultor inactivo, PUT sin cambiar `assigned_to` | **200** — CLÁUSULA ABUELO |
| 10 | `test_update_case_can_reassign_to_active_consultant` | PUT con nuevo `assigned_to` activo | 200 |
| 11 | `test_cannot_update_case_reassigning_to_inactive_consultant` | PUT cambiando `assigned_to` a inactivo | 422 |
| 12 | `test_cannot_update_case_reassigning_to_admin` | PUT cambiando `assigned_to` a admin activo | 422 |
| 13 | `test_update_case_can_unassign` | PUT con `assigned_to = null` | 200 |
| 14 | `test_update_case_without_assigned_to_field_preserves_assignment` | PUT sin el campo `assigned_to` | 200, campo no modificado |

**Tiempo estimado:** 60 minutos

---

## Casos de Borde Documentados

### Caso 1: Consultor Fantasma
**Escenario:** Expediente asignado a Juan (id=5, consultor activo). Admin desactiva a Juan. Usuario abre el expediente para editar.
**Comportamiento:** Frontend llama `getStaff(5)` → backend incluye a Juan con `is_current_assignment: true` → dropdown muestra "Juan Pérez — (inactivo)" preseleccionado → si se guarda sin cambiar, cláusula abuelo permite → si se reasigna a otro, validación estándar.

### Caso 2: Cambio de Rol
**Escenario:** Juan era consultor y tenía casos asignados. Admin le cambia el rol a "apoyo".
**Comportamiento:** Idéntico al Caso 1 — Juan aparece como fantasma porque no cumple `role('consultor')`, pero se incluye via `include_user_id`.

### Caso 3: Desasignación desde Fantasma
**Escenario:** Caso con consultor fantasma asignado. Usuario quiere desasignar (poner "Sin asignar").
**Comportamiento:** Frontend envía `assigned_to: null`. Backend detecta `null != 5` (cambio), pero el nuevo valor es null → desasignación permitida sin validación de rol.

### Caso 4: Request sin campo `assigned_to`
**Escenario:** Formulario de edición envía solo `{ status: 'active', priority: 'high' }`.
**Comportamiento:** `$this->has('assigned_to')` retorna `false` → cláusula abuelo no se ejecuta → campo no modificado en DB.

### Caso 5: Sin Consultores Activos en el Tenant
**Escenario:** Todos los consultores del tenant están inactivos o no existe ninguno con ese rol.
**Comportamiento:** `StepDetails.vue` muestra el aviso "No hay consultores activos disponibles" → el campo queda en null → el caso se crea sin asignación → no se bloquea el flujo.

### Caso 6: Post-migración, Primera Ejecución
**Escenario:** Se ejecuta la migración. Todos los usuarios tienen `is_active = true` (default).
**Comportamiento:** El sistema funciona exactamente igual que antes en términos de qué usuarios aparecen (todos los consultores activos = todos los consultores). El cambio solo se vuelve visible cuando un admin desactiva a algún usuario.

---

## Orden de Implementación y Tiempos

| Paso | Fase | Tiempo est. | Puede ejecutarse en paralelo con | Estado |
|------|------|-------------|----------------------------------|--------|
| 1 | Fase 0: Migración | 15 min | — | ✅ COMPLETADO |
| 2 | Fase 1: Modelo User | 20 min | — | ✅ COMPLETADO |
| 3 | Fase 2: UserService | 30 min | — | ✅ COMPLETADO |
| 4 | Fase 3: UserController | 15 min | — | ✅ COMPLETADO |
| 5 | Fase 4: StoreCaseRequest | 20 min | Fases 5 y 6 | ✅ COMPLETADO |
| 6 | Fase 5: UpdateCaseRequest | 30 min | Fases 4 y 6 | ✅ COMPLETADO |
| 7 | Fase 6: Frontend types + service | 15 min | Fases 4 y 5 | ✅ COMPLETADO |
| 8 | Fase 7: StepDetails.vue | 25 min | Fase 8 | ✅ COMPLETADO |
| 9 | Fase 8: edit.vue | 30 min | Fase 7 | ✅ COMPLETADO |
| 10 | Fase 9: Tests | 60 min | — | ✅ COMPLETADO |
| **Total** | | **~4 horas** | | ✅ |

---

## Verificación Final Post-Implementación ✅ COMPLETADO

1. `php artisan migrate` ejecuta sin errores ✅
2. `GET /api/users/staff` retorna solo consultores activos ✅
3. `GET /api/users/staff?include_user_id=X` incluye al usuario X como fantasma si no cumple filtros ✅
4. Wizard: crear caso asignando a consultor activo → funciona ✅
5. Wizard: crear caso asignando a admin → falla con error 422 descriptivo ✅
6. Wizard: sin consultores activos → mensaje informativo visible, no bloquea el wizard ✅
7. Editar caso sin cambiar `assigned_to` (consultor desactivado) → funciona ✅
8. Editar caso cambiando `assigned_to` a consultor activo → funciona ✅
9. Editar caso cambiando `assigned_to` a usuario inactivo → falla con 422 ✅
10. Dropdown de `edit.vue` muestra el indicador visual para consultores fantasma ✅
11. `./vendor/bin/phpunit --filter="UserStaffTest|CaseTest"` → **62 tests, 272 assertions, 0 failures** ✅

## Archivos Creados/Modificados

| Archivo | Tipo de cambio |
|---------|----------------|
| `database/migrations/2026_03_13_000107_add_is_active_to_users_table.php` | Nuevo — columna `is_active` + índice compuesto |
| `app/Models/User.php` | `is_active` en fillable + cast + 3 scopes + activity log |
| `app/Services/User/UserService.php` | Filtro `activeConsultors()` + patrón consultor fantasma |
| `app/Http/Controllers/Api/UserController.php` | Parámetro `include_user_id` |
| `app/Http/Requests/Case/StoreCaseRequest.php` | Validación rol consultor + is_active |
| `app/Http/Requests/Case/UpdateCaseRequest.php` | Cláusula abuelo para asignaciones históricas |
| `resources/js/src/types/wizard.ts` | `is_current_assignment?: boolean` en StaffMember |
| `resources/js/src/services/userService.ts` | Parámetro opcional `includeUserId` |
| `resources/js/src/views/cases/wizard/steps/StepDetails.vue` | Loading/error/empty states para staff select |
| `resources/js/src/views/cases/edit.vue` | Carga de staff después del caso + indicador fantasma |
| `resources/js/src/locales/en.json` | 4 nuevas keys `cases.*` |
| `resources/js/src/locales/es.json` | 4 nuevas keys `cases.*` |
| `resources/js/src/locales/fr.json` | 4 nuevas keys `cases.*` |
| `tests/Feature/Api/UserStaffTest.php` | Nuevo — 4 tests del endpoint |
| `tests/Feature/CaseTest.php` | 10 nuevos tests (creación + edición + cláusula abuelo) |
