---
numero: 67
titulo: "RBAC Granular — Fase 2: botones v-can en builder + etiquetas i18n en panel de roles"
estado: DONE
dominio: AUTH/UX
relacionados: [66, 06, 08, 65]
fecha: 2026-05-27
---

## Contexto

Spec 66 creó los permisos granulares `workflow_tasks.*` y `case_code.*` y los conectó al sidebar y al router.
Esta fase 2 aplica esos permisos en las vistas concretas del administrador:

1. El builder de workflows usa `workflows.*` en todos los botones — incluyendo los de plantillas de tareas —
   pero debería usar `workflow_tasks.*` para las operaciones sobre task templates.
2. Los paneles de roles (`show.vue`, `edit.vue`) muestran nombres de grupos de permisos sin traducir
   (e.g., "Workflow_tasks") en lugar de etiquetas legibles ("Tareas del Workflow").

---

## Hallazgos

| Archivo | Problema | Corrección |
|---------|----------|------------|
| `admin/workflows/builder.vue` | Botones de task templates usan `workflows.*` en vez de `workflow_tasks.*` | Cambiar las 3 instancias del panel de templates |
| `admin/roles/show.vue` | `groupedPermissions.display_name` hace capitalize del key raw | Usar `t('permissions.modules.{group}')` con fallback |
| `admin/roles/show.vue` | `formatPermissionName` muestra solo el sufijo de acción | Usar `t('permissions.labels.{group}.{action}')` con fallback |
| `admin/roles/edit.vue` | Mismo problema en `roleStore.permissionGroups` y `formatPermissionName` | Computed `translatedGroups` + mismo fix de labels |
| `UpdateCaseCodePatternRequest.php` | Comentario menciona `settings.case_code.manage` (deprecado) | Actualizar comment a `case_code.update` |

---

## Decisiones

| ID | Decisión |
|----|----------|
| D-01 | Los botones de **etapas** (Stage) en builder.vue conservan `workflows.*` — gestionan la estructura de la plantilla, no el catálogo de tareas. |
| D-02 | El drag-handle de templates también queda bajo `v-can="'workflow_tasks.update'"` — reordenar es una mutación de la lista. |
| D-03 | Las vistas hacen la traducción con `t()` usando fallback al key capitalizado. El store sigue retornando el key raw para no mezclar i18n con estado. |
| D-04 | `settings.case_code.manage` fue migrado en `2026_06_01_000002_add_case_code_pattern_permissions.php`; la ruta ya usa `permission:case_code.update`. Solo queda actualizar el comentario stale en el Request. |

---

## Archivos Impactados

- `resources/js/src/views/admin/workflows/builder.vue`
- `resources/js/src/views/admin/roles/show.vue`
- `resources/js/src/views/admin/roles/edit.vue`
- `app/Http/Requests/Tenant/UpdateCaseCodePatternRequest.php`

---

## Plan de Implementación

### Fase 1 — builder.vue: permisos correctos en task templates

- [x] F1.1 — Botón "Add task template": cambiar `v-can="'workflows.create'"` → `v-can="'workflow_tasks.create'"`
- [x] F1.2 — Drag handle de templates: añadir `v-can="'workflow_tasks.update'"` (ocultar si no puede actualizar)
- [x] F1.3 — Botón "Edit template": cambiar `v-can="'workflows.update'"` → `v-can="'workflow_tasks.update'"`
- [x] F1.4 — Botón "Delete template": cambiar `v-can="'workflows.delete'"` → `v-can="'workflow_tasks.delete'"`

### Fase 2 — roles/show.vue: etiquetas i18n en permisos

- [x] F2.1 — Importar `useI18n` y añadir `const { t }`
- [x] F2.2 — Actualizar `groupedPermissions`: `display_name` usa `t('permissions.modules.{name}')` con fallback
- [x] F2.3 — Actualizar `formatPermissionName`: usa `t('permissions.labels.{group}.{action}')` con fallback

### Fase 3 — roles/edit.vue: etiquetas i18n en permisos

- [x] F3.1 — Importar `useI18n` y añadir `const { t }`
- [x] F3.2 — Añadir computed `translatedGroups` que envuelve `roleStore.permissionGroups`
- [x] F3.3 — Reemplazar `roleStore.permissionGroups` con `translatedGroups` en template
- [x] F3.4 — Actualizar `formatPermissionName`: usa `t('permissions.labels.{group}.{action}')` con fallback

### Fase 4 — Limpieza de deprecaciones

- [x] F4.1 — Actualizar comentario stale en `UpdateCaseCodePatternRequest.php`

---

## Tabla de Riesgos

| Riesgo | Probabilidad | Mitigación |
|--------|-------------|------------|
| Grupos sin clave i18n muestran fallback raw | BAJA | Fallback a capitalize del key garantiza UX mínima |
| Romper `workflows.create` en etapas | MEDIA | Solo cambiar los 3 botones del panel de templates |
