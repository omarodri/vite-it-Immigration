---
tipo: spec
numero: 40
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[36_documentos_legales_y_alertas_vencimiento|Spec 36]]"
  - "[[37_integridad_referencial_y_restricciones_eliminacion|Spec 37]]"
---
# [[40_trash_papelera_reciclaje|Spec 40]] — Sistema de Papelera de Reciclaje (SoftDeletes + Trash Module)

## Contexto

Actualmente los registros eliminados desaparecen de la base de datos de forma permanente (hard delete) o están parcialmente protegidos por bloqueos (`ClientService` lanza excepción si el cliente tiene companions o cases). Se requiere un sistema de eliminación lógica que permita recuperar registros antes de borrarlos definitivamente.

## Objetivo

Implementar una Papelera de Reciclaje completa que:
1. Mueva clientes, expedientes, acompañantes y documentos a un estado "eliminado lógicamente" (soft delete).
2. Propague la eliminación en cascada a entidades hijas mediante Model Observers.
3. Permita restaurar registros (con validación de integridad referencial) o eliminarlos definitivamente.
4. Exponga una vista dedicada `/trash` con tabs por tipo de entidad.
5. Incluya purga automática configurable (30 días por defecto).

---

## Estado Actual — Hallazgos Clave

### Modelos con SoftDeletes (ya implementado)
| Modelo | Tabla | `SoftDeletes` | `deleted_at` en migración |
|--------|-------|:---:|:---:|
| Client | `clients` | ✅ | ✅ |
| [[ImmigrationCase]] | `cases` | ✅ | ✅ |
| Companion | `companions` | ✅ | ✅ |
| Document | `documents` | ✅ | ✅ |
| Event | `events` | ✅ | ✅ |

### Modelos SIN SoftDeletes (requieren trabajo)
| Modelo | Tabla | Estado | Acción |
|--------|-------|--------|--------|
| [[LegalDocument]] | `legal_documents` | ❌ Sin SoftDeletes | Agregar migración + trait |
| Todo | `todos` | usa `status='trash'` propio | Mantener mecanismo propio, integrar a la UI de papelera |

### Problemas críticos identificados
- `ClientService::deleteClient()` **bloquea** el borrado si hay companions o cases → debe cambiar.
- La cascade de la DB (`cascadeOnDelete` en FK) solo aplica a hard delete, **no** a soft delete. La cascada lógica debe implementarse en Observers.
- Los `unique` constraints de `clients.email` y `clients.phone` no excluyen soft-deleted → conflictos de unicidad.
- `Client::booted()` y `Companion::booted()` tienen lógica de cascade manual → mover a Observers.

---

## Jerarquía de Eliminación

```
Client → trash
  ├── Companion(s) → trash (Observer: ClientObserver.deleting)
  │     └── LegalDocument(s) → trash (Observer: CompanionObserver.deleting)
  ├── ImmigrationCase(s) → trash (Observer: ClientObserver.deleting)
  │     └── Document(s) → trash (Observer: ImmigrationCaseObserver.deleting)
  └── LegalDocument(s) directos → trash (Observer: ClientObserver.deleting)

Events  → NO se tocan (nullable client_id, existen independientemente)
Todos   → NO se tocan (pertenecen al usuario/tenant)
CaseTask, CaseImportantDate, CaseInvoice, DocumentFolder → NO SoftDeletes (datos internos del caso)
```

---

## Estrategia de Restauración

**Regla:** Bloquear restauración si el padre está en papelera + ofrecer restauración en cadena ascendente.

| Escenario | Comportamiento |
|-----------|----------------|
| Restaurar Client | Siempre permitido. Restaura companions e hijos borrados en cascada. |
| Restaurar Case cuyo Client está en trash | Bloqueado. Ofrecer "Restaurar cliente y expediente". |
| Restaurar Companion cuyo Client está en trash | Bloqueado. Ofrecer "Restaurar cliente y acompañante". |
| Restaurar caso con email/phone ya en uso | Bloqueado con mensaje de conflicto. |

---

## Conflictos de Unicidad

**Problema:** `Rule::unique('clients')` falla al crear con email de un soft-deleted.

**Solución multicapa:**
1. **Request classes:** Agregar `->whereNull('deleted_at')` a todas las reglas `unique`.
2. **Índices DB:** Cambiar `unique(['tenant_id', 'email'])` → `unique(['tenant_id', 'email', 'deleted_at'])`.
3. **Pre-restore:** Verificar que email/phone no esté en uso antes de restaurar.

---

## API Design

```
GET    /api/trash                               → Resumen de contadores por tipo
GET    /api/trash/{type}?page=&search=          → Listar items (clients|cases|companions|documents)
PATCH  /api/trash/{type}/{id}/restore           → Restaurar (falla si padre en trash)
PATCH  /api/trash/{type}/{id}/restore-with-parents → Restaurar + cadena ascendente
DELETE /api/trash/{type}/{id}                   → Hard delete definitivo
DELETE /api/trash/purge                         → Vaciar papelera (con filtro de días)
```

### Nuevos permisos
| Permiso | Roles por defecto |
|---------|-------------------|
| `trash.view` | admin, consultant |
| `trash.restore` | admin, consultant |
| `trash.force-delete` | admin |
| `trash.purge` | admin |

---

## Diseño Frontend

**Vista dedicada `/trash`** (no filtro en el listado existente).

```
views/trash/
  TrashView.vue          ← Tabs: Clientes | Expedientes | Acompañantes | Documentos
stores/
  trash.ts               ← Store Pinia dedicado
services/
  trashService.ts        ← Llamadas API
types/
  trash.ts               ← TrashItem, TrashSummary, TrashableType
```

---

## Riesgos

| Nivel | Riesgo | Mitigación |
|-------|--------|------------|
| 🔴 CRÍTICO | Restaurar hijo cuyo padre está en trash → huérfano | Bloquear + endpoint `restore-with-parents` |
| 🔴 CRÍTICO | Unicidad email/phone con soft-deleted | Índices compuestos con `deleted_at` + `whereNull` en Requests |
| 🔴 CRÍTICO | SoftDelete no propaga cascada DB | Observers con cascada lógica explícita |
| 🟡 MEDIO | Conflicto unicidad al restaurar | Validación pre-restore en [[TrashService]] |
| 🟡 MEDIO | `ClientService` bloquea borrado por dependientes | Cambiar lógica del servicio |
| 🟢 BAJO | Crecimiento DB por filas soft-deleted | Purga automática diaria a 30 días |
| 🟢 BAJO | `LegalDocument` sin SoftDeletes | Migración + agregar trait |

---

## Checklist de Implementación

### Fase 1 — Backend: Persistencia y Modelos

- [x] Migración: añadir `deleted_at` (softDeletes) a tabla `legal_documents`
- [x] Migración: cambiar unique index `clients.(tenant_id, email)` → `(tenant_id, email, deleted_at)`
- [x] Migración: cambiar unique index `clients.(tenant_id, phone)` → `(tenant_id, phone, deleted_at)`
- [x] Migración: cambiar unique index `cases.case_number` → `(case_number, deleted_at)`
- [x] Migración: agregar índice `(tenant_id, deleted_at)` a `clients`, `cases`, `companions`, `documents`, `legal_documents`
- [x] Modelo `LegalDocument`: agregar `use SoftDeletes`
- [x] Crear `app/Observers/ClientObserver.php` con lógica `deleting` y `restoring`
- [x] Crear `app/Observers/CompanionObserver.php` con lógica `deleting` y `restoring` para LegalDocuments
- [x] Crear `app/Observers/ImmigrationCaseObserver.php` con lógica `deleting` y `restoring` para Documents
- [x] Registrar los 3 observers en `AppServiceProvider::boot()`
- [x] Eliminar lógica de cascade de `Client::booted()` (movida al Observer)
- [x] Eliminar lógica de cascade de `Companion::booted()` (movida al Observer)

### Fase 2 — Backend: Service Layer y Excepciones

- [x] Crear excepción `app/Exceptions/Trash/ParentInTrashException.php`
- [x] Crear excepción `app/Exceptions/Trash/RestoreConflictException.php`
- [x] Crear `app/Services/Trash/TrashService.php` con métodos: `summary()`, `listByType()`, `restore()`, `restoreWithParents()`, `forceDelete()`, `purge()`
- [x] Modificar `ClientService::deleteClient()` — eliminar bloqueo por dependientes, retornar info de cascada
- [x] Modificar `StoreClientRequest` — agregar `->whereNull('deleted_at')` a reglas `unique` de `email` y `phone`
- [x] Modificar `UpdateClientRequest` — agregar `->whereNull('deleted_at')` a reglas `unique` de `email` y `phone`

### Fase 3 — Backend: API y Controlador

- [x] Crear `app/Http/Controllers/Api/TrashController.php` con 6 métodos
- [x] Agregar rutas en `routes/api.php` bajo el grupo autenticado (resourceful bajo `/trash`)
- [x] Agregar permisos `trash.view`, `trash.restore`, `trash.force-delete`, `trash.purge` al seeder de permisos
- [x] Crear `app/Console/Commands/PurgeTrashCommand.php` (signature: `trash:purge --days=30`)
- [x] Registrar schedule diario `trash:purge --days=30` a las 03:00 AM en `routes/console.php`

### Fase 4 — Frontend: Tipos, Store y Service

- [x] Crear `resources/js/src/types/trash.ts` con interfaces: `TrashableType`, `TrashItem`, `TrashSummary`
- [x] Crear `resources/js/src/services/trashService.ts` con llamadas a los 6 endpoints
- [x] Crear `resources/js/src/stores/trash.ts` (Pinia store con state, getters, actions)

### Fase 5 — Frontend: Vista y UX

- [x] Crear `resources/js/src/views/trash/TrashView.vue` con tabs por tipo + listado paginado
- [x] Agregar ruta `/trash` en `resources/js/src/router/index.ts` con meta `permission: 'trash.view'`
- [x] Agregar entrada "Papelera" con badge de conteo en `resources/js/src/components/layout/Sidebar.vue`
- [x] Implementar diálogo de confirmación al restaurar (con y sin padre en trash)
- [x] Implementar diálogo de confirmación para eliminar definitivamente (con input de texto de confirmación)
- [x] Modificar diálogo de eliminación en listado de clientes para informar "mover a papelera + N items en cascada"
- [x] Modificar diálogo de eliminación en listado de expedientes para informar sobre la papelera
- [x] Agregar i18n keys `trash.*` en `locales/es.json`, `locales/en.json`, `locales/fr.json`

---

## Dependencias entre Fases

```
Fase 1 (Persistencia)
    │
    ▼
Fase 2 (Services) ──────────────────────────┐
    │                                        │
    ▼                                        │
Fase 3 (API + Controlador) ─────────────────┤
    │                                        │
    ▼                                        ▼
Fase 4 (Frontend Base) ──────────────► Fase 5 (Vista y UX)
```

Las fases 1 y 2 son estrictamente secuenciales. Las fases 3 y 4 pueden paralelizarse una vez Fase 2 esté completa. La Fase 5 depende de las fases 3 y 4.
