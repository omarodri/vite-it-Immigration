---
tipo: spec
numero: 35
modulo: UX e Internacionalización
dominio: UX
estado: DONE
tags: [spec, ux, done]
---
# [[35_ux_i18n_data_robustness|Spec 35]]: Estandarización de UX, Internacionalización y Robustez de Datos

## Información del Documento

- **Fecha:** 2026-04-01
- **Tipo:** Análisis de Impacto Técnico y Plan de Implementación
- **Scope:** Estandarización de fechas, paridad Cliente/Acompañante, sesión, i18n trilingüe, validación visual
- **Dependencias:** [[28_multi_tenant_branding_storage|Spec 28]] (Multi-Tenant), [[34_security_hardening|Spec 34]] (Security Hardening)
- **Estado:** Implementado
- **Metodología:** Auditoría de código fuente, comparación de modelos, análisis de keys i18n

---

## 1. Resumen Ejecutivo

Auditoría técnica de 4 dimensiones críticas de VITE-IT Immigration. Se identificaron **18 hallazgos** que afectan la consistencia de datos, experiencia de usuario e internacionalización.

### Estado Actual — Controles Positivos

| Control | Estado | Detalle |
|---------|--------|---------|
| Vuelidate instalado y en uso | OK | 13 componentes ya lo utilizan |
| Interceptor 401 → redirect login | OK | `api.ts` línea 96-103 |
| Interceptor 419 → refresh CSRF + retry | OK | `api.ts` línea 121-140 |
| Backend valida `after_or_equal:start_date` en eventos | OK | `StoreEventRequest`, `UpdateEventRequest`, `RescheduleEventRequest` |
| Dashboard recarga datos en cada navegación | OK | `onMounted(() => dashboard.fetchDashboard())` sin KeepAlive |
| DOMPurify para XSS (Spec 34) | OK | Implementado en todos los v-html |

### Hallazgos Críticos

| ID | Hallazgo | Severidad | Dimensión |
|----|----------|-----------|-----------|
| UX-001 | No existe componente wrapper de flatpickr — 6 archivos independientes sin dark mode ni i18n | Alta | Fechas |
| UX-002 | DateManager.vue tiene strings hardcoded (`Vencida`, `Proxima`, `Pendiente`, `Sin fecha`) | Media | i18n |
| UX-003 | Fechas de cliente/expediente no usan altFormat — usuario ve `Y-m-d` en vez de formato localizado | Media | UX |
| DATA-001 | **`canada_status` desincronizado** — Frontend envía `protected_person`, `refugee_claimant`; Backend valida `asylum_seeker`, `refugee` | **Crítica** | Datos |
| DATA-002 | Campo `passport_issue_date` no existe en ningún modelo | Media | Datos |
| DATA-003 | Companion carece de 14 campos que Client tiene (language, profession, address, permits, etc.) | Media | Datos |
| DATA-004 | Companion usa `canada_status_other` pero Client usa `other_status_1` — naming inconsistente | Baja | Datos |
| I18N-001 | **434 keys faltan en FR**, 127 en EN, 17 en ES — FR está al 70% | **Alta** | i18n |
| I18N-002 | Strings hardcoded en DateManager.vue, api.ts, y potencialmente otros | Media | i18n |
| I18N-003 | Mensajes de error del interceptor axios están en inglés hardcoded | Media | i18n |
| SESS-001 | No hay detección de inactividad prolongada en frontend | Baja | Sesión |
| VAL-001 | No hay patrón visual unificado para errores de validación en formularios | Media | Validación |
| UX-004 | Rangos de fecha (Desde-Hasta) no tienen restricción visual en frontend, solo backend | Media | Fechas |
| UX-005 | Eventos/Calendario no distinguen entre fecha pura y datetime — todo es `date` en backend | Media | Fechas |

---

## 2. Decisiones de Diseño

| # | Decisión | Justificación |
|---|----------|---------------|
| D1 | Crear wrapper `AppDatePicker.vue` con props para dark mode, locale, enableTime | Centraliza configuración en 1 punto. Los 6 archivos actuales duplican configuración |
| D2 | Usar `altFormat` de flatpickr para mostrar formato localizado pero enviar `Y-m-d` al backend | El usuario ve `dd/mm/aaaa` pero la API recibe `2026-04-01` — sin conversión de timezone |
| D3 | Alinear Frontend a los valores del Backend para `canada_status` | El backend es fuente de verdad; cambiar el frontend es menos riesgoso que migrar datos |
| D4 | Extender Vuelidate (ya instalado) en vez de crear sistema propio de validación visual | Ya está en 13 componentes. Solo falta estandarizar las clases CSS de error |
| D5 | Fechas de cliente/expediente como DATE puro; Eventos como DATETIME con hora | Los documentos legales no deben tener componente horario. Calendario sí lo necesita |
| D6 | Agregar `passport_issue_date` a Client Y Companion para paridad | Campo necesario para trámites de inmigración donde se requiere validez del pasaporte |
| D7 | FR como idioma terciario — completar keys pendientes en batch | 434 keys faltantes requieren traducción profesional o batch |

---

## 3. Análisis Detallado por Dimensión

### 3.1 Estandarización de Campos de Fecha

#### Estado Actual

| Archivo | Modo | Dark Mode | i18n placeholder | altFormat |
|---------|------|-----------|------------------|-----------|
| `DateManager.vue` | date only | No | No | No |
| `clients/create.vue` | date only | No | No | No |
| `clients/edit.vue` | date only | No | No | No |
| `clients/show.vue` | date only | No | No | No |
| `InvoiceTable.vue` | date only | No | No | No |
| `user-account-settings.vue` | date only | No | No | No |

Todos usan `{ dateFormat: 'Y-m-d', allowInput: true }` sin localización.

#### Componente Wrapper Propuesto: `AppDatePicker.vue`

```
Props:
  - modelValue: string | null
  - enableTime: boolean (default: false) — solo para Calendario/Eventos
  - minDate: string | null — para restricción "Desde"
  - maxDate: string | null — para restricción "Hasta"
  - placeholder: string | null — override del placeholder i18n automático

Comportamiento:
  - dateFormat: siempre 'Y-m-d' (o 'Y-m-d H:i' si enableTime)
  - altInput: true
  - altFormat: computado según locale activo
    - ES: 'd/m/Y' (placeholder: dd/mm/aaaa)
    - EN: 'm/d/Y' (placeholder: mm/dd/yyyy)
    - FR: 'd/m/Y' (placeholder: jj/mm/aaaa)
  - Dark mode: watch store.isDarkMode → toggle clase 'dark' en flatpickr
  - Locale de flatpickr: importar Spanish/French dinámicamente
```

#### Fechas Puras vs DateTime

| Contexto | Tipo | Zona Horaria | Justificación |
|----------|------|--------------|---------------|
| Client: date_of_birth, passport_expiry, arrival_date | DATE | Sin TZ | Documentos legales, valor absoluto |
| Case: important_dates | DATE | Sin TZ | Fechas de vencimiento legales |
| Invoice: due_date | DATE | Sin TZ | Fecha de vencimiento fiscal |
| Event: start_date, end_date | DATETIME | Con TZ (UTC en BD, local en UI) | Calendario necesita hora para evitar solapamientos |

**Restricción de rangos en frontend:**
- En Eventos: `end_date` flatpickr debe recibir `minDate: startDate` como prop reactivo
- En Calendario: FullCalendar ya maneja esto visualmente con drag, pero el form modal no lo restricta

---

### 3.2 Integridad de Datos y Nuevos Campos

#### BUG CONFIRMADO: `canada_status` Desincronizado

| Valor Frontend | Valor Backend | Resultado |
|----------------|---------------|-----------|
| `protected_person` | No existe en Rule::in | **422 — "The selected canada status is invalid"** |
| `refugee_claimant` | No existe en Rule::in | **422 — "The selected canada status is invalid"** |
| `temporary_resident` | `temporary_resident` | OK |
| `permanent_resident` | `permanent_resident` | OK |
| `citizen` | `citizen` | OK |
| `visitor` | `visitor` | OK |
| `student` | `student` | OK |
| `worker` | `worker` | OK |
| `other` | `other` | OK |
| — | `asylum_seeker` | No seleccionable en frontend |
| — | `refugee` | No seleccionable en frontend |

**Root Cause:** Frontend usa etiquetas de inmigración canadiense (`protected_person`, `refugee_claimant`), backend usa etiquetas genéricas (`asylum_seeker`, `refugee`). Se actualizaron los labels del frontend sin sincronizar los values con el backend.

**Solución:** Alinear frontend values al backend: `protected_person` → `asylum_seeker`, `refugee_claimant` → `refugee`. O si los nuevos valores son preferidos por negocio, actualizar el backend Rule::in + crear migración para actualizar datos existentes.

#### Paridad de Campos: Client vs Companion

| Campo | Client | Companion | Acción |
|-------|--------|-----------|--------|
| `passport_issue_date` | No | No | **Agregar a ambos** |
| `language` | Sí | No | Evaluar si necesario |
| `second_language` | Sí | No | Evaluar si necesario |
| `second_nationality` | Sí | No | Evaluar si necesario |
| `profession` | Sí | No | Evaluar si necesario |
| `marital_status` | Sí | No | Evaluar si necesario |
| `residential_address` | Sí | No | No necesario (comparte con cliente) |
| `mailing_address` | Sí | No | No necesario |
| `city`, `province`, `postal_code`, `country` | Sí | No | No necesario |
| `secondary_phone` | Sí | No | Evaluar |
| `work_permit_number` | Sí | No | **Relevante para acompañantes con permiso propio** |
| `study_permit_number` | Sí | No | **Relevante para acompañantes estudiantes** |
| `permit_expiry_date` | Sí | No | **Relevante si tiene permiso** |
| `arrival_date` | Sí | No | **Relevante — puede haber llegado en fecha distinta** |
| `entry_point` | Sí | No | Evaluar |
| `status_date` | Sí | No | Evaluar |
| `canada_status_other` (Client: `other_status_1`) | Sí | Sí | **Normalizar naming** |

**Campos prioritarios para Companion:** `passport_issue_date`, `work_permit_number`, `study_permit_number`, `permit_expiry_date`, `arrival_date`

---

### 3.3 Comportamiento y Seguridad de Sesión

#### Estado Actual

| Mecanismo | Implementado | Detalle |
|-----------|-------------|---------|
| 401 → redirect login | Sí | `api.ts:96-103` |
| 419 → refresh CSRF + retry | Sí | `api.ts:121-140` — reintenta 1 vez |
| Session lifetime | Config Laravel | Default 120 min |
| Inactivity detection frontend | **No** | No hay timer de inactividad |
| Dashboard reload on mount | Sí | `onMounted` sin KeepAlive |

#### Mejora Propuesta: Timer de Inactividad

```
Composable: useInactivityTimer(timeoutMinutes: number)
- Escucha: mousemove, keydown, click, scroll (throttled)
- Al expirar: muestra modal "Tu sesión ha expirado" → redirect login
- Reset: cada interacción reinicia el timer
- Timeout sugerido: sincronizar con SESSION_LIFETIME de Laravel
```

**No es bloqueante** — el interceptor 401 ya cubre el caso donde el backend rechaza la sesión. El timer sería UX improvement para evitar que el usuario trabaje en un form largo y pierda datos al enviar.

---

### 3.4 Interfaces e Internacionalización

#### Gap de Traducciones

| Idioma | Keys Totales | Keys Faltantes (vs ES como base) | Cobertura |
|--------|-------------|----------------------------------|-----------|
| ES | 1,419 | — (base) | 100% |
| EN | 1,309 | 127 faltantes + 17 en ES que no están en EN | 91% |
| FR | 1,000 | **434 faltantes** | **70%** |

**Categorías principales de keys faltantes en FR:**
- `users.*` — módulo de usuarios completo
- `permissions.*` — nombres de permisos
- `clients.*` — selects y placeholders
- `cases.*` — expedientes
- `companions.*` — acompañantes
- `documents.*` — documentos

#### Strings Hardcoded Identificados

| Archivo | Línea | String | Fix |
|---------|-------|--------|-----|
| `DateManager.vue` | 63-67 | `Vencida`, `Proxima`, `Pendiente`, `Sin fecha` | Usar `t('dates.overdue')`, etc. |
| `api.ts` | 88 | `'Network error. Please check your connection.'` | `t('errors.network')` |
| `api.ts` | 109 | `'Please verify your email address.'` | `t('errors.verify_email')` |
| `api.ts` | 112 | `'You do not have permission...'` | `t('errors.forbidden')` |
| `api.ts` | 118 | `'The requested resource was not found.'` | `t('errors.not_found')` |
| `api.ts` | 134 | `'Session expired. Please refresh the page.'` | `t('errors.session_expired')` |
| `api.ts` | 150 | `'Too many requests...'` | `t('errors.rate_limit')` |
| `api.ts` | 156 | `'Server error...'` | `t('errors.server')` |

**Nota sobre api.ts:** Internacionalizar el interceptor requiere acceso al store de i18n fuera de un componente Vue. Solución: importar la instancia de i18n directamente.

#### Validación Visual — Patrón Propuesto

**Estado actual:** Vuelidate está en 13 componentes pero NO hay estilo CSS consistente para campos con error.

**Patrón a estandarizar:**
```vue
<!-- Campo con error visual -->
<input
  v-model="form.field"
  class="form-input"
  :class="{ '!border-danger': v$.field.$error }"
/>
<span v-if="v$.field.$error" class="text-danger text-xs mt-1">
  {{ v$.field.$errors[0]?.$message }}
</span>
```

**Clases Tailwind para error:**
- Input border: `!border-danger` (override del border default)
- Label: `!text-danger` cuando campo tiene error
- Mensaje: `text-danger text-xs mt-1`

---

## 4. Plan de Implementación

### Fase 1: Bug Fix Crítico — canada_status (DATA-001)

| # | Tarea | Estado |
|---|-------|--------|
| 1.1 | Alinear values de `CanadaStatusOptions` en `clients/create.vue` y `clients/edit.vue` con backend | Hecho |
| 1.2 | Verificar que las mismas opciones estén en los forms de Companion | Hecho |
| 1.3 | Agregar keys i18n para los status alineados (`asylum_seeker`, `refugee`) en ES, EN, FR | Hecho |

**Archivos a modificar:**
- `resources/js/src/views/clients/create.vue`
- `resources/js/src/views/clients/edit.vue`
- `resources/js/src/locales/es.json`, `en.json`, `fr.json`

---

### Fase 2: Componente AppDatePicker (UX-001, UX-003)

| # | Tarea | Estado |
|---|-------|--------|
| 2.1 | Crear `resources/js/src/components/AppDatePicker.vue` con dark mode, locale, enableTime | Hecho |
| 2.2 | Agregar locales de flatpickr (Spanish, French) | Hecho |
| 2.3 | Reemplazar flatpickr directo en 6 archivos por `<AppDatePicker>` | Hecho |
| 2.4 | Agregar prop `minDate` para restricción de rangos Desde-Hasta | Hecho |

**Archivos a modificar:**
- Crear: `resources/js/src/components/AppDatePicker.vue`
- Modificar: `DateManager.vue`, `clients/create.vue`, `clients/edit.vue`, `InvoiceTable.vue`, `user-account-settings.vue`

---

### Fase 3: Nuevo Campo passport_issue_date (DATA-002)

| # | Tarea | Estado |
|---|-------|--------|
| 3.1 | Crear migración para agregar `passport_issue_date` a `clients` y `companions` | Hecho |
| 3.2 | Agregar a `$fillable` y `$casts` en ambos modelos | Hecho |
| 3.3 | Agregar a FormRequests de Client y Companion (Store y Update) | Hecho |
| 3.4 | Agregar al formulario de create/edit de Client y Companion en frontend | Hecho |
| 3.5 | Agregar a Resources (ClientResource, CompanionResource) | Hecho |
| 3.6 | Agregar keys i18n en 3 idiomas | Hecho |

**Archivos a crear:**
- `database/migrations/xxxx_add_passport_issue_date_to_clients_and_companions.php`

---

### Fase 4: Paridad de Campos Companion (DATA-003)

| # | Tarea | Estado |
|---|-------|--------|
| 4.1 | Crear migración para agregar campos prioritarios a `companions`: `work_permit_number`, `study_permit_number`, `permit_expiry_date`, `arrival_date` | Hecho |
| 4.2 | Actualizar modelo Companion ($fillable, $casts) | Hecho |
| 4.3 | Actualizar FormRequests de Companion | Hecho |
| 4.4 | Actualizar CompanionResource | Hecho |
| 4.5 | Actualizar formularios frontend de Companion | Hecho |
| 4.6 | Agregar keys i18n | Hecho |

---

### Fase 5: Internacionalización Completa (I18N-001, I18N-002, I18N-003)

| # | Tarea | Estado |
|---|-------|--------|
| 5.1 | Completar 127 keys faltantes en `en.json` | Hecho |
| 5.2 | Completar 423 keys faltantes en `fr.json` | Hecho |
| 5.3 | Completar keys faltantes en `es.json` | Hecho |
| 5.4 | Reemplazar strings hardcoded en `DateManager.vue` | Hecho |
| 5.5 | Internacionalizar mensajes de error en `api.ts` | Hecho |

**Archivos a modificar:**
- `resources/js/src/locales/es.json`, `en.json`, `fr.json`
- `resources/js/src/components/DateManager.vue`
- `resources/js/src/services/api.ts`

---

### Fase 6: Estandarización de Validación Visual (VAL-001)

| # | Tarea | Estado |
|---|-------|--------|
| 6.1 | Crear composable `useFormValidation` con helpers para clases de error | Pendiente |
| 6.2 | Estandarizar patrón `!border-danger` + mensaje en formularios existentes con Vuelidate | Pendiente |
| 6.3 | Integrar errores de API (422) con Vuelidate usando `$externalResults` | Pendiente |

**Archivos a crear:**
- `resources/js/src/composables/useFormValidation.ts`

---

### Fase 7: Timer de Inactividad (SESS-001)

| # | Tarea | Estado |
|---|-------|--------|
| 7.1 | Crear composable `useInactivityTimer` | Pendiente |
| 7.2 | Integrar en `App.vue` o `app-layout.vue` | Pendiente |
| 7.3 | Sincronizar timeout con `SESSION_LIFETIME` de Laravel | Pendiente |

---

## 5. Estimación de Impacto

| Dimensión | Valor |
|-----------|-------|
| Archivos backend a modificar | ~8 |
| Archivos backend a crear | 1 (migración) |
| Archivos frontend a modificar | ~15 |
| Archivos frontend a crear | 3 (AppDatePicker, useFormValidation, useInactivityTimer) |
| Keys i18n a agregar | ~580 (127 EN + 434 FR + ~20 nuevos) |
| Migraciones | 1 (passport_issue_date + campos companion) |
| Riesgo de regresión | Medio — canada_status fix puede afectar datos existentes si se cambian values |
| Impacto en usuarios | Alto positivo — fechas legibles, formularios validados, FR completo |

---

## 6. Priorización Recomendada

| Prioridad | Fase | Justificación |
|-----------|------|---------------|
| **P0 - Inmediata** | Fase 1: canada_status fix | Bug activo que impide guardar clientes con ciertos status |
| **P1 - Alta** | Fase 5: i18n (EN + FR) | FR al 70% es inaceptable para usuarios francófonos |
| **P1 - Alta** | Fase 2: AppDatePicker | Base para todas las demás mejoras de fecha |
| **P2 - Media** | Fase 3: passport_issue_date | Necesario para trámites, bajo riesgo |
| **P2 - Media** | Fase 6: Validación visual | UX improvement significativo |
| **P3 - Baja** | Fase 4: Paridad companion | Nice to have, depende de requisitos de negocio |
| **P3 - Baja** | Fase 7: Timer inactividad | El interceptor 401 ya cubre el caso funcional |
