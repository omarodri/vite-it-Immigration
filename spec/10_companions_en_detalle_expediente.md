# Plan de Implementación: Companions en Detalle de Expediente

## Metadata
- **Fecha:** 2026-03-12
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 2.3 - Vinculación y Visualización de Acompañantes en Expedientes
- **Dependencias:** Spec 09 (Epic 2.2 Case Wizard) — infraestructura Many-to-Many ya implementada
- **Tiempo Total Estimado:** ~2.5 horas (~8 tareas)

---

## Resumen de Tareas

| # | Tarea | Prioridad | Tiempo | Status |
|---|-------|-----------|--------|--------|
| 1.1 | Sección de Companions en `show.vue` | CRÍTICA | 30 min | ⬜ PENDIENTE |
| 1.2 | Traducciones `cases.*` en locales | CRÍTICA | 15 min | ⬜ PENDIENTE |
| 1.3 | Ampliar tipo `ImmigrationCase` (TS) | CRÍTICA | 5 min | ⬜ PENDIENTE |
| 2.1 | `UpdateCaseRequest` acepta `companion_ids` | MEDIA | 20 min | ⬜ PENDIENTE |
| 2.2 | `CaseService::updateCase()` sincroniza companions | MEDIA | 20 min | ⬜ PENDIENTE |
| 2.3 | `UpdateCaseData` type acepta `companion_ids` | MEDIA | 5 min | ⬜ PENDIENTE |
| 2.4 | Sección de edición de companions en `edit.vue` | MEDIA | 45 min | ⬜ PENDIENTE |
| 3.1 | Proteger `deleteCompanion()` contra casos activos | BAJA | 15 min | ⬜ PENDIENTE |

---

## Diagnóstico de Estado Actual

### Lo que YA está implementado ✅

```
✅ database/migrations/2026_02_11_190659_create_case_companions_table.php
   - Pivot Many-to-Many con UNIQUE(case_id, companion_id)
   - CASCADE ON DELETE en ambas FK

✅ app/Models/ImmigrationCase.php
   - companions(): BelongsToMany via case_companions

✅ app/Models/Companion.php
   - cases(): BelongsToMany inverso

✅ app/Services/Case/CaseService.php
   - createCase(): transacción atómica con sync($companionIds)
   - getCase(): eager loading ->load(['client','caseType','assignedTo','companions'])

✅ app/Http/Resources/CaseResource.php
   - 'companions' => $this->whenLoaded('companions', CompanionResource::collection)

✅ app/Http/Requests/Case/StoreCaseRequest.php
   - Valida companion_ids[], pertenencia al cliente y al tenant

✅ resources/js/src/composables/useCaseWizard.ts
   - selectedCompanionIds[], toggleCompanion(), setCompanions()
   - Persistencia en sessionStorage
   - submit() envía companion_ids en el payload

✅ resources/js/src/views/cases/wizard/steps/StepCompanions.vue
   - UI de selección de companions en el wizard
```

### Lo que FALTA ❌

```
❌ resources/js/src/views/cases/show.vue
   - Tabs actuales: ['info', 'timeline', 'documents']
   - Ningún tab ni sección muestra los companions vinculados

❌ app/Services/Case/CaseService.php — updateCase()
   - No hace sync de companion_ids al actualizar un expediente

❌ app/Http/Requests/Case/UpdateCaseRequest.php
   - No valida companion_ids

❌ app/Services/Companion/CompanionService.php — deleteCompanion()
   - No valida si el companion está vinculado a casos activos
```

---

## Prioridad 1 — Crítica: Visualización en `show.vue`

### Tarea 1.1 — Sección de Companions en `show.vue`

**Archivo:** `resources/js/src/views/cases/show.vue`

**Ubicación exacta:** Dentro del tab `info` (líneas 80-205), columna derecha (`<!-- Client & Dates -->`), **entre** el bloque `<!-- Client Information -->` y el bloque `<!-- Important Dates -->`.

**Cambios específicos:**

1. Agregar sección con el patrón visual existente de client info card:
   - Contenedor: `<div class="space-y-4">`
   - Título: `<h3 class="font-semibold text-lg dark:text-white-light">` con key `cases.companions`
   - Mostrar contador: `({{ currentCase.companions.length }})` junto al título
   - Condición: `v-if="currentCase.companions && currentCase.companions.length > 0"`
   - Estado vacío: `v-else` con mensaje `cases.no_companions` en gris

2. Cada companion card con `v-for="companion in currentCase.companions" :key="companion.id"`:
   - Contenedor: `flex items-center gap-4 p-3 border border-gray-200 dark:border-gray-700 rounded-lg`
   - Avatar: círculo con iniciales, color `bg-secondary/10 text-secondary` (distinto del cliente que usa `primary`)
   - Iniciales: reusar `getInitials(companion.first_name, companion.last_name)` (ya existe en línea 286)
   - Nombre: `companion.full_name || companion.first_name + ' ' + companion.last_name`
   - Badge de relación: `<span class="badge badge-outline-secondary">{{ companion.relationship_label }}</span>`
   - Edad y nacionalidad opcionales: `text-sm text-gray-500` separados por punto medio

3. **No agregar** botón de enlace al perfil del companion en esta iteración (los perfiles de companions están anidados bajo clientes, lo que complica la ruta — dejar para iteración futura).

**Riesgo:** BAJO. Solo se agrega HTML al template. No se modifica lógica del script. Los datos ya llegan: `CaseService::getCase()` ya hace eager loading y `CaseResource` ya serializa companions.

**Dependencias:** Requiere Tareas 1.2 y 1.3 completadas primero.

**Propuesta visual:**
```
+----------------------------------------------------------+
| Acompañantes (3)                                          |
+----------------------------------------------------------+
| [AM]  Ana Martinez                                        |
|       Cónyuge  ·  35 años  ·  Mexicana                   |
+----------------------------------------------------------+
| [JM]  Juan Martinez                                       |
|       Hijo  ·  8 años  ·  Mexicano                       |
+----------------------------------------------------------+
| [LM]  Laura Martinez                                      |
|       Hija  ·  5 años  ·  Mexicana                       |
+----------------------------------------------------------+
```

---

### Tarea 1.2 — Traducciones para la sección de Companions

**Archivos:**
- `resources/js/src/locales/en.json`
- `resources/js/src/locales/es.json`
- `resources/js/src/locales/fr.json`

**Keys a agregar** dentro del bloque `cases` existente:

| Key | EN | ES | FR |
|-----|----|----|-----|
| `cases.companions` | Companions | Acompañantes | Accompagnants |
| `cases.no_companions` | No companions linked to this case | No hay acompañantes vinculados a este expediente | Aucun accompagnant lié à ce dossier |
| `cases.tab_companions` | Companions | Acompañantes | Accompagnants |

> Nota: Las keys `companions.*` (tipos de relación) ya existen en los tres archivos. Solo agregar las nuevas keys de `cases.*`.

**Riesgo:** NULO. Agregar keys de traducción no afecta código existente.

---

### Tarea 1.3 — Ampliar tipo inline de companions en `ImmigrationCase`

**Archivo:** `resources/js/src/types/case.ts`

**Ubicación:** Propiedad `companions?` dentro del interface `ImmigrationCase`.

**Cambio:** El tipo inline actual tiene: `id`, `first_name`, `last_name`, `full_name`, `relationship`, `relationship_label`. Agregar:
- `age?: number`
- `nationality?: string`
- `date_of_birth?: string`
- `gender?: string`
- `initials?: string`

> No importar el tipo `Companion` de `types/companion.ts` — mantener el tipo inline para no crear acoplamiento entre módulos.

**Riesgo:** NULO. Solo propiedades opcionales.

---

## Prioridad 2 — Media: Edición post-creación

### Tarea 2.1 — `UpdateCaseRequest` acepta `companion_ids`

**Archivo:** `app/Http/Requests/Case/UpdateCaseRequest.php`

**Cambios:**

1. **En `rules()`:** Agregar al array de retorno:
   ```
   'companion_ids' => ['sometimes', 'array'],
   'companion_ids.*' => ['integer', 'exists:companions,id'],
   ```

2. **En `withValidator()`:** Agregar validación de pertenencia al cliente. El `client_id` se obtiene del caso existente (no del request):
   ```php
   $clientId = $this->route('case')->client_id;
   ```
   Luego validar que todos los `companion_ids` pertenezcan a ese cliente y al tenant del usuario autenticado. Mismo patrón que `StoreCaseRequest` líneas 84-96.

3. **Agregar imports** al inicio: `use App\Models\Companion;`

4. **En `messages()`:** Agregar:
   ```
   'companion_ids.array' => 'Companions must be provided as a list.',
   'companion_ids.*.exists' => 'One or more selected companions do not exist.',
   ```

**Riesgo:** BAJO. Reglas `sometimes` solo se activan si el campo está presente en el request. Requests existentes no se ven afectados.

---

### Tarea 2.2 — `CaseService::updateCase()` sincroniza companions

**Archivo:** `app/Services/Case/CaseService.php`

**Ubicación:** Método `updateCase()`, líneas 86-104.

**Cambios — dentro del bloque `DB::transaction`:**

1. **Extraer `companion_ids` antes del update** (mismo patrón que `createCase()` líneas 46-47):
   ```php
   $companionIds = array_key_exists('companion_ids', $data) ? $data['companion_ids'] : null;
   unset($data['companion_ids']);
   ```

2. **Después de `$this->caseRepository->update()`**, agregar sync condicional:
   ```php
   if ($companionIds !== null) {
       $updatedCase->companions()->sync($companionIds);
   }
   ```
   > Usar `!== null` (no `!empty`) para que un array vacío `[]` desvincule todos los companions correctamente.

3. **En el activity log**, incluir la propiedad `companions_synced` cuando se modifiquen:
   ```php
   'companions_count' => $companionIds !== null ? count($companionIds) : null,
   ```

4. **Cambiar el return** de `return $updatedCase;` a:
   ```php
   return $updatedCase->load(['client', 'caseType', 'assignedTo', 'companions']);
   ```

**Riesgo:** MEDIO. Puntos críticos a verificar:
- El `unset($data['companion_ids'])` DEBE hacerse antes de `$this->caseRepository->update()` para evitar error de columna inexistente en la tabla `cases`.
- El `sync()` está dentro de la transacción existente — si falla, se hace rollback completo.
- Si `companion_ids` no viene en el request, `$companionIds` es `null` y no se ejecuta el sync.

**Dependencias:** Requiere Tarea 2.1 completada.

---

### Tarea 2.3 — Agregar `companion_ids` al tipo `UpdateCaseData`

**Archivo:** `resources/js/src/types/case.ts`

**Ubicación:** Interface `UpdateCaseData`.

**Cambio:** Agregar `companion_ids?: number[];` como propiedad opcional.

**Riesgo:** NULO. Propiedad opcional, no afecta código existente.

---

### Tarea 2.4 — Sección de edición de companions en `edit.vue`

**Archivo:** `resources/js/src/views/cases/edit.vue`

**Cambios:**

1. **En el template:** Agregar sección de companions después del bloque `<!-- Description -->`, antes de los closure notes. Usar checkboxes con el mismo estilo visual de `StepCompanions.vue` del wizard (cards con avatar de iniciales + checkbox).

2. **En el script:**
   - Agregar `companion_ids: [] as number[]` al reactive `form`
   - En `onMounted`, después de cargar el caso, popular `form.companion_ids` con los IDs de `currentCase.value.companions?.map(c => c.id) ?? []`
   - Cargar la lista de companions del cliente mediante `GET /api/clients/{client_id}/companions` (endpoint ya existente)
   - Guardar en un `ref<Companion[]>` local: `availableCompanions`
   - En `handleSubmit`, incluir `companion_ids: form.companion_ids` en el payload

3. **Función de toggle local:**
   ```typescript
   function toggleCompanion(id: number) {
       const idx = form.companion_ids.indexOf(id);
       if (idx === -1) form.companion_ids.push(id);
       else form.companion_ids.splice(idx, 1);
   }
   ```

**Riesgo:** MEDIO. Requiere una llamada adicional a la API para obtener todos los companions del cliente. Verificar que el endpoint `GET /api/clients/{id}/companions` funciona y devuelve los datos necesarios.

**Dependencias:** Requiere Tareas 2.1, 2.2 y 2.3 completadas.

---

## Prioridad 3 — Baja: Robustez

### Tarea 3.1 — Proteger `deleteCompanion()` contra casos activos

**Archivo:** `app/Services/Companion/CompanionService.php`

**Ubicación:** Método `deleteCompanion()`.

**Cambios:**

1. **Antes de la eliminación**, agregar validación:
   ```php
   $activeCasesCount = $companion->cases()
       ->whereNotIn('status', [
           ImmigrationCase::STATUS_CLOSED,
           ImmigrationCase::STATUS_ARCHIVED,
       ])
       ->count();

   if ($activeCasesCount > 0) {
       abort(422, "No se puede eliminar: el acompañante está vinculado a {$activeCasesCount} expediente(s) activo(s).");
   }
   ```

2. **Agregar import:** `use App\Models\ImmigrationCase;`

**Alternativa:** Crear `app/Exceptions/CompanionHasActiveCasesException.php` extendiendo `\DomainException` para manejo más elegante, capturando en `CompanionController` y devolviendo 422 con mensaje descriptivo.

**Riesgo:** BAJO. Solo agrega una validación previa al delete. Requiere que `Companion::cases()` exista (ya confirmado).

---

## Diagrama de Dependencias

```
PRIORIDAD 1 (se pueden hacer en paralelo entre sí):
  Tarea 1.2 (traducciones)      ──┐
  Tarea 1.3 (tipos TS)          ──┼──► Tarea 1.1 (UI show.vue)
                                  │
PRIORIDAD 2 (secuencial):         │
  Tarea 2.1 (UpdateRequest)  ──► Tarea 2.2 (CaseService) ──┐
  Tarea 2.3 (UpdateCaseData)  ────────────────────────────┤
                                                            └──► Tarea 2.4 (edit.vue)
PRIORIDAD 3 (independiente):
  Tarea 3.1 (deleteCompanion) ── sin dependencias
```

---

## Orden de Ejecución Recomendado

| Paso | Tarea | Tiempo | Archivo principal |
|------|-------|--------|-------------------|
| 1 | 1.2 + 1.3 | 15 min | `locales/*.json` + `types/case.ts` |
| 2 | 1.1 | 30 min | `views/cases/show.vue` |
| 3 | 2.1 | 20 min | `Http/Requests/Case/UpdateCaseRequest.php` |
| 4 | 2.2 | 20 min | `Services/Case/CaseService.php` |
| 5 | 2.3 | 5 min | `types/case.ts` |
| 6 | 2.4 | 45 min | `views/cases/edit.vue` |
| 7 | 3.1 | 15 min | `Services/Companion/CompanionService.php` |

**Total estimado:** ~2.5 horas de desarrollo.

---

## Verificaciones Post-Implementación

| Tarea | Verificación |
|-------|-------------|
| 1.1 | Navegar a `/cases/{id}` con caso con companions → deben mostrarse. Probar con caso sin companions → debe mostrar estado vacío. |
| 2.2 | `PATCH /api/cases/{id}` con `companion_ids: [1,2]` → pivot actualizado. Con `companion_ids: []` → todos desvinculados. Sin `companion_ids` → pivot sin cambios. |
| 2.4 | Editar caso, cambiar companions, guardar, volver al detalle → cambios persisten. |
| 3.1 | Intentar eliminar companion con caso activo → error 422. Eliminar companion solo en casos cerrados → permitido. |

---

## Análisis de Riesgos

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Companions no visibles en detalle del caso | 🔴 Certeza (bug actual) | Alto | Implementar Tarea 1.1 |
| `updateCase()` guarda `companion_ids` en tabla `cases` | 🟡 Media | Alto | `unset()` antes del `->update()` |
| Sync en update desvincula companions involuntariamente | 🟡 Media | Medio | Usar `!== null`, no `!empty` |
| N+1 en listado de casos si se carga companions | 🟢 Baja | Bajo | No agregar companions al `listCases()` |
| Force-delete de companion rompe pivot | 🟢 Baja | Bajo | Tarea 3.1 |
| Race condition en edición simultánea | 🟢 Muy baja | Bajo | Fuera de scope — optimistic locking en iteración futura |
