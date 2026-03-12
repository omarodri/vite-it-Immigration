# Fases de Implementacion Backend - Epic 2.3: Sincronizacion de Companions en Casos

| Campo | Valor |
|-------|-------|
| **Fecha** | 2026-03-12 |
| **Tipo** | Backend Implementation Phases |
| **Epic** | 2.3 |
| **Alcance** | Validacion, sincronizacion y proteccion de companions en el flujo de casos |
| **Archivos impactados** | 3 archivos de produccion + 2 archivos de tests |
| **Estado** | ✅ COMPLETADO |

---

## Resumen de Gaps

| Gap | Descripcion | Severidad | Estado |
|-----|-------------|-----------|--------|
| Gap 1 | `UpdateCaseRequest` no valida `companion_ids` | Media-Alta | ✅ COMPLETADO |
| Gap 2 | `CaseService::updateCase()` no sincroniza companions | Alta | ✅ COMPLETADO |
| Gap 3 | `CompanionService::deleteCompanion()` no protege contra casos activos | Alta | ✅ COMPLETADO |

---

## Fase 1: Excepcion de dominio para companion vinculado a caso activo ✅ COMPLETADO

**Objetivo:** Crear una excepcion personalizada del dominio que sera utilizada en la Fase 4 (Gap 3) para rechazar la eliminacion de companions vinculados a casos activos. Se implementa primero porque es una dependencia de la Fase 4 y porque establece el patron de excepciones de dominio que actualmente no existe en el proyecto.

**Archivos creados:**
- `app/Exceptions/CompanionHasActiveCasesException.php` ✅
- `app/Exceptions/Handler.php` (modificado) ✅

**Pasos:**

1. Crear el directorio `app/Exceptions/` si no existe un subdirectorio para excepciones de dominio (actualmente solo existe `Handler.php` en ese directorio).

2. Crear la clase `CompanionHasActiveCasesException` que extienda de `\RuntimeException` (o `\DomainException` si se prefiere semantica de dominio).

3. La excepcion debe:
   - Tener una propiedad privada para almacenar el conteo de casos activos vinculados.
   - Tener un constructor que reciba el nombre del companion y el numero de casos activos, y construya un mensaje descriptivo como: "Cannot delete companion '{nombre}' because they are linked to {n} active case(s)."
   - Tener un metodo publico `getActiveCasesCount(): int` para que el controlador pueda acceder al conteo si lo necesita.
   - Definir un metodo estatico `forCompanion(Companion $companion, int $activeCasesCount)` como named constructor para facilitar su uso en el servicio.

4. Registrar el manejo de esta excepcion en `app/Exceptions/Handler.php`:
   - En el metodo `register()`, dentro del callback de `$this->renderable()`, agregar un handler que capture `CompanionHasActiveCasesException` y retorne un `JsonResponse` con status 422 y un cuerpo que incluya `message` y `errors.companion` con un array describiendo el error.
   - Esto permite que el controlador no necesite un try/catch explicito; la excepcion se convierte automaticamente en respuesta HTTP 422.

**Criterio de verificacion:**
- La clase de excepcion existe y es instanciable. ✅
- Al lanzar la excepcion en un contexto HTTP, el Handler la captura y retorna un JSON con status 422. ✅
- El mensaje de error es descriptivo e incluye el nombre del companion y la cantidad de casos. ✅

**Riesgo y mitigacion:**
- **Riesgo:** Que otros desarrolladores usen `abort(422)` en lugar de la excepcion, rompiendo la consistencia.
- **Mitigacion:** Documentar en el PHPDoc del servicio que este metodo lanza `CompanionHasActiveCasesException`.

**Tiempo estimado:** 30 minutos

---

## Fase 2: Validacion de companion_ids en UpdateCaseRequest (Gap 1) ✅ COMPLETADO

**Objetivo:** Agregar las reglas de validacion para `companion_ids` en el Form Request de actualizacion de caso, replicando la logica que ya existe en `StoreCaseRequest` pero adaptada al contexto de update (donde el `client_id` se obtiene del caso existente, no del request).

**Archivos modificados:**
- `app/Http/Requests/Case/UpdateCaseRequest.php` ✅

**Pasos:**

1. **Agregar imports faltantes** en la seccion de `use` del archivo:
   - `App\Models\Companion` (necesario para la query de validacion en `withValidator`).
   - `Illuminate\Support\Facades\Auth` (ya esta importado, verificar).

2. **Agregar reglas en el metodo `rules()`**, dentro del array de retorno, despues de la linea de `assigned_to`:
   - Agregar la regla para `companion_ids` con las restricciones: `sometimes`, `array` (notar que aqui se usa `sometimes` y no `nullable` como en Store, porque en un update solo se debe procesar si viene en el request).
   - Agregar la regla para `companion_ids.*` con: `integer`, `exists:companions,id`.

3. **Extender el metodo `withValidator()`** para agregar la validacion de pertenencia de companions al cliente del caso:
   - Dentro del callback `$validator->after()`, despues del bloque existente de validacion de `assigned_to`, agregar un nuevo bloque condicional.
   - Obtener el `client_id` desde el caso existente vinculado a la ruta: `$this->route('case')->client_id`. Esto es diferente a `StoreCaseRequest` donde se usa `$this->client_id` del request.
   - Solo ejecutar la validacion si `companion_ids` esta presente en el request y no esta vacio.
   - Ejecutar una query a `Companion::withoutGlobalScopes()` filtrando por `client_id`, `tenant_id` y `whereIn('id', $this->companion_ids)`.
   - Comparar con `array_diff()` para encontrar IDs invalidos.
   - Si hay IDs invalidos, agregar error al validator con key `companion_ids`.

4. **Agregar mensajes custom** en el metodo `messages()`.

**Criterio de verificacion:**
- Un request PUT/PATCH a `/api/cases/{id}` con `companion_ids` de otro cliente retorna 422. ✅
- Un request PUT/PATCH sin `companion_ids` sigue funcionando normalmente. ✅
- Un request PUT/PATCH con `companion_ids: []` pasa la validacion. ✅

**Tiempo estimado:** 45 minutos

---

## Fase 3: Sincronizacion de companions en CaseService::updateCase() (Gap 2) ✅ COMPLETADO

**Objetivo:** Modificar el metodo `updateCase()` del servicio para que extraiga `companion_ids` del array de datos, ejecute el `sync()` en la relacion pivot dentro de la transaccion existente, y registre el cambio en el activity log.

**Archivos modificados:**
- `app/Services/Case/CaseService.php` ✅

**Pasos:**

1. **Extraer `companion_ids` del array `$data`** al inicio del closure de `DB::transaction()`:
   - Usar `array_key_exists('companion_ids', $data)` (NO `isset` ni `!empty`, para permitir array vacio).
   - `unset($data['companion_ids'])` para que no se pase al repositorio.
   - Si no existe la key, asignar `null` a `$companionIds`.

2. **Capturar el estado anterior de companions** para el activity log (solo si `$companionIds !== null`).

3. **Ejecutar el sync** despues de `$this->caseRepository->update()`, solo si `$companionIds !== null`.

4. **Actualizar el activity log** para incluir `old_companion_ids` y `new_companion_ids` condicionalmente.

5. **Retornar** `$updatedCase->load(['client', 'caseType', 'assignedTo', 'companions'])`.

**Criterio de verificacion:**
- PUT con `companion_ids: [1,2,3]` sincroniza la tabla pivot. ✅
- PUT con `companion_ids: []` elimina todos los companions del caso. ✅
- PUT sin `companion_ids` no modifica los companions existentes. ✅
- La respuesta JSON incluye el array de companions actualizado. ✅
- El activity log registra los IDs anteriores y nuevos. ✅

**Tiempo estimado:** 1 hora

---

## Fase 4: Proteccion contra eliminacion de companion vinculado a casos activos (Gap 3) ✅ COMPLETADO

**Objetivo:** Agregar una verificacion en `CompanionService::deleteCompanion()` que impida el soft-delete de un companion si este esta vinculado a al menos un caso con status activo o inactivo.

**Archivos modificados:**
- `app/Services/Companion/CompanionService.php` ✅

**Pasos:**

1. **Agregar imports**: `CompanionHasActiveCasesException` e `ImmigrationCase`.

2. **Agregar la verificacion** al inicio del metodo `deleteCompanion()`:
   - `$companion->cases()->withoutGlobalScopes()->whereNotIn('cases.status', [STATUS_CLOSED, STATUS_ARCHIVED])->count()`
   - Si `$activeCasesCount > 0`, lanzar `CompanionHasActiveCasesException::forCompanion($companion, $activeCasesCount)`.

3. **No modificar el controlador** `CompanionController::destroy()` (el Handler maneja la excepcion automaticamente).

**Criterio de verificacion:**
- DELETE companion con caso `active` → HTTP 422. ✅
- DELETE companion con caso `inactive` → HTTP 422. ✅
- DELETE companion solo con casos `closed` → permitido. ✅
- DELETE companion solo con casos `archived` → permitido. ✅
- DELETE companion sin casos → permitido. ✅

**Tiempo estimado:** 45 minutos

---

## Fase 5: Tests de feature para los tres gaps ✅ COMPLETADO

**Objetivo:** Agregar tests de feature que cubran los tres gaps implementados.

**Archivos modificados:**
- `tests/Feature/CaseTest.php` — 4 nuevos tests (Gap 1 + Gap 2) ✅
- `tests/Feature/CompanionTest.php` — 3 nuevos tests (Gap 3) ✅
- `database/factories/ImmigrationCaseFactory.php` — agregado state `inactive()` ✅

**Tests agregados en CaseTest.php:**
- `test_can_update_case_companions` ✅
- `test_can_remove_all_companions_from_case` ✅
- `test_cannot_update_case_with_companions_from_different_client` ✅
- `test_update_without_companion_ids_preserves_existing` ✅

**Tests agregados en CompanionTest.php:**
- `test_cannot_delete_companion_linked_to_active_case` ✅
- `test_can_delete_companion_linked_only_to_closed_cases` ✅
- `test_can_delete_companion_linked_only_to_archived_cases` ✅

**Resultado de ejecucion:** 85 tests, 318 assertions — todos pasan ✅

**Tiempo estimado:** 1 hora 30 minutos

---

## Diagrama de Dependencias

```
Fase 1 (Excepcion de dominio)
    |
    v
Fase 2 (UpdateCaseRequest) -----> Fase 3 (CaseService::updateCase)
    |                                   |
    |                                   v
    |                             Fase 5A (Tests Case)
    v
Fase 4 (CompanionService::deleteCompanion)
    |
    v
Fase 5B (Tests Companion)
```

---

## Resumen de Tiempos

| Fase | Descripcion | Tiempo estimado | Estado |
|------|-------------|-----------------|--------|
| Fase 1 | Excepcion de dominio | 30 min | ✅ COMPLETADO |
| Fase 2 | UpdateCaseRequest validacion | 45 min | ✅ COMPLETADO |
| Fase 3 | CaseService sync companions | 1 hora | ✅ COMPLETADO |
| Fase 4 | CompanionService proteccion delete | 45 min | ✅ COMPLETADO |
| Fase 5 | Tests de feature | 1 hora 30 min | ✅ COMPLETADO |
| **Total** | | **4 horas 30 min** | ✅ |

---

## Verificacion Final (Post-implementacion)

1. Suite de tests ejecutada: `./vendor/bin/phpunit --filter="CaseTest|CompanionTest"` → **85 tests, 318 assertions, 0 failures** ✅
2. No hay regresiones en tests existentes de CaseTest y CompanionTest. ✅
3. Bugs preexistentes corregidos: migration `create_case_types_table` (2 filas sin clave `description`), `CaseType` model (constante `CATEGORY_CITIZENSHIP` faltante). ✅

**Archivos creados/modificados en esta implementacion:**
- `app/Exceptions/CompanionHasActiveCasesException.php` (nuevo)
- `app/Exceptions/Handler.php`
- `app/Http/Requests/Case/UpdateCaseRequest.php`
- `app/Services/Case/CaseService.php`
- `app/Services/Companion/CompanionService.php`
- `database/factories/ImmigrationCaseFactory.php`
- `database/migrations/2026_02_08_221203_create_case_types_table.php` (bug fix)
- `app/Models/CaseType.php` (bug fix)
- `tests/Feature/CaseTest.php`
- `tests/Feature/CompanionTest.php`
