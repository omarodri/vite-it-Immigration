---
tipo: spec
numero: 21
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[47_tablero_interactivo_tareas_expediente|Spec 47]]"
---
# [[21_tareas_tab_expediente|Spec 21]]: Tab "Tareas" en Vista de Expediente (show.vue)

**Fecha:** 2026-03-15
**Estado:** ✅ COMPLETADO (2026-03-15)
**Epic:** Integración de Tareas en Expediente
**Autor:** Architect Agent

---

## 1. Resumen Ejecutivo

Integrar un tab "Tareas" en `resources/js/src/views/cases/show.vue` que muestre y permita gestionar las tareas (todos) asociadas a un expediente específico, reutilizando la infraestructura existente (`useTodoStore`, `TodoController`, tipos).

---

## 2. Contexto y Motivación

- El módulo de tareas (`todolist.vue`) ya existe y funciona con filtro `case_id`
- El backend `TodoController::index()` ya soporta `?case_id=X`
- `show.vue` ya sigue el patrón de tabs extensibles (info, lifecycle, timeline, invoices, documents)
- `InvoiceTable.vue` es el patrón de referencia para componentes de tab

---

## 3. Análisis de Impacto

### 3.1 Backend — Sin cambios requeridos
- `GET /api/todos?case_id=X` ya funciona ✅
- `POST /api/todos` ya acepta `case_id` ✅
- `StoreTodoRequest` ya valida `case_id` ✅

### 3.2 Frontend — Cambios necesarios

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `types/todo.ts` | MODIFICAR | Actualizar union type de `tag` |
| `views/cases/components/CaseTodoTab.vue` | CREAR | Nuevo componente de tab |
| `views/cases/show.vue` | MODIFICAR | Añadir tab + contenido |
| `locales/en.json` | MODIFICAR | Clave `cases.tab_todos` |
| `locales/es.json` | MODIFICAR | Clave `cases.tab_todos` |
| `locales/fr.json` | MODIFICAR | Clave `cases.tab_todos` |

---

## 4. Diseño del Componente `CaseTodoTab.vue`

### Props
```typescript
interface Props {
    caseId: number;
    caseNumber: string;
}
```

### Diferencias vs `todolist.vue`
- **Sin sidebar** — filtros de status como barra horizontal de chips/botones
- **case_id bloqueado** — pre-filled en modal, no editable por el usuario
- **Sin filtro de casos** — la vista ya está contextualizada al expediente
- **Con filtro de asignado** — select dropdown
- **Con filtro de tag** — select dropdown
- **Mismo store** — `useTodoStore` singleton, safe porque SPA no renderiza ambas vistas simultáneamente

### Estado local
```typescript
const selectedStatus = ref<string>('');   // '' = inbox
const selectedTag = ref<string>('');
const searchTask = ref<string>('');
const filterAssigneeId = ref<number | null>(null);
const addTaskModal = ref(false);
const viewTaskModal = ref(false);
const isSaving = ref(false);
```

### Estructura visual
```
┌─────────────────────────────────────────────────────────────┐
│ [+ Nueva Tarea]  [Buscar...]  [Asignado: ▾]  [Tag: ▾]      │
│ ────────────────────────────────────────────────────────── │
│ [Bandeja] [Hecho] [Importante] [Papelera]    1-10 of 25 ◀▶ │
│ ────────────────────────────────────────────────────────── │
│ □ ⭐ Título tarea          [tag] [prioridad]  fecha  asig ⋮ │
│ □    Título tarea 2        ...                              │
└─────────────────────────────────────────────────────────────┘
```

### Modal Add/Edit
- Campo `case_id` pre-llenado con `caseId` prop, **oculto** (no se muestra al usuario)
- El resto igual que en `todolist.vue`

---

## 5. Patrón de Integración en show.vue

```typescript
// Tabs array
const tabs = computed(() => [
    { id: 'info',       label: 'cases.tab_information' },
    { id: 'lifecycle',  label: 'cases.tab_lifecycle' },
    { id: 'timeline',   label: 'cases.tab_timeline' },
    { id: 'invoices',   label: 'cases.tab_invoices' },
    { id: 'todos',      label: 'cases.tab_todos' },      // ← NUEVO
    { id: 'documents',  label: 'cases.tab_documents' },
]);
```

```html
<!-- Todos Tab -->
<div v-else-if="activeTab === 'todos'">
    <CaseTodoTab
        :case-id="currentCase.id"
        :case-number="currentCase.case_number"
    />
</div>
```

---

## 6. i18n Keys

| Key | EN | ES | FR |
|-----|----|----|-----|
| `cases.tab_todos` | Tasks | Tareas | Tâches |

---

## 7. Checklist de Implementación

- [x] 1. Guardar este spec en `spec/21_tareas_tab_expediente.md`
- [x] 2. Validar prerequisitos backend (`TodoController` soporta `case_id`)
- [x] 3. Actualizar `types/todo.ts` — tag union type correcto
- [x] 4. Crear `views/cases/components/CaseTodoTab.vue`
- [x] 5. Actualizar `views/cases/show.vue` — añadir tab + contenido
- [x] 6. Añadir `cases.tab_todos` en en/es/fr.json
- [x] 7. Ejecutar `npm run build` para verificar TypeScript

---

## 8. Notas Técnicas

- **z-index modales:** `z-[51]` igual que en `todolist.vue`
- **Quill editor:** importar `vue3-quill` y CSS en el componente
- **Store compartido:** `useTodoStore` es singleton Pinia; al montar el tab se llama `fetchTodos({ case_id: caseId })` que sobreescribe el estado. No hay colisión porque la SPA nunca muestra `todolist.vue` y `CaseTodoTab.vue` simultáneamente.
- **Paginación:** el store mantiene `currentPage`, hay que resetear a 1 en cada cambio de filtro
