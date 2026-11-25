---
tipo: spec
numero: 41
modulo: UX e Internacionalización
dominio: UX
estado: DONE
tags: [spec, ux, done]
---
# [[41_busqueda_global|Spec 41]] — Búsqueda Global (Global Search)

## Contexto

La aplicación cuenta con múltiples recursos (clientes, expedientes, acompañantes, eventos, tareas, papelera) que hoy solo se pueden localizar navegando a cada sección. Se requiere un motor de búsqueda unificado accesible desde el Header en cualquier pantalla de la plataforma.

---

## Análisis Técnico

### 1. Endpoint Unificado vs. Consultas Concurrentes

**Decisión: Endpoint unificado** (`GET /api/search?q=`).

| Criterio | Unificado | Concurrente (N llamadas) |
|----------|-----------|--------------------------|
| Round-trips | 1 | 5 (clients, cases, companions, events, todos) |
| Control de permisos | 1 punto, en el servidor | Disperso por controlador |
| Ordenación cross-resource | Posible en PHP | Imposible sin post-merge en JS |
| Cache | 1 clave de caché | 5 claves, difícil invalidar |
| Saturación en debounce | 1 req/300 ms | 5 req/300 ms |

Con 5 tablas y ≤ 5 resultados por tipo, una sola consulta unificada es más eficiente y mantenible.

### 2. Estrategia de Búsqueda: LIKE vs. Full-Text Search

**Fase 1 (implementación actual): LIKE con índices compuestos.**

- MySQL `LIKE '%term%'` no usa B-Tree, pero con un volumen < 50 000 registros por tabla el impacto es menor de 50 ms.
- Los índices `(tenant_id, deleted_at)` ya creados en [[40_trash_papelera_reciclaje|Spec 40]] limitan el scope por tenant.
- Se agregan índices adicionales sobre las columnas de búsqueda frecuente (ver Fase 1 backend).

**Fase 2 (crecimiento > 50k filas): Laravel Scout + MySQL Full-Text o Meilisearch.**

- Scout permite añadir `use Searchable` a cada modelo sin modificar el controlador.
- Meilisearch soporta typo-tolerance y resultados en < 5 ms independiente del volumen.
- Se documenta la ruta de migración en el checklist pero **no se implementa ahora**.

### 3. Seguridad y Permisos

Cada fuente de datos pasa por su Policy existente (`viewAny`). El `GlobalSearchService` construye sub-consultas solo para los tipos que el usuario autenticado puede ver:

```
clients    → ClientPolicy::viewAny    → permiso clients.view
cases      → CasePolicy::viewAny      → permiso cases.view
companions → CompanionPolicy::viewAny → permiso companions.view
events     → EventPolicy::viewAny     → permiso events.view
todos      → TodoPolicy::viewAny      → permiso todos.view (propios del usuario)
trash      → trash.view
```

Si el usuario no tiene el permiso correspondiente, esa fuente simplemente se omite del resultado — nunca devuelve 403, devuelve vacío para ese tipo.

### 4. Indexación de Base de Datos

Nuevos índices a agregar (migración dedicada):

| Tabla | Índice |
|-------|--------|
| `clients` | `(tenant_id, deleted_at, first_name, last_name)` |
| `clients` | `(tenant_id, deleted_at, email)` |
| `cases` | `(tenant_id, deleted_at, case_number)` |
| `cases` | `(tenant_id, deleted_at, description)` |
| `companions` | `(tenant_id, deleted_at, first_name, last_name)` |
| `events` | `(tenant_id, title)` |
| `todos` | `(tenant_id, title)` |

> MySQL puede usar índices parciales de hasta 767 bytes; `first_name` + `last_name` caben sin problema. Los índices compuestos que empiezan con `tenant_id` son la optimización más importante (reduce el conjunto escaneado al tenant activo).

### 5. Latencia y Límite de Resultados

- Máximo **5 resultados por tipo** (total ≤ 35). Límite fijo en backend, no configurable desde frontend.
- La búsqueda no inicia hasta que el query tenga **≥ 2 caracteres**.
- Debounce de **300 ms** en frontend.
- Si la consulta tarda > 800 ms se muestra un spinner; si tarda > 5 s se muestra error genérico.

### 6. Esquema de Respuesta API

```json
{
  "query": "omar",
  "results": [
    {
      "id": 42,
      "type": "client",
      "label": "Omar Rodriguez",
      "description": "omar@email.com · Activo",
      "route": "/clients/42",
      "meta": {}
    },
    {
      "id": 7,
      "type": "case",
      "label": "26-RT-RODR-0060",
      "description": "Residencia Permanente · En Proceso",
      "route": "/cases/7",
      "meta": {}
    }
  ],
  "totals": {
    "clients": 1,
    "cases": 1,
    "companions": 0,
    "events": 0,
    "todos": 0
  }
}
```

`route` es una URL relativa (no `route_name`) — el frontend hace `router.push(item.route)` directamente, sin necesidad de resolver named routes en JS.

### 7. UX / Accesibilidad

- `Ctrl+K` / `Cmd+K`: abre y enfoca el campo de búsqueda.
- Flechas `↑`/`↓`: navegan por los resultados.
- `Enter`: selecciona el resultado activo.
- `Escape`: cierra el dropdown.
- `aria-live="polite"` en el contenedor de resultados para lectores de pantalla.
- Resultado activo con `role="option"` y `aria-selected`.

---

## Arquitectura

```
Header.vue
  │ (v-model, debounce 300ms, min 2 chars)
  ▼
GET /api/search?q=omar&limit=5
  │
  ▼
GlobalSearchController
  │
  ▼
GlobalSearchService
  ├── if clients.view   → Client::onlyActive()->search(q)->limit(5)
  ├── if cases.view     → ImmigrationCase::search(q)->limit(5)
  ├── if companions.view→ Companion::search(q)->limit(5)
  ├── if events.view    → Event::search(q)->limit(5)
  ├── if todos.view     → Todo::search(q)->where(user)->limit(5)
  └── if trash.view     → TrashService::globalSearch(q)->limit(5)
  │
  ▼
SearchResultResource (uniform DTO)
  │
  ▼
JSON { results: [], totals: {} }
```

---

## Checklist de Implementación

### Fase 1 — Backend: Migración e Índices

- [x] Crear migración `2026_04_14_000002_add_global_search_indexes.php` con índices compuestos por tabla

### Fase 2 — Backend: Service y Controlador

- [x] Crear `app/Services/Search/GlobalSearchService.php`
  - Gate checks por permiso antes de cada sub-consulta
  - Caché de 30s por `(tenant_id, query)` con `Cache::remember`
  - Retorna colección de `SearchResultDTO` agrupada por tipo
- [x] Crear `app/DTOs/SearchResultDTO.php` (readonly class con `id, type, label, description, route`)
- [x] Crear `app/Http/Controllers/Api/GlobalSearchController.php`
  - `GET /api/search?q=&limit=` → valida q ≥ 2 chars, llama al service
- [x] Agregar ruta en `routes/api.php` dentro del grupo autenticado con `throttle:60,1`

### Fase 3 — Frontend: Tipos y Servicio

- [x] Crear `resources/js/src/types/search.ts` — interfaces `SearchResult`, `SearchResponse`
- [x] Crear `resources/js/src/services/searchService.ts` — llama `GET /api/search` y aplana resultados agrupados a array plano

### Fase 4 — Frontend: Componente y Header

- [x] Crear `resources/js/src/components/layout/GlobalSearch.vue`
  - Input con debounce 300ms inline
  - Estado: `query`, `results`, `isLoading`, `activeIndex`, `isOpen`
  - Dropdown con badge de tipo por color + label + description
  - Navegación teclado: `↑`/`↓` Arrow, Enter, Escape
  - Atajo `Ctrl+K` / `Cmd+K` vía `onMounted` global listener
  - Click-outside nativo sobre clase `.global-search-container`
  - `router.push(item.route)` al seleccionar
  - Accesibilidad: `role="combobox"`, `aria-expanded`, `role="listbox"`, `role="option"`, `aria-selected`, `aria-live="polite"`
- [x] Reemplazar el bloque `<!-- Search -->` en `Header.vue` con `<GlobalSearch />`
- [x] Agregar i18n keys `search.*` en `es.json`, `en.json`, `fr.json`

### Fase 5 — UX de Papelera en Resultados

- [x] `GlobalSearchService` incluye resultados de papelera si `trash.view` activo
  - Aparecen con badge `[Papelera]` y descripción "En papelera"
  - `route` apunta a `/trash`

---

## Riesgos

| Nivel | Riesgo | Mitigación |
|-------|--------|------------|
| 🟡 MEDIO | LIKE lento con > 50k registros | Índices compuestos; ruta de migración a Scout/Meilisearch documentada |
| 🟡 MEDIO | Búsquedas simultáneas de muchos usuarios | Caché de respuesta por `(tenant_id, query)` con TTL 30s usando `Cache::remember` |
| 🟢 BAJO | Flash de dropdown al hacer click fuera | `onClickOutside` + transición `v-show` con fade |
| 🟢 BAJO | Resultados stale después de crear/editar | TTL corto (30s) + invalidación no necesaria para búsqueda |

---

## Dependencias

- Spec 40 (Papelera): índices `(tenant_id, deleted_at)` ya creados — reutilizables
- Policies existentes (`ClientPolicy`, `CasePolicy`, etc.): se consumen sin modificación
- `api` (axios instance): mismo patrón usado en `trashService.ts`
