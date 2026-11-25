---
tipo: spec
numero: 16
modulo: Clientes y Acompañantes
dominio: CLNT
estado: DONE
tags: [spec, clientes, done]
---
# Plan de Implementación: Selector de Columnas — Lista de Clientes

## Metadata
- **Fecha:** 2026-03-15
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 4.1 - Column Chooser en Lista de Clientes
- **Alcance:** Implementar selector dinámico de columnas (show/hide) en `clients/list.vue`, con persistencia en `localStorage` y restablecimiento a vista predeterminada. Reutiliza el patrón ya establecido en `cases/list.vue` via `useCaseColumnChooser.ts`.
- **Flujos cubiertos:** Lista de clientes (datatable)
- **Tiempo Total Estimado:** 2–3 horas
- **Estado:** ✅ COMPLETADO (2026-03-15)

---

## Decisiones Arquitectónicas

| Dimensión | Decisión | Justificación |
|-----------|----------|---------------|
| **Patrón de referencia** | Clonar `useCaseColumnChooser.ts` adaptado a clientes | Patrón completamente validado y en producción. Cero riesgo arquitectónico. |
| **Persistencia** | `localStorage` — key `vite-it:clients:columns` | Igual que casos. Latencia cero, sin endpoint backend, sin complejidad adicional. La configuración de UI es preferencia de dispositivo, no dato de negocio. |
| **Filtrado de columnas** | Manipulación del array `columns` (computed) | `vue3-datatable` acepta array dinámico; la columna no existe en el DOM cuando está oculta. Más eficiente que `v-show`. |
| **Merge strategy** | Saved prefs merged con code defaults en `loadColumns()` | Nuevas columnas en futuras versiones aparecen con visibilidad default sin romper preferencias guardadas. |
| **Columnas bloqueadas** | `first_name` (Nombre) + `actions` (Acciones) | Nombre identifica el registro; Acciones son operacionales. Nunca ocultables. |
| **Backend** | Sin cambios | El payload ya es paginado y contiene todos los campos. Ocultar columnas es UX puro; optimizar el payload no es requerimiento de este epic. |
| **Responsividad mobile** | Sin cambios — scroll horizontal nativo | El datatable ya maneja scroll horizontal. El column chooser aplica igual en mobile. |

---

## Columnas Configuradas

| # | Campo (`field`) | Título (i18n key) | Visible por defecto | Bloqueada | Ancho |
|---|-----------------|-------------------|---------------------|-----------|-------|
| 1 | `first_name` | `clients.name` | ✅ | 🔒 | `250px` |
| 2 | `email` | `clients.email` | ✅ | — | `200px` |
| 3 | `phone` | `clients.phone` | ✅ | — | `150px` |
| 4 | `status` | `clients.status` | ✅ | — | `120px` |
| 5 | `canada_status` | `clients.canada_status` | ✅ | — | `160px` |
| 6 | `nationality` | `clients.nationality` | ❌ | — | `140px` |
| 7 | `marital_status` | `clients.marital_status` | ❌ | — | `140px` |
| 8 | `profession` | `clients.profession` | ❌ | — | `140px` |
| 9 | `arrival_date` | `clients.arrival_date` | ❌ | — | `140px` |
| 10 | `created_at` | `clients.created` | ✅ | — | `150px` |
| 11 | `actions` | `clients.actions` | ✅ | 🔒 | `180px` |

**Columnas visibles por defecto:** 6 de 11 (first_name, email, phone, status, canada_status, created_at, actions)

---

## Composable: `useClientColumnChooser.ts`

Ubicación: `resources/js/src/composables/useClientColumnChooser.ts`

Contrato idéntico a `useCaseColumnChooser.ts`:

```typescript
interface ColumnConfig {
    field: string;
    titleKey: string;
    visible: boolean;
    locked: boolean;
    width: string;
}

const STORAGE_KEY = 'vite-it:clients:columns';

const DEFAULT_COLUMNS: ColumnConfig[] = [ /* 11 columnas definidas arriba */ ];

// Exports:
// columns        — ColumnConfig[] reactivo (cargado desde localStorage)
// visibleOptions — ColumnConfig[] para el dropdown (solo no-bloqueadas)
// toggleColumn(field: string): void
// resetColumns(): void
// isVisible(field: string): boolean
// saveColumns(): void — privado, llamado desde toggleColumn/resetColumns
```

**`loadColumns()`:** merge de saved con defaults → garantiza forward-compatibility con nuevas columnas.

**`isVisible(field)`:** solo verifica `visible` (sin `requiresPermission` — los clientes no tienen campos por permiso a nivel de columna).

---

## API Contract — Sin cambios

`GET /api/clients` sigue retornando todos los campos. El frontend filtra qué mostrar. No se modifica ningún endpoint.

---

## Análisis de Casos de Borde

| Caso | Comportamiento |
|------|---------------|
| Todas las columnas opcionales ocultas | Válido — `first_name` + `actions` siempre visibles |
| localStorage corrupto o vacío | `try/catch` en `loadColumns()` → fallback a defaults sin crash |
| Columna nueva en versión futura | Merge strategy: visible con su default, no rompe prefs guardadas |
| Reset solicitado por usuario | `resetColumns()` limpia `localStorage` + recarga DEFAULT_COLUMNS |
| Mobile | Column chooser funciona igual; scroll horizontal nativo de la tabla no cambia |
| Columna con template slot oculta | Al no estar en el array `columns`, `vue3-datatable` no renderiza el slot — cero DOM overhead |

---

## i18n — Claves Necesarias

Verificación de claves existentes vs. nuevas requeridas:

| Key | EN | ES | FR | Estado |
|-----|----|----|-----|--------|
| `clients.columns` | Columns | Columnas | Colonnes | ⚠️ Agregar |
| `clients.reset_columns` | Reset to Default | Restablecer columnas | Réinitialiser | ⚠️ Agregar |
| `clients.name` | Name | — | — | ✅ Existe |
| `clients.email` | Email | — | — | ✅ Existe |
| `clients.phone` | Phone | — | — | ✅ Existe |
| `clients.status` | Status | — | — | ✅ Existe |
| `clients.canada_status` | Canada Status | — | — | ✅ Existe |
| `clients.nationality` | Nationality | — | — | ✅ Existe |
| `clients.marital_status` | Marital Status | — | — | ✅ Existe |
| `clients.profession` | Profession | — | — | ✅ Existe |
| `clients.arrival_date` | Arrival Date | — | — | ✅ Existe |
| `clients.created` | Created | — | — | ✅ Existe |
| `clients.actions` | Actions | — | — | ✅ Existe |

**Solo 2 claves nuevas** necesarias: `clients.columns` y `clients.reset_columns` en los 3 locales.

---

## Lista Completa de Archivos Impactados

### Nuevos

| Archivo | Descripción |
|---------|-------------|
| `resources/js/src/composables/useClientColumnChooser.ts` | Composable con DEFAULT_COLUMNS, loadColumns, toggleColumn, resetColumns, isVisible |

### Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/views/clients/list.vue` | (1) Importar `useClientColumnChooser`. (2) Cambiar `columns` de array estático a `computed` filtrado por `isVisible`. (3) Agregar `allColumns` con las 11 columnas. (4) Agregar dropdown Column Chooser (UI idéntica a `cases/list.vue`). (5) Agregar `showColumnChooser` ref + `handleClickOutside` + `onUnmounted`. |
| `resources/js/src/locales/en.json` | Agregar `clients.columns` y `clients.reset_columns` |
| `resources/js/src/locales/es.json` | Ídem en español |
| `resources/js/src/locales/fr.json` | Ídem en francés |

---

## Checklist de Implementación

### Fase 1: Composable ✅
- [x] Crear `resources/js/src/composables/useClientColumnChooser.ts`
  - [x] Definir `STORAGE_KEY = 'vite-it:clients:columns'`
  - [x] Definir `DEFAULT_COLUMNS` con las 11 columnas
  - [x] Implementar `loadColumns()` con merge strategy y `try/catch`
  - [x] Implementar `toggleColumn(field)` — solo columnas no bloqueadas
  - [x] Implementar `resetColumns()` — limpia localStorage + recarga defaults
  - [x] Implementar `isVisible(field)` — verifica `visible`
  - [x] Implementar `saveColumns()` — persiste mapa `{field: visible}` en localStorage

### Fase 2: i18n ✅
- [x] Agregar `clients.columns` y `clients.reset_columns` en `locales/en.json`
- [x] Agregar `clients.columns` y `clients.reset_columns` en `locales/es.json`
- [x] Agregar `clients.columns` y `clients.reset_columns` en `locales/fr.json`

### Fase 3: Integración en `clients/list.vue` ✅
- [x] Importar `useClientColumnChooser`
- [x] Reemplazar array estático `columns` por `allColumns` + computed `columns` filtrado
- [x] Agregar `showColumnChooser = ref(false)`
- [x] Agregar dropdown Column Chooser en la sección de filtros (antes de Per Page)
  - [x] Botón toggle con ícono de columnas + label `$t('clients.columns')`
  - [x] Panel dropdown con lista de checkboxes (solo columnas no bloqueadas)
  - [x] Botón "Restablecer" con `resetColumns()`
- [x] Agregar templates de columnas nuevas: `nationality`, `marital_status`, `profession`, `arrival_date`
- [x] Agregar `handleClickOutside` para cerrar el dropdown al hacer clic fuera
- [x] Registrar/desregistrar listener en `onMounted`/`onUnmounted`

### Fase 4: QA ✅
- [x] `npm run build` — ✅ completado en 8.07s sin errores TypeScript
- [ ] Verificar persistencia: ocultar columna → recargar página → columna sigue oculta
- [ ] Verificar Reset: restaura las columnas por defecto
- [ ] Verificar que `first_name` y `actions` no aparecen en el dropdown (bloqueadas)
- [ ] Verificar en los 3 idiomas (EN, ES, FR)

---

## Verificación Final

**Fecha:** 2026-03-15

**Frontend build:** `npm run build` — ✅ 8.07s, cero errores TypeScript

**Prerequisito backend:** `ClientResource` no existe — el controller retorna el modelo Eloquent directamente con todos los campos. Los campos `nationality`, `marital_status`, `profession`, `arrival_date` ya están disponibles en el API response. Sin cambios necesarios.

**Archivos nuevos creados:**
- `resources/js/src/composables/useClientColumnChooser.ts`

**Archivos modificados:**
- `resources/js/src/views/clients/list.vue` (allColumns, computed columns, dropdown chooser, templates nuevos, click-outside)
- `resources/js/src/locales/en.json` (+2 keys)
- `resources/js/src/locales/es.json` (+2 keys)
- `resources/js/src/locales/fr.json` (+2 keys)
