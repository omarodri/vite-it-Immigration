# Fases de Implementacion Frontend - Companions en Vistas de Expediente

| Campo | Valor |
|-------|-------|
| **Fecha** | 2026-03-12 |
| **Tipo** | Frontend Implementation Phases |
| **Epic** | 2.3 - Companions en vistas de expediente |
| **Alcance** | Solo frontend (Vue 3.5 + TypeScript) |
| **Estado** | ✅ COMPLETADO |

---

## Resumen Ejecutivo

Este documento detalla las fases necesarias para completar la visualizacion y gestion de companions (acompanantes) dentro de las vistas de detalle (`show.vue`) y edicion (`edit.vue`) de expedientes migratorios. Actualmente, el wizard de creacion ya soporta la seleccion de companions, pero las vistas posteriores no los muestran ni permiten modificarlos.

Se identifican **5 gaps** que se resuelven en **4 fases** ordenadas por dependencias.

---

## Prerequisitos del Backend (Verificados ✅)

1. **`GET /api/cases/{id}`** retorna la relacion `companions` con los campos: `id`, `first_name`, `last_name`, `full_name`, `relationship`, `relationship_label`, `age`, `nationality`, `date_of_birth`, `gender`. ✅
2. **`PUT /api/cases/{id}`** acepta el campo `companion_ids` (array de enteros) y sincroniza la relacion pivot. ✅
3. **`GET /api/clients/{id}/companions`** sigue funcionando correctamente. ✅

---

## Fase 1 - Actualizacion de tipos e interfaces ✅ COMPLETADO

**Objetivo:** Preparar la capa de tipos para que soporte los campos adicionales de companions en la respuesta del API y permita enviar `companion_ids` en la actualizacion de expedientes.

**Archivos modificados:**
- `resources/js/src/types/case.ts` ✅

**Pasos:**

1. Localizar la interfaz `ImmigrationCase`, especificamente la propiedad `companions?`. Dentro del tipo inline del array, agregar las siguientes propiedades opcionales despues de `relationship_label?`:
   - `age?: number` -- edad calculada del companion
   - `nationality?: string` -- nacionalidad del companion
   - `date_of_birth?: string` -- fecha de nacimiento en formato ISO
   - `gender?: string` -- genero del companion
   - `initials?: string` -- iniciales precalculadas desde el backend

2. Localizar la interfaz `UpdateCaseData`. Agregar la propiedad `companion_ids?: number[]` al final del bloque.

**Criterio de verificacion:**
- El proyecto compila sin errores de TypeScript. ✅
- Las nuevas propiedades son opcionales y no rompen el tipado existente. ✅

**Tiempo estimado:** 15 minutos

---

## Fase 2 - Traducciones ✅ COMPLETADO

**Objetivo:** Agregar las keys de traduccion necesarias para los tres idiomas soportados (ingles, espanol, frances).

**Archivos modificados:**
- `resources/js/src/locales/en.json` ✅
- `resources/js/src/locales/es.json` ✅
- `resources/js/src/locales/fr.json` ✅

**Keys agregadas en los tres archivos:**

| Key | EN | ES | FR |
|-----|----|----|-----|
| `cases.companions` | Companions | Acompañantes | Accompagnants |
| `cases.no_companions` | No companions associated with this case | No hay acompañantes asociados a este expediente | Aucun accompagnant associé à ce dossier |
| `cases.tab_companions` | Companions | Acompañantes | Accompagnants |
| `cases.companions_count` | Companions ({count}) | Acompañantes ({count}) | Accompagnants ({count}) |
| `cases.companion_age` | {age} years old | {age} años | {age} ans |
| `cases.select_companions` | Select Companions | Seleccionar Acompañantes | Sélectionner les accompagnants |
| `cases.select_companions_description` | Select the companions to include in this case | Seleccione los acompañantes a incluir en este expediente | Sélectionnez les accompagnants à inclure dans ce dossier |

**Criterio de verificacion:**
- Los tres archivos JSON parsean correctamente. ✅
- `$t('cases.companions')` retorna el texto traducido segun el idioma activo. ✅

**Tiempo estimado:** 20 minutos

---

## Fase 3 - Seccion de companions en show.vue ✅ COMPLETADO

**Objetivo:** Mostrar la lista de companions asociados al expediente en la vista de detalle, dentro del tab 'info', columna derecha, entre la seccion "Client Information" y "Important Dates".

**Archivo modificado:**
- `resources/js/src/views/cases/show.vue` ✅

**Pasos:**

### 3.1 - Template: Insertar bloque de companions

1. Localizar en el template el cierre del bloque "Client Information". Inmediatamente despues de ese cierre y antes del bloque "Important Dates", insertar una nueva seccion con:
   - Titulo con key `cases.companions` + badge contador `badge-outline-secondary`
   - Estado vacio con key `cases.no_companions`
   - Lista de companion cards con: avatar circular `bg-secondary/10 text-secondary`, nombre completo (truncado), badge de relacion `badge-outline-secondary`, edad y nacionalidad opcionales

### 3.2 - Script: Sin cambios necesarios

La funcion `getInitials()` ya existe. Los datos vienen precargados desde `caseStore.fetchCase()`. No se requieren imports adicionales.

**Criterio de verificacion:**
- La seccion de companions aparece entre "Client Information" e "Important Dates". ✅
- Cards con avatar, nombre, badge de relacion, edad y nacionalidad. ✅
- Estado vacio si no hay companions. ✅
- Contador correcto en el titulo. ✅
- Colores `secondary` (diferenciado de `primary` del cliente). ✅

**Tiempo estimado:** 45 minutos

---

## Fase 4 - Seccion de companions en edit.vue ✅ COMPLETADO

**Objetivo:** Permitir al usuario seleccionar o deseleccionar companions al editar un expediente, enviando `companion_ids` en el payload de actualizacion.

**Archivo modificado:**
- `resources/js/src/views/cases/edit.vue` ✅

**Pasos:**

### 4.1 - Script: Imports y estado

1. Agregar imports: `useCompanionStore`, tipo `Companion`, `watch`.
2. Agregar refs: `availableCompanions`, `selectedCompanionIds`, `isLoadingCompanions`.
3. Agregar funcion `toggleCompanion(id: number)`.
4. Agregar `companion_ids: []` al reactive `form`.

### 4.2 - Script: Carga de datos en onMounted

1. Pre-popular `selectedCompanionIds` con los IDs de companions actuales del expediente.
2. Cargar companions del cliente via `companionStore.fetchCompanions(client_id)`.
3. Agregar `watch` sobre `selectedCompanionIds` para sincronizar con `form.companion_ids`.

### 4.3 - Template: Insertar seccion visual

Seccion full-width despues de los campos de descripcion con:
- Skeleton loader durante carga
- Estado vacio si cliente sin companions
- Cards de companions seleccionables (checkbox inline + avatar + nombre + relacion)
- Badge informativo con count de seleccionados
- Interaccion: click en card o en checkbox llama `toggleCompanion()`
- Estilo de seleccion: `border-secondary bg-secondary/5`

**Decision de diseño:** Se implementaron los checkboxes directamente en el template de `edit.vue` en lugar de reutilizar el componente `CompanionCheckbox` del wizard. Razon: el layout del wizard y el del formulario de edicion son diferentes, y la implementacion inline es mas simple y evita acoplamiento con componentes del wizard.

**Criterio de verificacion:**
- Seccion de companions visible en `/cases/{id}/edit`. ✅
- Companions del cliente se cargan y se muestran como cards seleccionables. ✅
- Companions ya vinculados aparecen pre-seleccionados. ✅
- Payload de submit incluye `companion_ids` con los IDs correctos. ✅
- Contador de seleccionados actualizado en tiempo real. ✅
- Estado vacio si cliente sin companions. ✅

**Tiempo estimado:** 1 hora 15 minutos

---

## Mapa de Dependencias

```
Fase 1 (Tipos)
    |
    +---> Fase 2 (Traducciones)  [independiente, paralelo]
    |         |
    |         v
    +---> Fase 3 (show.vue)  [depende de Fase 1 + Fase 2]
    |
    +---> Fase 4 (edit.vue)  [depende de Fase 1 + Fase 2]
```

---

## Resumen de Tiempos

| Fase | Descripcion | Tiempo estimado | Estado |
|------|-------------|-----------------|--------|
| Fase 1 | Actualizacion de tipos e interfaces | 15 min | ✅ COMPLETADO |
| Fase 2 | Traducciones (en, es, fr) | 20 min | ✅ COMPLETADO |
| Fase 3 | Seccion de companions en show.vue | 45 min | ✅ COMPLETADO |
| Fase 4 | Seccion de companions en edit.vue | 1 h 15 min | ✅ COMPLETADO |
| **Total** | | **2 h 35 min** | ✅ |

---

## Archivos Modificados en esta Implementacion

| Archivo | Tipo de cambio |
|---------|---------------|
| `resources/js/src/types/case.ts` | Extendido tipo inline companions + companion_ids en UpdateCaseData |
| `resources/js/src/locales/en.json` | 7 nuevas keys `cases.*` |
| `resources/js/src/locales/es.json` | 7 nuevas keys `cases.*` |
| `resources/js/src/locales/fr.json` | 7 nuevas keys `cases.*` |
| `resources/js/src/views/cases/show.vue` | Nueva seccion de companions en tab info |
| `resources/js/src/views/cases/edit.vue` | Nueva seccion de seleccion de companions |
