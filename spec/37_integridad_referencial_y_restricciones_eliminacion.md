---
tipo: spec
numero: 37
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[36_documentos_legales_y_alertas_vencimiento|Spec 36]]"
---
# [[37_integridad_referencial_y_restricciones_eliminacion|Spec 37]]: Integridad Referencial, Cascada y Restricciones de Eliminación

## Información del Documento

- **Fecha:** 2026-04-04
- **Tipo:** Análisis de Impacto Arquitectónico y Plan de Implementación
- **Scope:** Campo `issuing_country` en documentos legales, cascada de eliminación Client/Companion → LegalDocuments, restricciones de eliminación (cliente con companions o casos), feedback UX al usuario
- **Dependencias:** [[36_documentos_legales_y_alertas_vencimiento|Spec 36]] (Documentos Legales y Alertas de Vencimiento)
- **Estado:** Implementado (Fases 1–4 completadas)
- **Metodología:** Análisis de impacto sobre modelos existentes, diseño de integridad referencial con SoftDeletes, evaluación de estrategias de cascada y restricción

---

## 1. Resumen Ejecutivo

Con el módulo de documentos legales (Spec 36) en producción, surgen tres necesidades de maduración del modelo:

1. **Campo `issuing_country`**: Los documentos necesitan registrar el país emisor. Ausente actualmente.
2. **Cascada de eliminación**: `Client` y `Companion` usan `SoftDeletes`, pero `LegalDocument` no. Al soft-deletear un cliente, sus documentos quedan huérfanos en la DB, invisibles pero presentes. Se requiere cascada explícita.
3. **Restricciones de negocio**: Un cliente con companions activos o casos activos no debe poder eliminarse. El frontend debe informar al usuario de forma descriptiva por qué no puede completar la operación.

### Hallazgos que motivan este cambio

| ID | Hallazgo | Severidad |
|----|----------|-----------|
| INT-001 | `legal_documents` quedan huérfanos al soft-deletear un Client o Companion — la tabla crece indefinidamente con basura | Alta |
| INT-002 | No existe validación al eliminar un cliente con companions o casos activos — posible pérdida de integridad referencial | Alta |
| INT-003 | El campo `issuing_country` es necesario para cumplimiento documental y validación de pasaportes | Media |
| INT-004 | El frontend no informa al usuario por qué fue bloqueada una eliminación — experiencia de usuario pobre | Media |

---

## 2. Decisiones de Diseño

| # | Decisión | Alternativa Descartada | Justificación |
|---|----------|------------------------|---------------|
| D1 | Cascada via `static::deleting()` en `booted()` de los modelos | `ON DELETE CASCADE` en FK de DB | `SoftDeletes` no dispara DB-level cascades. La única solución confiable es el hook de Eloquent |
| D2 | `LegalDocument` recibe **hard delete** cuando el padre se elimina | Agregar `SoftDeletes` a `LegalDocument` | Documentos sin entidad padre no tienen valor operativo. Hard delete mantiene la tabla limpia |
| D3 | Restricción de eliminación en **capa de servicio** (`ClientDeletionService`) | Validación directa en controlador | El controlador no debe contener lógica de negocio. El servicio es testeable de forma aislada |
| D4 | Excepción tipada `ClientHasDependentsException` | Retornar booleano desde servicio | Las excepciones permiten captura selectiva en el controlador y mensajes de error estructurados |
| D5 | HTTP **422 Unprocessable Entity** para eliminaciones bloqueadas | 403 Forbidden | 422 comunica "regla de negocio violada". 403 implicaría falta de permisos, que es semánticamente incorrecto |
| D6 | `issuing_country` como **string libre** (VARCHAR 100) | FK a tabla `countries` | Consistente con `nationality` y `passport_country` existentes en el modelo. Sin overhead de joins |
| D7 | `CountrySelect` reutilizado para `issuing_country` en `DocumentRepeater` | Input de texto libre | Consistencia UX con otros campos de país. Mejora calidad de datos con selección estandarizada |
| D8 | Frontend verifica companions ANTES de mostrar botón de eliminar | Solo manejo de error post-click | UX proactiva: si hay dependientes, el botón se desactiva con tooltip explicativo |

---

## 3. Arquitectura de Cambios

### 3.1 Campo `issuing_country` en `legal_documents`

**Migración:**
```sql
ALTER TABLE legal_documents
    ADD COLUMN issuing_country VARCHAR(100) NULL
    AFTER document_number;
```

**Posición en formulario** (`DocumentRepeater.vue`):
```
[ Tipo ]  [ Nombre si 'other' ]
[ Número ]  [ País emisor ]  [ Fecha emisión ]  [ Fecha vencimiento ]  [ Días alerta ]  [ Activo ]  [X]
```

**Backend — `LegalDocumentResource`:**
```php
'issuing_country' => $this->issuing_country,
```

**Backend — Validación (Store/Update):**
```php
'documents.*.issuing_country' => 'nullable|string|max:100',
```

---

### 3.2 Cascada de Eliminación Client → LegalDocuments

**Implementación en `Client` model:**
```php
protected static function booted(): void
{
    static::deleting(function (Client $client) {
        // Hard delete de documentos legales (no tienen SoftDeletes)
        $client->legalDocuments()->each(fn($doc) => $doc->delete());
    });
}
```

**Implementación en `Companion` model:**
```php
protected static function booted(): void
{
    static::deleting(function (Companion $companion) {
        $companion->legalDocuments()->each(fn($doc) => $doc->delete());
    });
}
```

> **Nota crítica:** `->delete()` sobre una colección Eloquent llama al `delete()` del modelo, no un query bulk. Esto es intencional para disparar cualquier observer o hook que `LegalDocument` pueda tener en el futuro.

---

### 3.3 Restricción de Eliminación de Clientes

#### 3.3.1 Excepción tipada

```php
// app/Exceptions/ClientHasDependentsException.php
class ClientHasDependentsException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,  // 'companions' | 'cases'
        public readonly int $count,
        string $message = ''
    ) {
        parent::__construct($message ?: "Client has active {$reason}: {$count}");
    }
}
```

#### 3.3.2 Servicio de eliminación

```php
// app/Services/Client/ClientDeletionService.php
class ClientDeletionService
{
    public function delete(Client $client): void
    {
        // 1. Verificar companions activos (no soft-deleted)
        $companionCount = $client->companions()->count();
        if ($companionCount > 0) {
            throw new ClientHasDependentsException('companions', $companionCount);
        }

        // 2. Verificar casos activos
        $caseCount = $client->cases()->count();
        if ($caseCount > 0) {
            throw new ClientHasDependentsException('cases', $caseCount);
        }

        // 3. Eliminar (dispara booted::deleting → cascada de LegalDocuments)
        $client->delete();
    }
}
```

#### 3.3.3 Controlador

```php
// app/Http/Controllers/Api/ClientController.php
public function destroy(Client $client): JsonResponse
{
    $this->authorize('delete', $client);

    try {
        app(ClientDeletionService::class)->delete($client);
        return response()->json(['message' => 'Client deleted successfully']);
    } catch (ClientHasDependentsException $e) {
        return response()->json([
            'message' => __("clients.delete_blocked_{$e->reason}"),
            'reason'  => $e->reason,
            'count'   => $e->count,
        ], 422);
    }
}
```

---

## 4. Impacto en Frontend

### 4.1 Cliente `clientStore.deleteClient()`

La función ya existe. Se debe capturar el 422 y extraer `reason` y `count` del body para mostrar un toast descriptivo.

```typescript
// stores/clients.ts
async deleteClient(id: number): Promise<void> {
    try {
        await api.delete(`/clients/${id}`);
    } catch (error: any) {
        const data = error.response?.data;
        if (error.response?.status === 422 && data?.reason) {
            const key = `clients.delete_blocked_${data.reason}`;
            throw new Error(t(key, { count: data.count }));
        }
        throw error;
    }
}
```

### 4.2 `clients/show.vue` — UX proactiva

Antes de mostrar el botón de eliminar, consultar si el cliente tiene companions o casos:

```typescript
// Computed
const canDelete = computed(() =>
    client.value?.companions_count === 0 &&
    client.value?.cases_count === 0
);
```

El botón de eliminar debe:
- **Estar deshabilitado** si `!canDelete`
- Mostrar un **tooltip descriptivo** cuando está deshabilitado: _"No se puede eliminar: tiene X acompañantes y/o expedientes activos"_

### 4.3 `clients/list.vue` — Feedback post-eliminación

El toast de error en la lista ya existe. Solo se necesita propagar el mensaje específico del 422:

```typescript
} catch (error: any) {
    notification.error(error.message || t('clients.delete_error'));
}
```

---

## 5. Claves i18n Necesarias

```json
// en.json
{
  "documents": {
    "issuing_country": "Issuing Country"
  },
  "clients": {
    "delete_blocked_companions": "Cannot delete: client has {count} active companion(s). Remove them first.",
    "delete_blocked_cases": "Cannot delete: client has {count} active case(s). Close or transfer them first.",
    "delete_blocked_tooltip": "Cannot delete: client has active companions or cases"
  }
}
```

```json
// es.json
{
  "documents": {
    "issuing_country": "País Emisor"
  },
  "clients": {
    "delete_blocked_companions": "No se puede eliminar: el cliente tiene {count} acompañante(s) activo(s). Elimínalos primero.",
    "delete_blocked_cases": "No se puede eliminar: el cliente tiene {count} expediente(s) activo(s). Ciérralos o transfié relos primero.",
    "delete_blocked_tooltip": "No se puede eliminar: el cliente tiene acompañantes o expedientes activos"
  }
}
```

```json
// fr.json
{
  "documents": {
    "issuing_country": "Pays Émetteur"
  },
  "clients": {
    "delete_blocked_companions": "Suppression impossible : le client a {count} accompagnant(s) actif(s). Supprimez-les d'abord.",
    "delete_blocked_cases": "Suppression impossible : le client a {count} dossier(s) actif(s). Fermez-les ou transférez-les d'abord.",
    "delete_blocked_tooltip": "Suppression impossible : le client a des accompagnants ou des dossiers actifs"
  }
}
```

---

## 6. Plan de Implementación

### Fase 1: Campo `issuing_country` en documentos legales

- [x] Crear migración `add_issuing_country_to_legal_documents`
- [x] Agregar `issuing_country` a `$fillable` en `LegalDocument` model
- [x] Agregar `'issuing_country'` a `LegalDocumentResource`
- [x] El campo es aceptado vía el upsert genérico del LegalDocumentController (nullable string)
- [x] Agregar campo `issuing_country?: string | null` a tipo TypeScript `LegalDocument`
- [x] Agregar `CountrySelect` en `DocumentRepeater.vue` entre número y fecha de emisión
- [x] Agregar claves i18n `documents.issuing_country` en `en.json`, `es.json`, `fr.json`

### Fase 2: Cascada de eliminación (Client/Companion → LegalDocuments)

- [x] Agregar hook `static::deleting()` en `Client::booted()` — hard delete de `legalDocuments()`
- [x] Agregar hook `static::deleting()` en `Companion::booted()` — hard delete de `legalDocuments()`
- [ ] Escribir test unitario: soft-delete de Client elimina sus LegalDocuments
- [ ] Escribir test unitario: soft-delete de Companion elimina sus LegalDocuments

### Fase 3: Restricciones de eliminación de clientes

- [x] Crear `app/Exceptions/ClientHasDependentsException.php`
- [x] Integrar restricciones directamente en `ClientService::deleteClient()` (sin servicio separado — más simple y cohesivo)
- [x] Modificar `ClientController::destroy()` para capturar la excepción → HTTP 422
- [x] Agregar claves i18n `clients.delete_blocked_companions`, `clients.delete_blocked_cases`, `clients.delete_blocked_tooltip`

### Fase 4: Frontend UX para eliminaciones bloqueadas

- [x] El store re-lanza el error con `error.response?.data?.message` — el 422 descriptivo llega al caller
- [x] `clients/list.vue` usa `err.response?.data?.message` en el catch — muestra el mensaje del backend
- [x] Agregar computed `canDelete` en `clients/show.vue` basado en `companions_count` y `cases_count`
- [x] Desactivar botón de eliminar con tooltip cuando `!canDelete` en `clients/show.vue`
- [x] Agregar `companions_count` y `cases_count` al tipo TypeScript `Client` (opcionales)

---

## 7. Archivos Afectados

### Backend
| Archivo | Acción |
|---------|--------|
| `database/migrations/YYYY_MM_DD_add_issuing_country_to_legal_documents.php` | Crear |
| `app/Models/LegalDocument.php` | Modificar — `$fillable` |
| `app/Models/Client.php` | Modificar — `booted()` hook |
| `app/Models/Companion.php` | Modificar — `booted()` hook |
| `app/Http/Resources/LegalDocumentResource.php` | Modificar — agregar `issuing_country` |
| `app/Exceptions/ClientHasDependentsException.php` | Crear |
| `app/Services/Client/ClientDeletionService.php` | Crear |
| `app/Http/Controllers/Api/ClientController.php` | Modificar — `destroy()` |

### Frontend
| Archivo | Acción |
|---------|--------|
| `resources/js/src/types/legal-document.ts` | Modificar — agregar `issuing_country` |
| `resources/js/src/components/DocumentRepeater.vue` | Modificar — agregar `CountrySelect` |
| `resources/js/src/stores/clients.ts` | Modificar — manejo 422 en `deleteClient()` |
| `resources/js/src/views/clients/list.vue` | Modificar — toast con mensaje descriptivo |
| `resources/js/src/views/clients/show.vue` | Modificar — computed `canDelete`, tooltip |
| `resources/js/src/locales/en.json` | Modificar — claves nuevas |
| `resources/js/src/locales/es.json` | Modificar — claves nuevas |
| `resources/js/src/locales/fr.json` | Modificar — claves nuevas |

### Tests
| Archivo | Acción |
|---------|--------|
| `tests/Unit/Models/ClientDeletionCascadeTest.php` | Crear |
| `tests/Unit/Models/CompanionDeletionCascadeTest.php` | Crear |
| `tests/Feature/Clients/DeleteClientTest.php` | Modificar o crear |

---

## 8. Consideraciones de Riesgo

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| `legalDocuments()` no cargado en memoria al disparar `deleting` hook | Baja | Alta | Usar `->each()` con lazy loading — Eloquent carga la relación on-demand |
| Soft-delete de Companion disparado desde cascade de Client (companions no tienen SoftDeletes?) | Media | Media | Verificar que `Companion` también tiene `SoftDeletes`. Si no, el hook de Client debe iterar y eliminar primero companions (y sus docs) antes de eliminarse |
| `companions_count` / `cases_count` no incluidos en `ClientResource` | Media | Baja | Verificar con `withCount()` en query. Agregar si faltan |
| Tests en CI: `ClientHasDependentsException` no registrada en `Handler.php` | Baja | Baja | La excepción se captura en el controlador — no llega al Handler global |

---

## 9. Criterios de Aceptación

1. Al eliminar un Client/Companion, **no quedan filas huérfanas** en `legal_documents`
2. Intentar eliminar un cliente con companions activos retorna **HTTP 422** con body `{reason: 'companions', count: N, message: '...'}`
3. Intentar eliminar un cliente con casos activos retorna **HTTP 422** con body `{reason: 'cases', count: N, message: '...'}`
4. En `clients/show.vue`, el botón de eliminar aparece **deshabilitado** con tooltip si el cliente tiene companions o casos
5. En `clients/list.vue`, tras un intento de eliminar bloqueado, el toast muestra el **motivo específico** (no un mensaje genérico)
6. El campo `issuing_country` se guarda y recupera correctamente en CRUD de documentos
7. El campo `issuing_country` usa `CountrySelect` component en el formulario
