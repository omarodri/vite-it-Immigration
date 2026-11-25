---
tipo: spec
numero: 43
modulo: UX e Internacionalización
dominio: UX
estado: DONE
tags: [spec, ux, done]
---
# [[43_persistencia_filtros_listas|Spec 43]] — Persistencia de Filtros y Ordenamientos en Listas

## Contexto

Las vistas `clients/list.vue`, `cases/list.vue` y `apps/todolist.vue` almacenan su estado de filtros, búsqueda, paginación y ordenamiento en refs locales efímeros. Al navegar al detalle de un registro y regresar, todos los filtros se reinician a sus valores por defecto. Esto obliga al consultor a reconfigurar el contexto de trabajo cada vez.

---

## Análisis Técnico

### 1. Estrategia de Almacenamiento: Evaluación Comparativa

| Estrategia | Ventajas | Desventajas | Veredicto |
|------------|----------|-------------|-----------|
| **URL Query Params** | Compartir enlaces con filtros, botón atrás nativo del browser | Contamina la URL, complejidad de sincronización bidireccional con watchers, los `<select>` y `ref` deben sincronizarse con `useRoute().query` | ❌ Excesivo para este caso |
| **Pinia global** | Reactivo, sin boilerplate de serialización, compatible con devtools | Estado no compartible por URL; sobrevive a recargas solo si se persiste a sessionStorage | ✅ Elegido |
| **sessionStorage directo** | Independiente del framework | Sin reactividad, hay que serializar/deserializar manualmente en cada hook | ❌ Más difícil de mantener |
| **localStorage** | Sobrevive al cierre del browser | Filtros "viejos" (usuario eliminado, tipo de caso borrado) generan confusión; difícil de limpiar | ❌ Demasiado persistente |

**Decisión: Pinia store dedicado + sessionStorage como respaldo opcional.**

- El store Pinia `useListFilterStore` guarda el estado por vista (key: `'clients' | 'cases' | 'todos'`).
- Al montar la vista (`onMounted`), lee el store y restaura los refs — la API se llama una sola vez con los filtros restaurados.
- Sin persistencia a localStorage: los filtros duran la sesión activa y se limpian al cerrar el tab. Esto previene confusiones por datos obsoletos.
- No se usa `beforeRouteLeave` — el store se actualiza reactivamente cada vez que un filtro cambia (watch), eliminando el riesgo de no guardar en edge cases (navegación programática, etc.).

### 2. Estado a Persistir por Vista

**`clients/list.vue`**
```typescript
{ searchQuery, statusFilter, sortColumn, sortDirection, currentPage, perPage }
```

**`cases/list.vue`**
```typescript
{ searchQuery, statusFilter, priorityFilter, stageFilter, caseTypeFilter,
  sortColumn, sortDirection, currentPage, perPage }
```

**`apps/todolist.vue`**
```typescript
{ selectedTab, searchTask, filterAssigneeId, filterCaseId }
// No paginación propia (usa store de todo con currentPage)
```

### 3. Composable `useListPersistence`

Composable genérico que recibe la key de la vista y un objeto de refs:

```typescript
// composables/useListPersistence.ts
export function useListPersistence<T extends Record<string, Ref>>(
    key: 'clients' | 'cases' | 'todos',
    refs: T,
) {
    const store = useListFilterStore();

    // Restore on mount
    function restore() {
        const saved = store.getState(key);
        if (!saved) return false;
        for (const [field, ref] of Object.entries(refs)) {
            if (saved[field] !== undefined) ref.value = saved[field];
        }
        return true; // signals that filters were restored
    }

    // Persist whenever any ref changes
    function persist() {
        const snapshot: Record<string, unknown> = {};
        for (const [field, ref] of Object.entries(refs)) {
            snapshot[field] = ref.value;
        }
        store.setState(key, snapshot);
    }

    function clear() {
        store.clearState(key);
    }

    return { restore, persist, clear };
}
```

Cada vista llama a `restore()` en `onMounted` antes de `fetchData()`. Si `restore()` devuelve `true` (hay estado guardado), la llamada a la API ya incluirá los filtros restaurados. Los watchers llaman a `persist()` en cualquier cambio.

### 4. Store Pinia `useListFilterStore`

```typescript
// stores/listFilters.ts
export const useListFilterStore = defineStore('listFilters', {
    state: () => ({
        states: {} as Record<string, Record<string, unknown>>,
    }),
    actions: {
        getState(key: string) { return this.states[key] ?? null; },
        setState(key: string, data: Record<string, unknown>) { this.states[key] = data; },
        clearState(key: string) { delete this.states[key]; },
    },
});
```

Sin `persist` a localStorage — solo en memoria de la sesión.

### 5. Botón "Limpiar Filtros"

Ya existe en `clients/list.vue` y `cases/list.vue` (`clearFilters()`). Se extiende para llamar a `clear()` del composable, borrando también el estado guardado en el store. Así el usuario no queda "atrapado".

Se agrega un indicador visual sutil cuando hay filtros restaurados: el badge del botón "Limpiar" aparece con un punto de notificación la primera vez que se restaura (desaparece al interactuar).

### 6. Riesgos y Mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Filtro de "asignado" para usuario eliminado | El `<select>` muestra opción vacía al no encontrar el ID; la API simplemente ignora IDs inexistentes; el error es silencioso y no bloquea |
| Filtro de `caseType` eliminado | Misma tolerancia — el select no encuentra la opción, queda en blanco |
| Página guardada > total de páginas actuales | La API devuelve página vacía → detectar y resetear a página 1 automáticamente |
| Conflicto entre stores de clientes y expedientes | Keys distintas: `'clients'`, `'cases'`, `'todos'` — compartimentos separados |
| Usuario navega directo a `/clients` con URL sin query | No hay conflicto; el store restaura independientemente de la URL |

### 7. Arquitectura del Flujo

```
onMounted()
  │
  ├── restore() → lee store['clients']
  │     │
  │     ├── state existe → aplica a refs (searchQuery, statusFilter, etc.)
  │     │     └── fetchClients() ← ya con filtros restaurados (1 sola petición)
  │     │
  │     └── state vacío → fetchClients() con defaults
  │
  └── watch(searchQuery, statusFilter, ...) → persist() en cada cambio

clearFilters()
  ├── resetea refs a defaults
  └── clear() → borra store['clients']
```

---

## Checklist de Implementación

### Fase 1 — Store y Composable

- [x] Crear `resources/js/src/stores/listFilters.ts` — Pinia store con `getState`, `setState`, `clearState`
- [x] Crear `resources/js/src/composables/useListPersistence.ts` — composable genérico con `restore()`, `persist()`, `clear()`

### Fase 2 — `clients/list.vue`

- [x] Importar `useListPersistence` y registrar refs: `{ searchQuery, statusFilter, sortColumn, sortDirection, currentPage, perPage }`
- [x] En `onMounted`: llamar `restore()` antes de `fetchClients()`
- [x] `watchEffect` auto-persiste en cada cambio de filtro
- [x] En `clearFilters()`: añadir `clearPersistedFilters()`
- [x] Guard de página inválida en `fetchClients()`

### Fase 3 — `cases/list.vue`

- [x] Importar `useListPersistence` y registrar refs: `{ searchQuery, statusFilter, priorityFilter, stageFilter, caseTypeFilter, sortColumn, sortDirection, currentPage, perPage }`
- [x] En `onMounted`: llamar `restore()` antes de `fetchCases()`
- [x] `watchEffect` auto-persiste en cada cambio de filtro
- [x] En `clearFilters()`: añadir `clearPersistedFilters()`
- [x] Guard de página inválida en `fetchCases()`

### Fase 4 — `apps/todolist.vue`

- [x] Importar `useListPersistence` y registrar refs: `{ selectedTab, searchTask, filterAssigneeId, filterCaseId }`
- [x] En `onMounted`: llamar `restore()` antes de `refreshTodos()` (reemplazado `fetchTodos()` por `refreshTodos()`)
- [x] `watchEffect` auto-persiste en cada cambio de filtro

---

## Notas de Implementación

- El composable usa `Ref` genérico de Vue — compatible con `ref()` y `ref<T>()`.
- `watchEffect` es preferible a múltiples `watch()` para llamar `persist()` — se ejecuta automáticamente cuando cualquier ref reactivo leído dentro cambia.
- Para la página: solo persistir `currentPage` si es > 1. Al restaurar, si la API devuelve 0 resultados para esa página, resetear `currentPage` a 1 y rellamar.
- `todolist.vue` no tiene paginación propia con refs locales (usa `todoStore.currentPage`); excluir de la persistencia y usar solo los filtros de tabs/búsqueda.
