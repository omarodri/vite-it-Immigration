---
tipo: spec
numero: 42
modulo: UX e Internacionalización
dominio: UX
estado: DONE
tags: [spec, ux, done]
---
# [[42_modal_guard_dirty_state|Spec 42]] — Modal Guard: Static + Dirty-State Confirmation

## Contexto

Los modales actuales del sistema usan `@headlessui/vue Dialog`, cuyo evento `@close` se dispara en dos situaciones: clic fuera del panel (backdrop) y tecla Escape. En todos los casos el modal se cierra inmediatamente sin verificar si el formulario fue modificado. Esto provoca pérdidas accidentales de datos al consultor.

---

## Criterios de Aceptación

- El modal **no** se cierra al presionar la tecla `Escape`.
- El modal **no** se cierra al hacer clic en el backdrop externo.
- El modal **solo** se cierra mediante el botón X (esquina superior derecha) o el botón Cancelar/Cerrar.
- Si el formulario tiene cambios sin guardar (`isDirty = true`), tanto la X como Cancelar muestran confirmación SweetAlert2 antes de cerrar.
- Si el formulario está limpio (`isDirty = false`), la X y Cancelar cierran inmediatamente sin fricción.
- El guardado exitoso cierra el modal sin confirmación (`forceClose()`).

**Patrón de implementación obligatorio (HeadlessUI Dialog):**
```html
<!-- Correcto: @close no-op → backdrop y Escape bloqueados -->
<Dialog as="div" @close="() => {}" class="...">
    ...
    <!-- Solo la X y Cancelar llaman a handleClose() -->
    <button @click="handleClose()"><icon-x /></button>
    <button @click="handleClose()">{{ $t('common.cancel') }}</button>
```

---

## Análisis Técnico

### 1. Librería de Modales: Headless UI

El proyecto usa **@headlessui/vue `Dialog`**, no Bootstrap-Vue ni Vuetify. El componente no tiene una prop `static` nativa; el control de cierre se gestiona enteramente mediante el handler del evento `@close`.

```html
<!-- Patrón actual — se cierra en backdrop y Escape sin control -->
<Dialog as="div" @close="addTaskModal = false">
```

**Solución:** Reemplazar el handler directo por una función `handleClose()` que aplique la lógica de dirty-check. El backdrop y el Escape siempre pasan por `@close`, por lo que un único punto de intercepción cubre ambos.

Para **bloquear completamente** el cierre sin confirmación (comportamiento "static"):
```html
<!-- No-op en el evento Dialog: el backdrop y Escape no hacen nada por sí solos -->
<Dialog as="div" @close="() => {}">
    ...
    <!-- Solo el botón X llama a handleClose -->
    <button @click="handleClose()">X</button>
```

> **Decisión**: No bloquear completamente. En cambio, dejar que `@close` pase por `handleClose()`. Si el formulario está limpio, cierra instantáneamente (sin fricción). Si está sucio, muestra confirmación. Esto cubre backdrop + Escape + botón X con un solo flujo.

### 2. Detección de Cambios: Comparación JSON

**Opciones evaluadas:**

| Estrategia | Ventaja | Desventaja |
|------------|---------|------------|
| `watch(form, deep)` con flag `isDirty` | Simple | Puede producir falsos positivos si se resetea el form programáticamente |
| Hash MD5 de JSON | Determinístico | Requiere librería |
| **`JSON.stringify` snapshot** | Sin dependencias, O(n) | Falla con `undefined` → usar `JSON.stringify` con replacer o normalizar a `null` |
| Librería `vueuse/isDirty` | Ergonómica | Agrega dependencia |

**Decisión: snapshot JSON** — almacena `JSON.stringify(initialForm)` al montar el modal y compara con `JSON.stringify(currentForm)` en `handleClose`. Sin dependencias externas, predecible.

```typescript
const initialSnapshot = ref('');
const isDirty = computed(() =>
    JSON.stringify(form) !== initialSnapshot.value
);

// Al abrir el modal:
watch(() => props.show, (val) => {
    if (val) initialSnapshot.value = JSON.stringify(toRaw(form));
});
```

### 3. Composable `useModalGuard`

Composable reutilizable que encapsula la lógica y usa **SweetAlert2** (ya disponible en el proyecto).

```typescript
// composables/useModalGuard.ts
export function useModalGuard<T extends object>(
    formGetter: () => T,
    onClose: () => void
) {
    const initialSnapshot = ref('');

    function captureSnapshot() {
        initialSnapshot.value = JSON.stringify(formGetter());
    }

    const isDirty = computed(() =>
        JSON.stringify(formGetter()) !== initialSnapshot.value
    );

    async function handleClose() {
        if (!isDirty.value) { onClose(); return; }
        const result = await Swal.fire({ ... confirmación ... });
        if (result.isConfirmed) onClose();
    }

    function forceClose() {
        initialSnapshot.value = JSON.stringify(formGetter()); // iguala snapshot
        onClose();
    }

    return { captureSnapshot, isDirty, handleClose, forceClose };
}
```

`forceClose()` se llama después de un guardado exitoso — iguala el snapshot al estado actual, lo que hace que `isDirty` devuelva `false` y `handleClose` cierre sin preguntar.

### 4. Diálogo de Confirmación

Usa **SweetAlert2** (ya importado en `TrashView.vue`). Respeta dark mode del sistema mediante clases CSS del proyecto.

```typescript
const result = await Swal.fire({
    title: t('modal_guard.unsaved_title'),
    text: t('modal_guard.unsaved_text'),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e7515a',  // danger del sistema
    cancelButtonColor: '#805dca',   // secondary del sistema
    confirmButtonText: t('modal_guard.discard'),
    cancelButtonText: t('modal_guard.keep_editing'),
    reverseButtons: true,
});
```

### 5. Sesión Expirada con Modal Abierto

Si Axios recibe 401, el interceptor existente en `api.ts` redirige al login. Con el modal abierto, la redirección de Vue Router cierra el modal (el componente se desmonta) — no hay bloqueo. No requiere manejo adicional.

### 6. Falsos Positivos

- Si el usuario abre el modal y **no toca nada**: `isDirty = false` → cierra sin preguntar.
- Si el modal se abre con datos precargados (modo edición): `captureSnapshot()` se llama **después** de cargar los datos del prop, no en `mounted()`. Esto evita que los datos pre-llenados cuenten como cambio.
- Campos de solo lectura o metadata (`id`, `created_at`) deben excluirse del form objeto antes de serializar.

### 7. Modales en Alcance

| Componente | Archivo | Acción |
|------------|---------|--------|
| CompanionFormModal | `components/companions/CompanionFormModal.vue` | Aplicar composable |
| EventFormModal | `components/EventFormModal.vue` | Aplicar composable |
| Tarea (add) en CaseTodoTab | `views/cases/components/CaseTodoTab.vue` | Aplicar composable |
| ScrumTaskModal | `views/apps/scrumboard/components/ScrumTaskModal.vue` | Aplicar composable |
| CreateClientModal (wizard) | `views/cases/wizard/components/CreateClientModal.vue` | Aplicar composable |
| MoveDocumentModal | `views/cases/components/documents/MoveDocumentModal.vue` | **Excluir** — no tiene campos de texto libres, solo selects de destino |

**Excluidos por diseño** (modales de solo confirmación, no formularios):
- Diálogos de eliminación (SweetAlert2 puro)
- Modales de visualización/lectura

### 8. Riesgos

| Nivel | Riesgo | Mitigación |
|-------|--------|------------|
| 🟡 MEDIO | `JSON.stringify` diferencia por orden de keys | Normalizar el objeto con `Object.keys().sort()` antes de serializar |
| 🟡 MEDIO | Formularios con arrays o objetos anidados | `JSON.stringify` maneja anidamiento correctamente; excluir campos irrelevantes |
| 🟢 BAJO | SweetAlert2 bloqueado por adblocker | No aplica — es una librería local, no CDN |
| 🟢 BAJO | Fricción en usuarios avanzados | Si no hay cambios, nunca aparece la confirmación |

---

## Arquitectura

```
Modal abierto
  │ watch(props.show) → captureSnapshot()
  │
  │ [usuario escribe]
  │
  └─→ handleClose()  ← backdrop clic / Esc / botón X
        │
        ├── isDirty = false → onClose() inmediato
        │
        └── isDirty = true → Swal.fire(confirm)
                  │
                  ├── "Descartar" → onClose()
                  └── "Seguir editando" → no hace nada

  [guardado exitoso] → forceClose() → onClose() sin Swal
```

---

## Checklist de Implementación

### Fase 1 — Composable y i18n

- [x] Crear `resources/js/src/composables/useModalGuard.ts`
  - Parámetros: `formGetter: () => object`, `onClose: () => void`
  - Exports: `captureSnapshot()`, `isDirty`, `handleClose()`, `forceClose()`
  - Usa SweetAlert2 con las keys i18n `modal_guard.*`
- [x] Agregar keys `modal_guard.*` en `locales/es.json`, `en.json`, `fr.json`
  - `modal_guard.unsaved_title`, `modal_guard.unsaved_text`, `modal_guard.discard`, `modal_guard.keep_editing`

### Fase 2 — CompanionFormModal

- [x] Modificar `components/companions/CompanionFormModal.vue`
  - `captureSnapshot()` tras `loadData()` / `resetForm()` en el watch
  - `Dialog @close` y botones X y Cancelar usan `guardClose`
  - Tras guardado exitoso: `forceClose()`

### Fase 3 — EventFormModal

- [x] Modificar `components/EventFormModal.vue`
  - `captureSnapshot()` tras cargar `params` en el watch
  - `Dialog @close`, botón X y Cancelar usan `handleClose`
  - Tras `saveEvent()` y `deleteEvent()` exitosos: `forceClose()`

### Fase 4 — CaseTodoTab (modal de tarea)

- [x] Modificar `views/cases/components/CaseTodoTab.vue`
  - `captureTodoSnapshot()` vía `nextTick` en `addEditTask()`
  - `Dialog @close`, botón X y Cancelar usan `handleTodoClose`
  - Tras `saveTask()`: `forceTodoClose()`

### Fase 5 — ScrumTaskModal y CreateClientModal

- [x] Modificar `views/apps/scrumboard/components/ScrumTaskModal.vue`
  - `captureSnapshot()` al final del watch de `isOpen`
  - `Dialog @close` y botón X usan `guardClose`; tras submit exitoso: `forceClose()`
- [x] Modificar `views/cases/wizard/components/CreateClientModal.vue`
  - `captureSnapshot()` tras `resetForm()` en el watch de `props.open`
  - `Dialog @close` y Cancelar usan `guardClose`; tras creación exitosa: `forceClose()`

---

## Keys i18n

```json
// es.json
"modal_guard.unsaved_title": "¿Salir sin guardar?",
"modal_guard.unsaved_text": "Tienes cambios sin guardar que se perderán si sales ahora.",
"modal_guard.discard": "Descartar cambios",
"modal_guard.keep_editing": "Seguir editando"

// en.json
"modal_guard.unsaved_title": "Unsaved Changes",
"modal_guard.unsaved_text": "You have unsaved changes that will be lost if you leave now.",
"modal_guard.discard": "Discard Changes",
"modal_guard.keep_editing": "Keep Editing"

// fr.json
"modal_guard.unsaved_title": "Modifications non enregistrées",
"modal_guard.unsaved_text": "Vous avez des modifications non enregistrées qui seront perdues si vous quittez maintenant.",
"modal_guard.discard": "Abandonner",
"modal_guard.keep_editing": "Continuer l'édition"
```
