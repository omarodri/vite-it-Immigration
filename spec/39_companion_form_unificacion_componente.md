---
tipo: spec
numero: 39
modulo: Clientes y Acompañantes
dominio: CLNT
estado: DONE
tags: [spec, clientes, done]
relacionados:
  - "[[10_companions_en_detalle_expediente|Spec 10]]"
---
# [[39_companion_form_unificacion_componente|Spec 39]] — Unificación del Formulario de Companions en Componente Compartido

## Contexto

Actualmente la gestión de acompañantes (Companions) está dividida en dos implementaciones similares pero separadas:
1. **`resources/js/src/views/clients/show.vue`** — Modal en detalle del cliente. Companion con ID existente.
2. **`resources/js/src/components/cases/StepCompanions.vue`** — Paso del wizard de expedientes. Companion puede estar sin ID.

## Objetivo

Extraer la lógica del formulario en un componente reutilizable `CompanionFormModal.vue` con paridad funcional completa, agregando el campo `marital_status` faltante en ambas implementaciones.

---

## Arquitectura del Componente Compartido

**Ubicación:** `resources/js/src/components/companions/CompanionFormModal.vue`

### Contrato

```typescript
// Props
{
  show: boolean
  clientId: number
  companion?: Companion | null   // null = modo crear
  showDocuments?: boolean        // default: true
  persistMode: 'api' | 'emit'
}

// Emits
{
  'update:show': (value: boolean) => void
  'saved': (companion: Companion) => void
  'close': () => void
}
```

### Uso en show.vue
```vue
<CompanionFormModal
    v-model:show="showCompanionModal"
    :client-id="client.id"
    :companion="editingCompanion"
    persist-mode="api"
    @saved="onCompanionSaved"
/>
```

### Uso en StepCompanions.vue
```vue
<CompanionFormModal
    v-model:show="showCompanionModal"
    :client-id="wizard.state.clientId"
    :companion="null"
    persist-mode="api"
    @saved="companion => wizard.toggleCompanion(companion.id)"
/>
```

---

## Checklist de Implementación

### Prioridad 1 — Bug Crítico (StepCompanions campos obsoletos)

- [x] Eliminar `passport_number`, `passport_country`, `passport_expiry_date` del template de `StepCompanions.vue`
- [x] Eliminar esos campos del objeto `companionForm` en el script de `StepCompanions.vue`

### Prioridad 2 — Backend: campo `marital_status` en Companions

- [x] Crear migración `add_marital_status_to_companions` (nullable string(30))
- [x] Agregar `marital_status` a `$fillable` en `app/Models/Companion.php`
- [x] Agregar validación en `app/Http/Requests/Companion/StoreCompanionRequest.php`
- [x] Agregar validación en `app/Http/Requests/Companion/UpdateCompanionRequest.php`
- [x] Exponer `marital_status` en `app/Http/Resources/CompanionResource.php`

### Prioridad 3 — Frontend: tipos y store

- [x] Actualizar `resources/js/src/types/companion.ts` — agregar `marital_status` a interfaces
- [x] Corregir `RELATIONSHIP_TYPE_OPTIONS` — agregar `half-sibling`, `step-sibling`
- [x] Corregir getter `relationshipOptions` en `stores/companion.ts` — sincronizar con backend (15 tipos)

### Prioridad 4 — Crear CompanionFormModal.vue

- [x] Crear `resources/js/src/components/companions/CompanionFormModal.vue`
- [x] Implementar props/emits del contrato
- [x] Campos completos: first_name, last_name, relationship, relationship_other, date_of_birth, gender, nationality, marital_status, iuc, email, phone, canada_status, canada_status_other, arrival_date, notes
- [x] Usar `AppDatePicker` para date_of_birth y arrival_date
- [x] Usar `CountrySelect` para nationality
- [x] Usar `PhoneInput` para phone
- [x] Incluir `DocumentRepeater` condicionado por prop `showDocuments`
- [x] Lógica de save: crear/actualizar companion + sincronizar documentos legales
- [x] Soporte i18n y dark mode

### Prioridad 5 — Integrar en vistas existentes

- [x] Refactorizar `resources/js/src/views/clients/show.vue` — reemplazar modal inline con `CompanionFormModal`
- [x] Refactorizar `resources/js/src/components/cases/StepCompanions.vue` — reemplazar modal inline con `CompanionFormModal`

### Prioridad 6 — i18n

- [x] Verificar/agregar claves `companions.marital_status` en `locales/en.json`
- [x] Verificar/agregar claves `companions.marital_status` en `locales/es.json`
- [x] Verificar/agregar claves `companions.marital_status` en `locales/fr.json`

---

## Brechas Identificadas (Estado Inicial)

| Dimensión | show.vue | StepCompanions.vue |
|-----------|----------|--------------------|
| Modo | Crear + Editar | Solo Crear |
| DocumentRepeater | ✓ | ✗ |
| `arrival_date` | ✓ | ✗ |
| `marital_status` | ✗ | ✗ |
| Campos pasaporte | ✗ (correcto) | ✓ (OBSOLETO — bug) |
| DatePicker | AppDatePicker | `<input type="date">` nativo |
| Sección contacto | Separada con header | Inline sin header |

---

## Riesgos y Mitigaciones

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Campos pasaporte obsoletos en wizard | **CRÍTICO** | Eliminar antes de refactorizar |
| `entityId=0` en DocumentRepeater al crear | Bajo | DocumentRepeater no hace fetch propio — seguro |
| Auto-selección de companion en wizard post-save | Medio | `@saved` en StepCompanions llama `wizard.toggleCompanion()` |
| `marital_status` null en datos existentes | Bajo | Campo nullable — sin impacto en expedientes |

---

## Estrategia para Documentos Legales

El `clientId` ya existe cuando se llega al Step de Companions del wizard. Flujo **"Create-then-attach"**:

1. Crear companion via API → obtener ID real
2. Iterar `companionDocs` y llamar `legalDocumentService.createForCompanion(newId, doc)`
3. Emitir `@saved`

El `DocumentRepeater` recibe `entityId=0` durante creación (maneja solo estado local).

---

## Reducción Neta Estimada

- Código duplicado eliminado: ~500 líneas (250 de cada vista)
- Código nuevo del componente: ~350 líneas
- **Ganancia neta: ~150 líneas con un único punto de mantenimiento**
