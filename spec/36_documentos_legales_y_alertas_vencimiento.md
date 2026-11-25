---
tipo: spec
numero: 36
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[37_integridad_referencial_y_restricciones_eliminacion|Spec 37]]"
---
# [[36_documentos_legales_y_alertas_vencimiento|Spec 36]]: Módulo de Documentos Legales y Alertas de Vencimiento

## Información del Documento

- **Fecha:** 2026-04-01
- **Tipo:** Análisis de Impacto Arquitectónico y Plan de Implementación
- **Scope:** Gestión de documentos legales 1:N, módulo de alertas de vencimiento, reestructuración de campos de permisos en clientes/acompañantes, widget de dashboard, Quick Sync al calendario
- **Dependencias:** [[31_telefono_internacional_enrichment_companions|Spec 31]] (Teléfono Internacional / Companions), [[35_ux_i18n_data_robustness|Spec 35]] (UX i18n Robustez), [[22_calendario_legal_integrado|Spec 22]] (Calendario Legal)
- **Estado:** Implementado (Fases 1–6 completadas)
- **Metodología:** Análisis de impacto sobre modelos existentes, auditoría de campos planos, diseño de migración gradual, evaluación de patrones polimórficos vs. tablas separadas

---

## 1. Resumen Ejecutivo

El sistema actual almacena documentos legales (pasaportes, permisos de trabajo, permisos de estudio) como **columnas planas** en las tablas `clients` y `companions`. Esto impide que un cliente o acompañante tenga múltiples documentos del mismo tipo vigentes o históricos, y no provee un mecanismo de alerta proactiva de vencimiento.

Este spec define la transición de estructura plana a **relacional polimórfica** (tabla `legal_documents`), la creación de un **módulo de alertas de vencimiento** que consulta los datos en tiempo real, y los componentes Vue necesarios para su gestión.

### Hallazgos que motivan este cambio

| ID | Hallazgo | Severidad |
|----|----------|-----------|
| DOC-001 | Columnas planas limitan a 1 documento por tipo por persona — bloqueo funcional para clientes con múltiples permisos | Alta |
| DOC-002 | No existe vista consolidada de próximos vencimientos — consultores deben revisar cada expediente manualmente | Alta |
| DOC-003 | Pasaportes con fechas de vencimiento no generan ningún tipo de alerta | Alta |
| DOC-004 | Dashboard no tiene sección de vencimientos — información crítica ausente del centro de control | Media |
| DOC-005 | No hay botón de "sincronización rápida" para empujar una fecha de vencimiento al calendario de eventos | Media |
| DOC-006 | `companions` carece de historial de permisos — sólo tiene los campos más recientes | Media |

---

## 2. Decisiones de Diseño

| # | Decisión | Alternativa Descartada | Justificación |
|---|----------|------------------------|---------------|
| D1 | Tabla polimórfica única `legal_documents` para clients y companions | Dos tablas separadas (`client_documents`, `companion_documents`) | Mismo dominio documental, mismo formulario, mismo componente Vue. Duplicar código sin beneficio |
| D2 | Alertas leídas con **query live** sobre `legal_documents.expiry_date` | Tabla espejo `expiration_alerts` + Observers | Elimina riesgo de desync. La fecha actualizada se refleja en el siguiente GET sin lógica extra |
| D3 | **No usar Observers** para automatización de alertas | `LegalDocumentObserver` que sincroniza tabla espejo | El `CaseObserver` ya está vacío por diseño — el codebase prioriza la capa de servicio explícita |
| D4 | Quick Sync es **acción explícita del usuario** (botón) | Evento auto-creado al guardar documento | El consultor decide cuándo y si pushear al calendario — evita ruido de eventos automáticos |
| D5 | **Migración gradual en 3 fases**: crear tabla → migrar datos → drop columnas planas | Drop inmediato de columnas planas | Reduce riesgo de regresión en datos existentes; permite rollback en cada fase |
| D6 | Índice compuesto `(tenant_id, alert_active, expiry_date)` sin cache Redis | Cache Redis sobre query de alertas | Innecesario a escala actual (~2,100 filas/tenant). Añadir Redis si supera 50k documentos |
| D7 | `document_type` como string con catálogo predefinido + opción `'other'` libre | ENUM en DB | ENUM requiere migración para añadir tipos; string permite extensibilidad sin downtime |

---

## 3. Arquitectura de Datos

### 3.1 Nueva Tabla `legal_documents`

```sql
CREATE TABLE legal_documents (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    documentable_type   VARCHAR(255) NOT NULL,   -- 'App\Models\Client' | 'App\Models\Companion'
    documentable_id     BIGINT UNSIGNED NOT NULL,
    document_type       VARCHAR(60) NOT NULL,    -- 'studen permit' | 'work_permit' | ... | 'other'
    document_name       VARCHAR(150) NULL,        -- Nombre libre cuando type='other'
    document_number     VARCHAR(80) NULL,
    issue_date          DATE NULL,
    expiry_date         DATE NULL,
    alert_days_before   SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    alert_active        TINYINT(1) NOT NULL DEFAULT 1,
    notes               TEXT NULL,
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_expiry        (tenant_id, expiry_date),
    INDEX idx_documentable         (documentable_type, documentable_id),
    INDEX idx_tenant_alert_expiry  (tenant_id, alert_active, expiry_date)
);
```

### 3.2 Catálogo de Tipos de Documento

| `document_type` | ES | FR | EN |
|---|---|---|---|
| `work_permit` | Permiso de Trabajo | Permis de Travail | Work Permit |
| `study_permit` | Permiso de Estudio | Permis d'Étude | Study Permit |
| `visitor_record` | Registro de Visitante | Fiche de Visiteur | Visitor Record |
| `pr_card` | Tarjeta de Residente Permanente | Carte de Résident Permanent | PR Card |
| `caq` | CAQ (Quebec) | CAQ (Québec) | CAQ (Quebec) |
| `open_work_permit` | Permiso de Trabajo Abierto | Permis de Travail Ouvert | Open Work Permit |
| `eta` | eTA | eTA | eTA |
| `RPID` | Documento de Identidad del Solicitante de Protección de Refugiado | Document d'identité du demandeur d'asile | Refugee Protection Identity Document |
| `other` | Otro | Autre | Other |

### 3.3 Columnas Planas a Migrar y Eliminar (Fase 3)

**En `clients`:**
- `work_permit_number` → `legal_documents` (type: `work_permit`, document_number)
- `study_permit_number` → `legal_documents` (type: `study_permit`, document_number)
- `permit_expiry_date` → `legal_documents` (expiry_date del permiso migrado)


**En `companions`:**
- Mismos campos (`work_permit_number`, `study_permit_number`, `permit_expiry_date`)


### 3.4 Relaciones en Modelos Existentes

```php
// Client.php — añadir
public function legalDocuments(): MorphMany
{
    return $this->morphMany(LegalDocument::class, 'documentable')
                ->orderBy('sort_order');
}

// Companion.php — añadir
public function legalDocuments(): MorphMany
{
    return $this->morphMany(LegalDocument::class, 'documentable')
                ->orderBy('sort_order');
}
```

---

## 4. Modelo `LegalDocument`

```php
// app/Models/LegalDocument.php
protected $fillable = [
    'tenant_id', 'documentable_type', 'documentable_id',
    'document_type', 'document_name', 'document_number',
    'issue_date', 'expiry_date',
    'alert_days_before', 'alert_active',
    'notes', 'sort_order',
];

protected $casts = [
    'issue_date'        => 'date',
    'expiry_date'       => 'date',
    'alert_active'      => 'boolean',
    'alert_days_before' => 'integer',
    'sort_order'        => 'integer',
];

// Scopes
scopeExpiringSoon(Builder $q, ?int $days = null)   // Usa alert_days_before si $days = null
scopeOverdue(Builder $q)
scopeForTenant(Builder $q, int $tenantId)
scopeActive(Builder $q)                             // alert_active = true

// Accessors (sin persistir)
getDaysRemainingAttribute(): ?int    // DATEDIFF(expiry_date, today)
getAlertStatusAttribute(): string    // 'overdue' | 'critical' | 'warning' | 'ok' | 'none'
getDisplayNameAttribute(): string    // document_name si 'other', label del catálogo si no
```

---

## 5. API Endpoints

### 5.1 Documentos por Entidad

```
GET    /api/clients/{client}/documents              → LegalDocumentController@indexForClient
POST   /api/clients/{client}/documents              → LegalDocumentController@storeForClient
PUT    /api/clients/{client}/documents/{document}   → LegalDocumentController@update
DELETE /api/clients/{client}/documents/{document}   → LegalDocumentController@destroy
POST   /api/clients/{client}/documents/{document}/sync-event → LegalDocumentController@syncToCalendar

GET    /api/companions/{companion}/documents             → LegalDocumentController@indexForCompanion
POST   /api/companions/{companion}/documents             → LegalDocumentController@storeForCompanion
PUT    /api/companions/{companion}/documents/{document}  → LegalDocumentController@update
DELETE /api/companions/{companion}/documents/{document}  → LegalDocumentController@destroy
POST   /api/companions/{companion}/documents/{document}/sync-event → LegalDocumentController@syncToCalendar
```

> `update` y `destroy` son compartidos porque el `LegalDocument` tiene su propio `id` global. La autorización valida que `document.tenant_id === user.tenant_id`.

### 5.2 Módulo de Alertas

```
GET /api/expiration-alerts
```

**Query Parameters:**

| Param | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `status` | `overdue\|critical\|warning\|all` | `all` | Filtro por urgencia |
| `entity_type` | `client\|companion\|all` | `all` | Filtro por tipo de entidad |
| `sort` | `expiry_date\|entity_name\|document_type` | `expiry_date` | Ordenamiento |
| `direction` | `asc\|desc` | `asc` | Dirección del orden |
| `per_page` | integer | 25 | Paginación |
| `search` | string | — | Búsqueda en nombre entidad, número, tipo |

**Response shape:**
```json
{
  "data": [
    {
      "id": 1,
      "document_type": "work_permit",
      "display_name": "Permiso de Trabajo",
      "document_number": "WP-456789",
      "expiry_date": "2026-05-15",
      "days_remaining": 44,
      "alert_status": "critical",
      "entity_type": "client",
      "entity_id": 12,
      "entity_name": "Wilson Hernandez",
      "entity_url": "/clients/12"
    }
  ],
  "meta": { "total": 8, "per_page": 25, "current_page": 1 }
}
```

### 5.3 Dashboard — Campo adicional

El endpoint `GET /api/dashboard` incluirá un nuevo campo `expiring_documents` con los 10 más próximos a vencer (misma query de alertas, LIMIT 10).

---

## 6. Frontend Vue — Componentes Nuevos

### 6.1 `DocumentRepeater.vue`

**Ubicación:** `resources/js/src/components/DocumentRepeater.vue`

**Interface TypeScript:**
```typescript
interface LegalDocument {
    id?: number
    document_type: string           // Del catálogo o 'other'
    document_name: string | null    // Requerido si document_type === 'other'
    document_number: string | null
    issue_date: string | null       // YYYY-MM-DD
    expiry_date: string | null      // YYYY-MM-DD
    alert_days_before: number       // Default: 60
    alert_active: boolean
    sort_order: number
    // Solo lectura — calculados por la API:
    days_remaining?: number
    alert_status?: 'overdue' | 'critical' | 'warning' | 'ok' | 'none'
}
```

**Props:**
```typescript
modelValue: LegalDocument[]
entityType: 'client' | 'companion'
entityId: number
readonly?: boolean          // Default: false
```

**Emits:** `update:modelValue`

**UX por fila (modo edición):**
```
┌──────────────────────────────────────────────────────────────────────┐
│ [Tipo ▼]  [Nombre libre — visible sólo si tipo=Otro]                 │
│ [Número: ___________]  [Emisión: dd/mm/aaaa]  [Vence: dd/mm/aaaa]   │
│ [🔔 Alertar: 60 días antes]  [✓ Activo]  [Notas]  [🗑 Eliminar]     │
└──────────────────────────────────────────────────────────────────────┘
```

**Modo readonly:** Muestra los documentos en formato tabla con badge de `alert_status` junto a la fecha de vencimiento.

### 6.2 `ExpirationAlertBadge.vue`

**Ubicación:** `resources/js/src/components/ExpirationAlertBadge.vue`

Componente pequeño reutilizable que recibe `alertStatus` y `daysRemaining` y renderiza el badge con color y texto apropiados.

```typescript
// Paleta por estado
overdue:  'bg-red-100 text-red-700'       // 🔴 Vencido hace N días
critical: 'bg-orange-100 text-orange-700' // 🟠 En N días (< 60)
warning:  'bg-yellow-100 text-yellow-700' // 🟡 En N días (60-90)
ok:       'bg-green-100 text-green-700'   // 🟢 En N días (> 90)
none:     'bg-gray-100 text-gray-500'     // — Sin fecha
```

### 6.3 Vista `/expiration-alerts`

**Ubicación:** `resources/js/src/views/alerts/expiration-alerts.vue`

**Columnas de la tabla:**

| Columna | Descripción |
|---------|-------------|
| Nombre | Link a `/clients/{id}` o mención de acompañante |
| Implicado | Badge `Cliente` / `Acompañante` |
| Tipo de Documento | Nombre del tipo (localizado) |
| Número | Número del documento |
| Vence | Fecha formateada |
| Estado | `ExpirationAlertBadge` |
| Recordatorio | "En 20 días" / "Vencido hace 5 días" |
| Acciones | 📅 Sync al calendario |

**Filtros en barra superior:** Estado (Todos / Vencidos / Crítico / OK) · Tipo de Entidad · Búsqueda global

### 6.4 Widget Dashboard "Vencimientos Próximos"

Sección nueva en `resources/js/src/views/index.vue` — muestra los 5 documentos más próximos a vencer con `ExpirationAlertBadge` y link directo a la entidad.

### 6.5 Botón Quick Sync en `show.vue` / `edit.vue`

En la sección de documentos de clientes y en el formulario de acompañantes, cada fila con `expiry_date` muestra un botón de sincronización:

```vue
<button
    v-if="doc.expiry_date"
    type="button"
    class="btn btn-xs btn-outline-primary"
    :title="$t('documents.sync_to_calendar')"
    @click="syncDocumentToCalendar(doc)"
>
    <icon-calendar class="w-4 h-4" />
</button>
```

El handler llama a `POST /api/clients/{id}/documents/{docId}/sync-event` que crea un `Event` con:
- `title`: `"[Tipo Documento] – [Nombre Cliente]"`
- `start_date` / `end_date`: `expiry_date` del documento
- `category`: `'importante'`
- `all_day`: `true`
- `client_id`: id del cliente dueño

---

## 7. Migración de Datos — 3 Fases

### Fase 1 — Crear tabla (sin romper nada)
- Migration: `create_legal_documents_table`
- Columnas planas permanecen intactas

### Fase 2 — Migrar datos existentes
- Migration: `migrate_flat_permit_fields_to_legal_documents`
- Recorre todos los `clients` y `companions` con datos en columnas planas
- Inserta registros en `legal_documents` correspondientes
- Las columnas planas permanecen (`read-only` mientras se valida)

### Fase 3 — Limpieza ✅ Completada
- Migration: `drop_flat_permit_columns_from_clients_and_companions`
- Elimina: `passport_number`, `passport_issue_date`, `passport_country`, `passport_expiry_date`, `work_permit_number`, `study_permit_number`, `permit_expiry_date` de `clients` y `companions`
- **Nota:** Se decidió eliminar también los campos de pasaporte (no solo permisos), ya que todos los documentos se gestionan vía `legal_documents`
---

## 8. Análisis de Riesgos

### 8.1 Integridad Referencial

| Escenario | Comportamiento | Veredicto |
|-----------|---------------|-----------|
| Se elimina un `LegalDocument` | Desaparece automáticamente de todas las queries de alertas | ✅ Correcto — no hay tabla espejo que quede huérfana |
| Se elimina un `Client` con documentos | `cascadeOnDelete` en `tenant_id`; documentos se eliminan vía scope de tenant | ✅ Correcto |
| Usuario sincronizó al calendario y luego elimina el documento | El `Event` del calendario permanece (fue creado explícitamente por el usuario) | ✅ Intencional |
| `expiry_date` se actualiza en un documento ya sincronizado | El evento del calendario NO se actualiza automáticamente — el usuario debe re-sync | ✅ Explícito por diseño |

### 8.2 Duplicidad durante la Transición

Durante la Fase 2 (coexistencia), tanto las columnas planas como `legal_documents` contendrán datos.

**Regla estricta:** El endpoint `/expiration-alerts` y el widget del dashboard leen **exclusivamente** de `legal_documents`. Los formularios de edición de clientes también cambian a leer de `legal_documents`. Las columnas planas solo se mantienen para compatibilidad de la query de listado de clientes hasta la Fase 3.

### 8.3 Performance

```
Proyección por tenant:
  500 clientes × 3 docs = 1,500 registros
  300 acompañantes × 2 docs = 600 registros
  Total: ~2,100 filas

Query dashboard con índice (tenant_id, alert_active, expiry_date):
  Tiempo estimado: < 1ms

Query módulo de alertas paginado (25 filas):
  Tiempo estimado: < 3ms

Sin cache necesario hasta > 50,000 documentos por tenant.
```

---

## 9. Claves i18n Nuevas Requeridas

Prefijos a añadir en `es.json`, `en.json`, `fr.json`:

```
documents.title
documents.add_document
documents.no_documents
documents.document_type
documents.document_name
documents.document_number
documents.issue_date
documents.expiry_date
documents.alert_days_before
documents.alert_active
documents.sync_to_calendar
documents.sync_success
documents.sync_failed
documents.type_passport
documents.type_work_permit
documents.type_study_permit
documents.type_visitor_record
documents.type_pr_card
documents.type_caq
documents.type_open_work_permit
documents.type_eta
documents.type_other

alerts.title
alerts.expiring_soon
alerts.overdue
alerts.critical
alerts.warning
alerts.ok
alerts.no_alerts
alerts.entity_client
alerts.entity_companion
alerts.in_days          -- "En {n} días"
alerts.overdue_days     -- "Vencido hace {n} días"
alerts.expires_today    -- "Vence hoy"
alerts.days_before      -- "{n} días antes"
```

---

## 10. Checklist de Implementación

### Fase 1 — Base de Datos
- [x] Migration: `create_legal_documents_table` con índices compuestos
- [x] Model: `LegalDocument` (fillable, casts, scopes, accessors, morphTo)
- [x] Trait `HasLegalDocuments` con `legalDocuments()` en `Client` y `Companion`
- [x] Policy: `LegalDocumentPolicy` (tenant scoping, client/companion ownership)

### Fase 2 — Migración de Datos
- [x] Migration: `migrate_flat_permit_fields_to_legal_documents`
- [x] Verificar integridad post-migración (conteo de registros migrados)

### Fase 3 — Backend API
- [x] `LegalDocumentController` (indexForClient, indexForCompanion, storeForClient, storeForCompanion, update, destroy, syncToCalendar)
- [x] `ExpirationAlertController` (index con filtros, búsqueda, paginación, ordenamiento)
- [x] `LegalDocumentResource` (incluye `days_remaining`, `alert_status`, `display_name`)
- [x] `StoreLegalDocumentRequest` / `UpdateLegalDocumentRequest` con validación
- [x] `LegalDocumentService` (lógica de negocio: syncToCalendar, upsert, bulk)
- [x] Actualizar `DashboardController` → añadir `expiring_documents` (top 5)
- [x] Actualizar `CompanionResource` → incluir `legalDocuments` via `whenLoaded` (ClientResource no existe — controller devuelve modelo directo)
- [x] Registrar rutas nuevas en `api.php`

### Fase 4 — Frontend
- [x] TypeScript interface `LegalDocument` en `resources/js/src/types/`
- [x] `DocumentRepeater.vue` con add/remove/reorder y modo readonly
- [x] `ExpirationAlertBadge.vue` (badge reutilizable por estado)
- [x] Vista `resources/js/src/views/alerts/expiration-alerts.vue` (tabla + filtros)
- [x] Widget "Vencimientos Próximos" en `views/index.vue` (dashboard)
- [x] Integrar `DocumentRepeater` en `clients/show.vue` (tab "Documentos Legales")
- [x] Integrar `DocumentRepeater` en `clients/edit.vue`
- [x] Integrar `DocumentRepeater` en `clients/create.vue` (docs guardados post-creación con el ID retornado)
- [x] Integrar `DocumentRepeater` en el formulario de acompañantes dentro de `clients/show.vue` (carga docs al abrir modal de edición; guarda docs al hacer submit)
- [x] Botones Quick Sync en `DocumentRepeater` (modo readonly y edición)
- [x] Ruta `/expiration-alerts` en `router/index.ts`
- [x] Entrada en sidebar (`Sidebar.vue`) bajo sección de navegación principal
- [x] Servicio API `legalDocumentService.ts` (CRUD + syncToCalendar)

### Fase 5 — i18n
- [x] Añadir keys `documents.*` en `es.json`, `en.json`, `fr.json`
- [x] Añadir keys `alerts.*` en `es.json`, `en.json`, `fr.json`

### Fase 6 — Limpieza
- [x] Migration: `drop_flat_permit_columns_from_clients_and_companions` — ejecutada ✅
- [x] Eliminar campos obsoletos de `$fillable` y `$casts` en `Client` y `Companion`
- [x] Eliminar campos obsoletos de `StoreClientRequest`, `UpdateClientRequest`, `StoreCompanionRequest`, `UpdateCompanionRequest`
- [x] Eliminar campos obsoletos de `types/client.ts` y `types/companion.ts`
- [x] Limpiar formulario `clients/create.vue` (contact + canada sections)
- [x] Limpiar formulario `clients/edit.vue` (contact + canada sections)
- [x] Limpiar tab Canada en `clients/show.vue` (solo status, entry_point, arrival_date, iuc)
- [x] Limpiar tarjeta de acompañante en `clients/show.vue` (solo iuc)
- [x] Limpiar modal de acompañante (campos passport_* y permit_* eliminados)

---

## 11. Diagrama de Flujo — Quick Sync

```
Usuario ve documento con expiry_date en show.vue
          │
          ▼
    Clic en [📅 Sync]
          │
          ▼
POST /api/clients/{id}/documents/{docId}/sync-event
          │
          ▼
LegalDocumentService::syncToCalendar()
    → Crea Event con:
        title = "[Tipo] – [Nombre Cliente]"
        start_date = expiry_date
        end_date = expiry_date
        all_day = true
        category = 'importante'
        client_id = client.id
          │
          ▼
    Notificación toast: "Evento creado en el calendario"
```

---

## 12. Diagrama de Flujo — Módulo de Alertas

```
GET /api/expiration-alerts
          │
          ▼
ExpirationAlertController::index()
          │
          ▼
LegalDocument::query()
    ->where('tenant_id', $tenantId)
    ->where('alert_active', true)
    ->whereNotNull('expiry_date')
    ->whereRaw('expiry_date <= DATE_ADD(CURDATE(), INTERVAL alert_days_before DAY)')
    ->with(['documentable:id,first_name,last_name'])
    ->orderBy('expiry_date')
    ->paginate($perPage)
          │
          ▼
LegalDocumentResource (con days_remaining, alert_status calculados)
          │
          ▼
Frontend: Tabla con ExpirationAlertBadge por fila
```
