---
tipo: spec
numero: 71
modulo: Workflows / Scrumboard
dominio: WFLOW/UX
estado: PART
tags: [spec, scrumboard, kanban, vista-lista, drag-and-drop, accesibilidad, ux, filtros]
relacionados:
  - "[[18_scrum_board_kanban|Spec 18]]"
  - "[[42_modal_guard_dirty_state|Spec 42]]"
  - "[[43_persistencia_filtros_listas|Spec 43]]"
---
# [[71_scrumboard_vista_lista|Spec 71]] — Scrumboard: Vista de Lista

**Dominio:** WFLOW / UX
**Estado:** PART — implementado 2026-11-24; `/code-review` aplicado; pendiente QA manual (móvil, teclado, Firefox/Safari) (DT-19)
**Tipo (CLAUDE.md › Flujo de Trabajo):** Feature mediano — **solo frontend**, sin cambios de BD ni API
**Relacionados:** 18 (diseño original del Scrum Board), 42 (modal guard), 43 (persistencia de filtros); commit `72f2637` (fix DnD con filtro activo)
**Creado:** 2026-11-24 · **Decisiones del usuario:** 2026-11-24

---

## 1. Objetivo y contexto

### Objetivo
Añadir una vista alternativa **Lista** al Scrumboard actual (vista **Tablero**/kanban) con paridad funcional completa. El usuario alterna entre ambas sin perder contexto: filtros, tarea abierta y cambios recientes se mantienen.

### Cómo funciona hoy

**Ruta:** `/apps/scrumboard` → `resources/js/src/router/index.ts:90` (sin `meta.permission`; la visibilidad se controla solo en el sidebar con `scrumboard.view`, `Sidebar.vue:983`).

**Modelo de datos** (Spec 18): un único tablero por tenant (no existe tabla `scrum_boards`).
- `scrum_columns`: `id`, `tenant_id`, `title` (≤100), `order_index` (gap strategy ×1000).
- `scrum_tasks`: `id`, `tenant_id`, `scrum_column_id`, `title`, `description`, `tags` (JSON), `category`, `due_date`, `assigned_to_id`, `case_id`, `order_index` (gap strategy), `is_completed`.

**Frontend:**

| Archivo | Rol | Acoplamiento a la vista kanban |
|---|---|---|
| `resources/js/src/views/apps/scrumboard.vue` | Wrapper de ruta (7 líneas) | — |
| `resources/js/src/views/apps/scrumboard/ScrumBoard.vue` | **Monolito** (≈310 líneas): header (título, filtro "Asignado a", botón "Agregar Columna"), los dos niveles de drag & drop (columnas y tareas), menú de columna, handlers CRUD, confirmaciones SweetAlert2, toasts e instancia de ambos modales | Alto: la lógica compartible (filtro, handlers, traducción de índice de drop) está mezclada con el template kanban |
| `.../scrumboard/components/ScrumTaskCard.vue` | Tarjeta: toggle completado, título, acciones clonar/editar/eliminar, preview de descripción (5 líneas), tags, fecha límite (rojo si vencida), asignado (avatar + primer nombre), nº de expediente | Presentacional; emite `edit`/`clone`/`delete`/`toggle` |
| `.../scrumboard/components/ScrumTaskModal.vue` | **El "popup de detalle"**: modal único de crear/editar tarea. En edición hace `GET /scrum/tasks/{id}` para la descripción completa. Usa `useModalGuard` (Spec 42) | Independiente de la vista — reutilizable tal cual |
| `.../scrumboard/components/ScrumColumnModal.vue` | Modal crear/editar columna (solo `title`) | Independiente — reutilizable tal cual |
| `resources/js/src/stores/scrum.ts` | `useScrumStore`: `columns[]` (con `tasks[]` anidadas), `assignees`, CRUD, `moveTask` optimista con snapshot/revert, `reorderColumns`, `toggleTask` optimista | Independiente de la vista |
| `resources/js/src/services/scrumService.ts` | 11 llamadas API | Independiente |
| `resources/js/src/types/scrum.ts` | `ScrumColumn`, `ScrumTask`, DTOs | Independiente |

**Comportamiento actual relevante:**
- **Filtro:** único filtro "Asignado a" (`Todos` / `@me` / cada asignado). Es un `ref` local en `ScrumBoard.vue` (`filterUserId`), se aplica **en frontend** (`getVisibleTasks()`) y **no se persiste**.
- **Abrir detalle:** solo con el icono de lápiz. El clic en la tarjeta no hace nada.
- **Drag & drop:** `vue-draggable-plus@0.6.1` (SortableJS). Columnas: arrastre horizontal por el handle `.column-drag-handle`. Tareas: toda la tarjeta es arrastrable, `group="tasks"`, enlace **unidireccional** (`:model-value`) desde `72f2637`; la tarea se identifica por `data-task-id` y el índice visible de drop se traduce a posición en la lista completa antes de `store.moveTask()`. **Sin soporte de teclado.**
- **Tareas finalizadas:** `is_completed` se marca con el botón check de la tarjeta (toggle optimista); se muestran siempre, tachadas y con `opacity-60`. No existe forma de ocultarlas.
- **Contador:** badge por columna = nº de tareas visibles tras filtro.
- **Eliminar columna:** bloqueado si tiene tareas (frontend + backend 422).
- **Estados vacíos:** solo "Sin columnas" (`scrum_no_columns`). `scrum_no_tasks` existe pero no se usa.
- **Logout:** `useScrumStore` **no** se resetea al cerrar sesión (solo `timesheetStore.clearAll()` en `auth.ts:175`).

**Backend** (no se modifica): `ScrumBoardController` (`GET /scrum/board`, `GET /scrum/assignees`), `ScrumColumnController` (store/update/reorder/destroy), `ScrumTaskController` (store/show/update/destroy/move/toggle). `PATCH /scrum/tasks/{id}/move` recibe `{scrum_column_id, position}`, donde `position` es el índice en la columna destino **excluyendo** la tarea movida.

**i18n:** 28 claves planas `scrum_*`, solo en `es.json`, `en.json`, `fr.json`.

**Tests existentes:** ninguno para Scrum. Vitest está configurado (`vitest.config.ts`, `resources/js/tests/unit/**`).

---

## 2. Requisitos funcionales

### Paridad funcional

**R1 — Drag & drop en la lista.**
- R1.1 Reordenar una tarea dentro de su sección arrastrándola verticalmente desde su handle.
- R1.2 Moverla a otra sección **expandida** soltándola en la posición deseada.
- R1.3 Moverla a una sección **colapsada** soltándola sobre su cabecera: la tarea queda **en primer lugar** de esa sección (posición 0 de la lista completa, por delante también de las tareas ocultas por el filtro). La cabecera resalta mientras una tarea pasa por encima.
- R1.4 Se persiste con el mismo `store.moveTask()` → `PATCH /scrum/tasks/{id}/move`.
- R1.5 Con filtro activo, la tarea movida es siempre la arrastrada y la posición final respeta las tareas ocultas (misma traducción de índice que el tablero tras `72f2637`).
- R1.6 Si el servidor rechaza el movimiento, la tarea vuelve a su posición original.

**R2 — Añadir tareas.** Cada sección tiene "Agregar Tarea" (botón al pie y "+" en la cabecera), que abre `ScrumTaskModal` en modo creación con el `columnId` de la sección.

**R3 — Añadir columnas.** "Agregar Columna" en el header crea una sección nueva (misma `ScrumColumnModal` y `store.createColumn`), que aparece al final.

**R4 — Abrir el detalle de una tarea** con el `ScrumTaskModal` existente, en **ambas vistas**:
- R4.1 Clic en cualquier zona de la **fila** (lista) o de la **tarjeta** (tablero) que no sea un botón ni el handle abre el modal en modo edición.
- R4.2 Se mantiene el icono de lápiz.
- R4.3 Un arrastre **no** abre el modal al soltar (se descarta el clic que algunos navegadores emiten al final del drag).
- R4.4 La fila y la tarjeta son enfocables (`tabindex="0"`, `role="button"`, `aria-label` con el título) y Enter abre el modal.
- R4.5 **Una única instancia** del modal, montada en el contenedor común.

**R5 — Filtros idénticos en ambas vistas.** Los dos filtros ("Asignado a" y "Mostrar finalizadas", R16) viven en el store; cambiarlos en una vista se refleja en la otra. Una tarea es visible si cumple **ambos**: `(filterUserId === null || task.assigned_to?.id === filterUserId) && (showCompleted || !task.is_completed)`.

**R6 — Acciones de tarea con paridad:** toggle completado (optimista), clonar, editar y eliminar (con la misma confirmación SweetAlert2) desde cada fila.

**R7 — Secciones con paridad de columnas:**
- R7.1 Menú "⋯" en la cabecera de cada sección: **Editar** (abre `ScrumColumnModal`) y **Eliminar** (misma confirmación y bloqueo si tiene tareas). Si el bloqueo se debe a tareas que no están visibles (filtro o finalizadas ocultas), el aviso lo indica (p. ej. "La columna contiene 3 tareas ocultas por los filtros"), para que una columna aparentemente vacía no parezca un error. Aplica también al tablero.
- R7.2 **Reordenar secciones** arrastrando su cabecera por un handle; persiste con el mismo `store.reorderColumns()` y se refleja en el orden de columnas del tablero.

**R8 — "Mover a…" (alternativa accesible al drag & drop), en ambas vistas.**
- R8.1 Cada tarea (fila y tarjeta) tiene un botón "Mover a…" que despliega la lista de las **demás** columnas/secciones.
- R8.2 Al elegir una, la tarea se mueve **al final** de esa columna (misma convención que al crear una tarea) vía `store.moveTask(taskId, columnId, rest.length)`.
- R8.3 Operable con teclado: botón enfocable, menú navegable con Tab/flechas, Escape cierra y devuelve el foco al botón.
- R8.4 Tras mover, toast de confirmación con el nombre de la columna destino, y el foco vuelve a un elemento visible y lógico (la siguiente tarea de la sección origen o, si no hay, la cabecera de la sección origen).
- R8.5 Si solo existe una columna, el botón se oculta.

### Requisitos propios de la vista

**R9 — Selector de vista y persistencia.**
- R9.1 Control segmentado "Tablero | Lista" en el header, mismo patrón visual que `DocumentToolbar.vue:55-74` (`icon-layout-grid` / `icon-list-check`, activo `bg-primary text-white`), con `title`/`aria-label` traducidos y `aria-pressed`.
- R9.2 La vista elegida se guarda en `localStorage` (clave por usuario) y se restaura al volver a la ruta y al recargar.
- R9.3 **Vista por defecto** (sin preferencia guardada): **Lista** en pantallas < 768 px (breakpoint `md` de Tailwind), **Tablero** en el resto. En cuanto el usuario elige una vista, su elección prevalece en cualquier tamaño de pantalla.

**R10 — Estado compartido entre vistas.**
- R10.1 Cambiar de vista no vuelve a llamar a `GET /scrum/board`.
- R10.2 El filtro activo se conserva.
- R10.3 Los modales se montan fuera de las vistas: nunca se duplican ni pierden estado al cambiar de vista.
- R10.4 Toda creación/edición/movimiento/eliminación en una vista se ve en la otra sin recargar (ambas leen `store.columns`).

**R11 — Persistencia de los filtros.** "Asignado a" y "Mostrar finalizadas" **no se reinician** al navegar fuera de la ruta y volver dentro de la SPA (viven en el store, como en Spec 43). Al recargar la página y al cerrar sesión vuelven a su valor por defecto: "Todos" y finalizadas **ocultas** (ver R14 y R16).

**R12 — Diseño de la fila de tarea.** De izquierda a derecha:

| Elemento | Origen | Notas |
|---|---|---|
| Handle de arrastre | — | Icono grip; única zona que inicia el drag |
| Toggle completado | `is_completed` | Mismo botón e icono que la tarjeta |
| Título | `title` | 1 línea truncada (`title` HTML con el texto completo); tachado + gris si completada |
| Descripción | `description_preview ?? description` | 1 línea truncada, texto secundario pequeño, bajo el título |
| Categoría | `category` | Badge neutro; se omite si está vacía |
| Tags | `tags[]` | Máximo 2 visibles + badge "+N" |
| Expediente | `case.case_number` | `#26-SP-MERC-0001`, `text-info` |
| Fecha límite | `due_date` | `YYYY-MM-DD`; rojo si vencida |
| Asignado | `assigned_to` | `UserAvatar` xs + primer nombre |
| Acciones | — | Mover a… / Clonar / Editar / Eliminar |

- R12.1 Responsive: en anchos < `md` se ocultan descripción, categoría, tags y expediente; quedan handle, toggle, título, fecha, avatar y acciones. Sin scroll horizontal de página.
- R12.2 Las filas de tareas completadas usan `opacity-60`, como la tarjeta.

**R13 — Secciones colapsables y contador.**
- R13.1 Cabecera de sección: handle de reordenar, chevron colapsar/expandir, título, badge con el **nº de tareas visibles tras filtro**, "+" (agregar tarea) y menú "⋯".
- R13.2 Al colapsar se ocultan las filas y el botón del pie; la cabecera, el contador y el "+" permanecen, y la cabecera sigue aceptando drops (R1.3).
- R13.3 Por defecto todas expandidas. El estado de colapso se guarda en `localStorage` (clave por usuario, solo frontend, sin impacto en backend). Los ids de columnas eliminadas se purgan.
- R13.4 El chevron es un `<button>` con `aria-expanded` y `aria-controls`.

**R14 — Reset al cerrar sesión.** Al hacer logout se resetea `useScrumStore` (columnas, filtro, vista en memoria), para que otro usuario en la misma pestaña no herede el filtro ni los datos. Las claves de `localStorage` son por usuario, así que no se borran.

**R15 — Estados vacíos.**
- R15.1 Sin columnas: `scrum_no_columns` en ambas vistas.
- R15.2 Sección sin tareas: "Sin tareas" (`scrum_no_tasks`) + botón "Agregar Tarea"; acepta drops.
- R15.3 Sección vacía **por los filtros** (tiene tareas, ninguna visible): "Ninguna tarea coincide con el filtro" (clave nueva); acepta drops. Si **todas** las tareas ocultas de la sección son finalizadas, el texto es "Todas las tareas están finalizadas" con un enlace "Mostrar finalizadas" que activa R16.
- R15.4 Cargando: mismo spinner actual.

**R16 — Mostrar/ocultar tareas finalizadas (ambas vistas).**
- R16.1 Control "Mostrar finalizadas" en el header, junto al filtro "Asignado a": switch/checkbox con `<label>` asociado y estado accesible (`aria-checked` o `checked` nativo).
- R16.2 **Por defecto desactivado:** las tareas con `is_completed = true` no se muestran en ninguna vista.
- R16.3 Es un filtro más: se combina con "Asignado a" (R5), comparte las reglas de persistencia de R11 y afecta a contadores, estados vacíos, drag & drop y "Mover a…" exactamente igual que el filtro de asignado.
- R16.4 **Contadores:** el badge de cada columna/sección cuenta solo las tareas visibles. Cuando hay finalizadas ocultas en la columna, se muestra a su lado un indicador discreto (p. ej. icono check + número, `title` = "3 finalizadas ocultas").
- R16.5 **Marcar como finalizada con el filtro activo:** la tarea no desaparece de golpe. Se muestra tachada durante ~1,5 s, se oculta con una transición de opacidad, y aparece un toast "Tarea finalizada" con la acción **Deshacer** (revierte con el mismo `store.toggleTask`). Si el usuario activa "Mostrar finalizadas" durante ese intervalo, la tarea permanece visible.
- R16.6 **Desmarcar** una finalizada (solo posible con "Mostrar finalizadas" activo) la deja visible en su posición; no hay cambios de orden.
- R16.7 Al clonar una tarea finalizada, la copia se crea no finalizada (comportamiento actual del backend) y por tanto es visible.
- R16.8 Drag & drop con finalizadas ocultas: se tratan como tareas no visibles; la posición final se calcula sobre la lista completa con `resolveDropPosition` (mismo mecanismo que el filtro de asignado).

---

## 3. Diseño técnico

### 3.1 Estructura de componentes

```
views/apps/scrumboard/
├── ScrumBoard.vue                (MODIFICADO → contenedor)
│     header: título, ScrumViewToggle, filtro, "Agregar Columna"
│     <ScrumKanbanView v-if="viewMode==='board'"> | <ScrumListView v-else>
│     ScrumTaskModal + ScrumColumnModal (instancia única)
│     handlers comunes: tareas (open/edit/clone/delete/toggle/move), columnas (save/delete/reorder), toasts
├── components/
│   ├── ScrumKanbanView.vue       (NUEVO — extracción 1:1 del template kanban actual)
│   ├── ScrumListView.vue         (NUEVO — draggable vertical de secciones)
│   ├── ScrumListSection.vue      (NUEVO — cabecera colapsable + drop zone + draggable de filas)
│   ├── ScrumTaskRow.vue          (NUEVO — fila, R12)
│   ├── ScrumMoveToMenu.vue       (NUEVO — "Mover a…", R8; usado por fila y tarjeta)
│   ├── ScrumViewToggle.vue       (NUEVO — control segmentado, R9)
│   ├── ScrumTaskCard.vue         (MODIFICADO — clic abre modal, foco/Enter, botón "Mover a…")
│   ├── ScrumTaskModal.vue        (sin cambios)
│   └── ScrumColumnModal.vue      (sin cambios)
utils/
└── scrumDragPosition.ts          (NUEVO — funciones puras, testeables)
stores/
├── scrum.ts                      (MODIFICADO — estado de UI, §3.4)
└── auth.ts                       (MODIFICADO — reset de useScrumStore en logout, R14)
```

**Contrato común de ambas vistas** (props/emits idénticos; el contenedor no sabe qué vista está activa):
- Props: `columns: ScrumColumn[]`, `visibleTasks: (column) => ScrumTask[]`.
- Emits: `add-task(columnId)`, `open-task(task)`, `clone-task(task)`, `delete-task(task)`, `toggle-task(task)`, `move-task({ taskId, targetColumnId, visibleIndex | 'first' | 'last' })`, `edit-column(column)`, `delete-column(column)`, `reorder-columns(columns)`.

Las vistas **no** mutan el store: emiten eventos y el contenedor centraliza confirmaciones, toasts y errores.

### 3.2 Lógica compartida (`utils/scrumDragPosition.ts`)

- `resolveDropPosition(fullTasks, taskId, visibleIndex, isVisible)`: posición en la lista completa (excluyendo la tarea movida) correspondiente al índice visible de drop. Es la lógica de `onTaskDragEnd` de `72f2637`, aislada.
- Casos especiales del payload `move-task`: `'first'` → `0` (drop sobre sección colapsada, R1.3); `'last'` → `rest.length` ("Mover a…", R8.2).
- Handler único `onMoveTask` en el contenedor: resuelve la posición y llama a `store.moveTask`.

### 3.3 Drag & drop en la lista

Misma librería (`vue-draggable-plus`), sin dependencias nuevas.

- **Secciones** (`ScrumListView`): `<draggable>` vertical con `group="columns"` y `handle=".scrum-section-handle"`, igual que el tablero. Emite `reorder-columns`. El orden de columnas no tiene filtro, así que aquí el `v-model` sobre la copia es seguro (mismo patrón que el kanban actual).
- **Filas** (`ScrumListSection`): `<draggable>` con:
  - `:model-value="visibleTasks(column)"` — **enlace unidireccional obligatorio** (nunca `v-model`), para no reintroducir el bug corregido en `72f2637`.
  - `group="tasks"`, `handle=".scrum-row-handle"`, `:data-column-id`; `:data-task-id` en cada fila.
  - `min-h` cuando no hay filas visibles, para aceptar drops (R15.2/R15.3).
- **Drop sobre sección colapsada** (R1.3): cuando la sección está colapsada, su cabecera contiene un `<draggable>` adicional, vacío (`:model-value="[]"`), con `group="tasks"` (solo `put`), que ocupa el área de la cabecera. Su evento `add` emite `move-task({..., visibleIndex: 'first'})`. Resaltado visual mientras se arrastra encima. *Riesgo técnico:* se valida en el paso 9 del plan con un spike; si SortableJS no lo resuelve de forma fiable, la alternativa es expandir automáticamente la sección tras ~600 ms de hover.
- **Clic vs. arrastre** (R4.3): flag `justDragged` que se activa en `onStart` y se limpia en un `setTimeout(0)` tras `onEnd`; el handler de clic lo ignora mientras está activo. En el tablero (donde toda la tarjeta es arrastrable) aplica lo mismo.
- **Sin teclado en el DnD** (SortableJS no lo soporta): la alternativa accesible es "Mover a…" (R8).

### 3.4 Cambios en el store (`stores/scrum.ts`)

Estado de UI nuevo (no se envía al backend):

```ts
viewMode: 'board' | 'list'          // R9
filterUserId: number | null          // movido desde ScrumBoard.vue (R5/R11)
showCompleted: boolean               // R16 — default false
collapsedColumnIds: number[]         // R13
pendingHideTaskIds: number[]         // R16.5 — finalizadas en su ~1,5 s de gracia antes de ocultarse
```

Getters/acciones nuevas:
- `isTaskVisible(task)`: predicado único de R5 (asignado + finalizadas, respetando `pendingHideTaskIds`). Es el que reciben `visibleTasks` y `resolveDropPosition`, para que la vista y el cálculo de posición nunca diverjan.
- `visibleTasks(columnId)`: `column.tasks.filter(isTaskVisible)`.
- `hiddenCompletedCount(columnId)`: para el indicador de R16.4.
- `setShowCompleted(value)`.
- `initUiPreferences(userId)`: lee `localStorage` (`scrum.viewMode.{userId}`, `scrum.collapsed.{userId}`) con `try/catch`; valida los valores; si no hay vista guardada aplica el default responsive (R9.3).
- `setViewMode(mode)`, `toggleColumnCollapsed(columnId)`: actualizan y persisten (con `try/catch`; si falla, siguen funcionando en memoria).
- `deleteColumn`: además purga el id de `collapsedColumnIds`.
- `reset()`: vuelve al estado inicial; lo llama `auth.ts` en logout (R14).

`moveTask`, `createTask`, `createColumn`, `reorderColumns`, etc. **no cambian**.

### 3.5 API / backend

**Ninguno.** Todas las operaciones existen y se usan con los mismos payloads. La persistencia de vista y colapso es exclusivamente `localStorage` (decisiones P3/P4).

### 3.6 i18n

Claves nuevas planas, prefijo `scrum_*`, **solo en `es`, `en`, `fr`** (P12):
`scrum_view_board`, `scrum_view_list`, `scrum_view_toggle_label`, `scrum_section_collapse`, `scrum_section_expand`, `scrum_section_reorder`, `scrum_no_tasks_filtered`, `scrum_drag_handle`, `scrum_more_tags` (`{n}`), `scrum_move_to`, `scrum_moved_to` (`{column}`), `scrum_open_task` (`{title}`), `scrum_drop_first_hint`, `scrum_show_completed`, `scrum_hidden_completed` (`{n}`), `scrum_all_tasks_completed`, `scrum_task_completed_toast`, `scrum_undo`, `scrum_column_has_hidden_tasks` (`{n}`). Sin `<`/`>` en los valores (G10).

---

## 4. Fuera de alcance

- Cambios de backend, BD o API.
- Nuevos filtros (búsqueda, etiquetas, fecha, expediente…).
- Drag & drop con teclado (lo cubre "Mover a…", R8).
- Virtualización de listas largas (el diseño original asume < 200 tareas por tenant).
- Ordenar la lista por otro campo que no sea `order_index`.
- Edición inline en la fila, selección múltiple y acciones masivas.
- Los bugs de §7.2 (registrados como DT-17 y DT-18).

---

## 5. Criterios de aceptación

Cada criterio se verifica en **ambas vistas** cuando aplica.

**Selector, estado compartido y persistencia**
- [ ] CA1 El header muestra "Tablero | Lista"; el botón activo está resaltado y tiene `aria-pressed="true"`.
- [ ] CA2 Cambiar de vista no dispara `GET /api/scrum/board` (Network).
- [ ] CA3 Filtro `@me` en Tablero → cambiar a Lista → sigue en `@me`; y viceversa.
- [ ] CA4 Elegir Lista y recargar (F5) → abre en Lista. Elegir Tablero, ir a otra ruta y volver → abre en Tablero.
- [ ] CA5 Primera visita (sin preferencia) a 375 px → Lista; a 1280 px → Tablero. Tras elegir Tablero en móvil, recargar en móvil → Tablero.
- [ ] CA6 Con `localStorage` bloqueado, la página carga (vista por defecto) sin errores en consola, y el selector funciona en memoria.
- [ ] CA7 Filtro `@me` → ir a Expedientes → volver → el filtro sigue en `@me`. Recargar → "Todos".
- [ ] CA8 Usuario A con filtro `@me` cierra sesión; usuario B inicia sesión en la misma pestaña → filtro "Todos", sin datos de A en pantalla; las preferencias de vista de A y B son independientes.
- [ ] CA9 Crear una tarea en Lista → cambiar a Tablero → aparece en la columna y posición correctas, sin recargar.

**Contenido de la lista**
- [ ] CA10 Una sección por columna, en el mismo orden que el tablero y con el mismo contador.
- [ ] CA11 La fila muestra los campos de R12; vencida → fecha en rojo; completada → tachada y con opacidad; sin categoría → no aparece el badge.
- [ ] CA12 Una tarea con 5 tags muestra 2 + "+3".
- [ ] CA13 A 375 px no hay scroll horizontal de página y las acciones de la fila siguen accesibles.

**Detalle y acciones**
- [ ] CA14 Clic en la fila (fuera de botones/handle) y clic en la tarjeta (fuera de botones) abren el **mismo** `ScrumTaskModal` con la descripción completa; con cambios sin guardar, cerrar pide confirmación (Spec 42).
- [ ] CA15 Tab hasta una fila/tarjeta + Enter abre el modal.
- [ ] CA16 Terminar un arrastre (fila o tarjeta) **no** abre el modal.
- [ ] CA17 Toggle, clonar (la copia aparece debajo de la original) y eliminar (con confirmación) funcionan igual que en el tablero.
- [ ] CA18 "Agregar Tarea" en la sección X crea la tarea al final de X; "Agregar Columna" crea una sección al final, visible también en el Tablero.
- [ ] CA19 Editar el título de una sección desde "⋯" se refleja en el Tablero; eliminar una sección con tareas muestra el aviso y no la elimina.

**Drag & drop**
- [ ] CA20 Reordenar dentro de una sección: tras soltar y recargar, el orden se conserva.
- [ ] CA21 Mover a otra sección expandida: tras recargar, está en la sección y posición correctas.
- [ ] CA22 **Filtro activo** (`@me`, con tareas de otros usuarios intercaladas): se mueve exactamente la tarea arrastrada, a la posición esperada respecto de las visibles; con "Todos", las ocultas conservan su orden relativo.
- [ ] CA23 **Sección colapsada:** soltar sobre su cabecera → la tarea queda **primera** de esa sección (verificable expandiendo, en "Todos" y tras recargar); el contador se actualiza; la cabecera se resalta durante el hover.
- [ ] CA24 **Mover la última tarea** de una sección: muestra R15.2 con contador 0 y vuelve a aceptar drops.
- [ ] CA25 **Sección vacía:** acepta un drop; la tarea queda como única.
- [ ] CA26 **Sección vaciada por el filtro:** muestra R15.3 y acepta un drop; con "Todos", la tarea movida queda al final (tras las ocultas).
- [ ] CA27 Error forzado del servidor al mover (p. ej. 422) → la tarea vuelve a su posición original.
- [ ] CA28 Clic en el título o en los botones de la fila **no** inicia un arrastre; solo el handle.
- [ ] CA29 Reordenar secciones en la Lista → el Tablero muestra el nuevo orden de columnas; persiste tras recargar.

**"Mover a…"**
- [ ] CA30 El menú lista todas las columnas excepto la actual; elegir una mueve la tarea al **final** de esa columna, persiste tras recargar y muestra el toast.
- [ ] CA31 Solo con teclado: Tab hasta "Mover a…" → Enter → flechas → Enter mueve; Escape cierra y devuelve el foco al botón; tras mover, el foco queda en un elemento visible (R8.4).
- [ ] CA32 Con una sola columna, el botón no aparece.

**Secciones colapsables**
- [ ] CA33 Colapsar oculta filas y botón del pie; contador y "+" visibles; el contador se actualiza al cambiar el filtro.
- [ ] CA34 El estado de colapso persiste tras recargar; al eliminar una columna colapsada no quedan restos en `localStorage`.
- [ ] CA35 El chevron funciona con Tab + Enter/Espacio y expone `aria-expanded`.

**Tareas finalizadas (R16)**
- [ ] CA39 Al entrar por primera vez (y tras recargar), "Mostrar finalizadas" está desactivado y ninguna tarea finalizada aparece en Tablero ni en Lista.
- [ ] CA40 Activarlo en una vista → cambiar de vista → sigue activo y las finalizadas se ven (tachadas, `opacity-60`). Navegar a otra ruta y volver → sigue activo. Recargar → vuelve a desactivado.
- [ ] CA41 Combinado con `@me`: solo se ven las tareas de `@me`, y las finalizadas de `@me` solo si el switch está activo.
- [ ] CA42 Con el switch desactivado, marcar una tarea como finalizada: sigue visible tachada ~1,5 s, se desvanece y aparece el toast con "Deshacer"; "Deshacer" la devuelve, no finalizada, a su posición original.
- [ ] CA43 Contador e indicador: una columna con 4 tareas, 3 de ellas finalizadas → badge "1" + indicador "3 finalizadas ocultas"; con el switch activo → badge "4", sin indicador.
- [ ] CA44 Columna cuyas tareas están todas finalizadas: muestra "Todas las tareas están finalizadas"; su enlace activa el switch.
- [ ] CA45 Intentar eliminar una columna aparentemente vacía (solo finalizadas ocultas) → no se elimina y el aviso menciona las tareas ocultas.
- [ ] CA46 **DnD con finalizadas ocultas intercaladas:** reordenar y mover entre columnas coloca la tarea en la posición esperada respecto de las visibles; al activar el switch, las finalizadas conservan su orden relativo; tras recargar, el orden persiste.

**Escala y regresión**
- [ ] CA36 **Lista larga:** con 200 tareas en 4 secciones, el cambio de vista y el arrastre no presentan bloqueos perceptibles (> 200 ms) en un portátil estándar; arrastrar cerca del borde hace auto-scroll.
- [ ] CA37 **Regresión kanban:** reordenar columnas por handle, reordenar/mover tareas con y sin filtro, y CRUD de columnas y tareas se comportan como antes (más R4 y R8, que son nuevos).
- [ ] CA38 `npm run build` sin errores; tests Vitest nuevos en verde; claves i18n presentes en es/en/fr.

---

## 6. Plan de implementación

Cada paso deja la app funcionando y es verificable por separado.

| # | Paso | Verificación |
|---|---|---|
| 0 | **Prerrequisito:** desplegar `72f2637` y confirmar en producción el DnD con filtro en el tablero | Prueba manual en producción |
| 1 | `utils/scrumDragPosition.ts` (`resolveDropPosition` + casos `'first'`/`'last'`); `onTaskDragEnd` del kanban la usa (sin cambio funcional) | Tests Vitest: sin filtro, con filtro, drop al final, sección vacía, misma columna, `'first'`, `'last'` + regresión manual kanban |
| 2 | Tests Vitest del store actual: `moveTask` (optimista + revert) | `npx vitest run` |
| 3 | Estado de UI en el store (`filterUserId`, `isTaskVisible`, `visibleTasks`, `reset()`) + reset en logout (`auth.ts`); el kanban consume el filtro del store | CA7, CA8 + regresión kanban |
| 3b | "Mostrar finalizadas" en el tablero: `showCompleted`, `hiddenCompletedCount`, switch en header, indicador en columnas, gracia de ~1,5 s + toast "Deshacer", aviso de borrado con tareas ocultas | CA39–CA46 en Tablero (las de Lista se re-verifican en los pasos 8 y 10) |
| 4 | Extraer `ScrumKanbanView.vue` con el contrato de §3.1 (refactor puro) | Regresión manual completa del kanban (CA37) |
| 5 | Tarjeta: clic abre modal, foco/Enter, guard `justDragged` | CA14–CA16 en Tablero |
| 6 | `ScrumMoveToMenu` en la tarjeta | CA30–CA32 en Tablero |
| 7 | `viewMode` + `initUiPreferences` + persistencia + default responsive + `ScrumViewToggle`; `ScrumListView` provisional | CA1, CA2, CA4–CA6 |
| 8 | `ScrumTaskRow` + `ScrumListSection` sin drag: filas, contador, estados vacíos, agregar tarea, acciones (incl. "Mover a…"), modal, menú de sección | CA9–CA19, CA30–CA32 en Lista |
| 9 | Secciones colapsables + persistencia + **spike del drop sobre cabecera colapsada** | CA33–CA35 (+ decisión del spike) |
| 10 | Drag & drop de filas (incluido drop sobre colapsada) | CA20–CA28, CA36 |
| 11 | Reordenar secciones | CA29 |
| 12 | i18n final, responsive, `npm run build`, `/code-review`, QA cruzado de ambas vistas; actualizar `MEMORY.md` e `INDEX.md` | CA13, CA37, CA38 |

**Tests (Definición de Terminado):** no hay endpoints nuevos ni modificados, así que no aplican tests Feature PHP nuevos. Se exigen tests unitarios Vitest para `utils/scrumDragPosition.ts` y para la lógica nueva del store (`isTaskVisible` con todas las combinaciones de filtros, `hiddenCompletedCount`, gracia de `pendingHideTaskIds`, `visibleTasks`, `initUiPreferences` con storage válido/inválido/no disponible, default responsive, `toggleColumnCollapsed`, purga al borrar columna, `reset`).

---

## 7. Riesgos y regresiones

### 7.1 Sobre la vista kanban actual

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| La extracción de `ScrumKanbanView` rompe el drag de columnas (draggable anidado con `handle`) o de tareas | Media | Paso 4 como refactor puro; regresión completa antes de seguir |
| Reintroducir el bug de índices con filtro (usar `v-model` en filas) | Media | Enlace unidireccional obligatorio + `resolveDropPosition` compartida y testeada |
| **Clic en la tarjeta abre el modal al terminar un arrastre** (toda la tarjeta es arrastrable en el tablero) | Media | Guard `justDragged` (R4.3), CA16 en ambas vistas |
| El botón "Mover a…" y el clic en la tarjeta compiten con los 3 botones existentes en tarjetas estrechas (`w-80`) | Baja | `@click.stop` en todos los botones; revisión visual |
| Drop sobre cabecera colapsada poco fiable con SortableJS | Media | Spike en el paso 9 con alternativa definida (auto-expandir en hover) |
| `v-if` entre vistas desmonta la inactiva → se pierde el scroll horizontal del tablero al volver | Baja | Aceptable; evaluar `<KeepAlive>` si molesta |
| `localStorage` lanza excepción (modo privado/bloqueado) | Baja | `try/catch` + funcionamiento en memoria (CA6) |
| El filtro persistente oculta tareas a un usuario que no recuerda haberlo puesto | Baja | El selector muestra siempre el filtro activo; se reinicia al recargar |
| **Cambio de comportamiento visible en el tablero:** al desplegar, las finalizadas dejan de verse por defecto; los usuarios pueden creer que se perdieron tareas | Alta | Indicador "N finalizadas ocultas" por columna (R16.4), estado vacío con enlace (R15.3), aviso en el borrado (R7.1); comunicar el cambio a los usuarios al desplegar |
| Marcar una tarea como finalizada por error la hace desaparecer | Media | Gracia de ~1,5 s + toast con "Deshacer" (R16.5) |
| El predicado de visibilidad diverge entre la vista y el cálculo de posición (tareas que se mueven al lugar equivocado) | Media | Predicado único `isTaskVisible` en el store, usado por ambas; tests de combinaciones |

### 7.2 Hallazgos fuera de alcance (registrados como deuda técnica)

1. **DT-17 (ALTA):** la API de scrum no tiene autorización. Ningún controller llama a `authorize()`, no hay Policy ni middleware `permission:`, todos los Form Requests de `app/Http/Requests/Scrum/` devuelven `authorize(): true` y la ruta del frontend no tiene `meta.permission`. Cualquier usuario autenticado del tenant (incluido el rol `cliente`) puede leer y modificar el tablero llamando a la API directamente. El aislamiento entre tenants sí está cubierto por `BelongsToTenant`.
2. **DT-18 (BAJA):** `ScrumTaskListResource` no devuelve `assigned_to.avatar_url`, aunque el tipo TS y `UserAvatar` lo esperan; el tablero muestra siempre iniciales.
3. El módulo Scrum no tiene tests (los de esta spec cubren solo la lógica nueva de frontend).

---

## 8. Decisiones (preguntas resueltas el 2026-11-24)

| # | Pregunta | Decisión |
|---|---|---|
| P1 | Ubicación de la spec | Mantener la convención: `spec/71_scrumboard_vista_lista.md` + entrada en `spec/INDEX.md` |
| P2 | ¿Clic abre el detalle? | Sí, en la fila **y** en la tarjeta del tablero (R4) |
| P3 | Persistencia de la vista | `localStorage` (R9.2) |
| P4 | Persistir colapso de secciones | Sí, solo frontend con `localStorage`, sin backend (R13.3) |
| P5 | Drop sobre sección colapsada | Sí; la tarea queda en **primer lugar** (R1.3) |
| P6 | Campos de la fila | Sí, incluyendo descripción y categoría (R12) |
| P7 | Reordenar secciones en la lista | Sí (R7.2) |
| P8 | Editar/eliminar sección en la lista | Sí (R7.1) |
| P9 | Lista por defecto en móvil | Sí, para la primera visita sin preferencia guardada (R9.3) |
| P10 | ¿El filtro se reinicia al salir? | No se reinicia (R11) |
| P11 | Hallazgos fuera de alcance | Registrados aparte como DT-17 y DT-18 en `MEMORY.md` |
| P12 | Idiomas | Solo es/en/fr (§3.6) |
| P13 | Accesibilidad | Añadir "Mover a…" en ambas vistas (R8) |
| — | Nueva funcionalidad (2026-11-24) | Mostrar/ocultar tareas finalizadas, **ocultas por defecto**, en ambas vistas (R16) |

**Decisiones derivadas tomadas al redactar** (revisar si no encajan):
- "Mover a…" coloca la tarea **al final** de la columna destino (igual que crear una tarea).
- El filtro persiste en la navegación dentro de la SPA, pero **se reinicia al recargar y al cerrar sesión** (mismo alcance que Spec 43; el reset en logout evita que otro usuario herede el filtro).
- La preferencia de vista elegida explícitamente prevalece sobre el default responsive.
- "Mostrar finalizadas" es un filtro con el mismo alcance que "Asignado a": sobrevive a la navegación en la SPA y vuelve a "ocultas" al recargar (no se guarda en `localStorage`).
- Al marcar una tarea como finalizada con el filtro activo, se oculta tras ~1,5 s con toast "Deshacer" (R16.5) en lugar de desaparecer al instante.
