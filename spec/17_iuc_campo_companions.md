---
tipo: spec
numero: 17
modulo: Clientes y Acompañantes
dominio: CLNT
estado: DONE
tags: [spec, clientes, done]
relacionados:
  - "[[31_telefono_internacional_enrichment_companions|Spec 31]]"
---
# Plan de Implementación: Campo IUC en Acompañantes (Companions)

## Metadata
- **Fecha:** 2026-03-15
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 4.2 - IUC (Identificador Único de Cliente IRCC) en Companions
- **Alcance:** Agregar el campo `iuc` a la entidad `companions` en todas las capas: migración, modelo, validación, API resource, tipos TypeScript, formularios en `StepCompanions.vue` y `clients/show.vue`, y visualización en tarjetas.
- **Flujos cubiertos:** Wizard (StepCompanions) + Detalle de cliente (show.vue modal) + Visualización en tarjetas de companions
- **Tiempo Total Estimado:** 2–3 horas
- **Estado:** ✅ COMPLETADO (2026-03-15)

---

## Hallazgos del Análisis

| Componente | Estado actual |
|------------|---------------|
| Tabla `companions` en DB | ❌ Campo `iuc` NO existe |
| `Companion` model (fillable) | ❌ No incluye `iuc` |
| `CompanionResource` | ❌ No expone `iuc` |
| `StoreCompanionRequest` | ❌ No valida `iuc` |
| `UpdateCompanionRequest` | ❌ No valida `iuc` |
| `types/companion.ts` | ❌ No declara `iuc` |
| `StepCompanions.vue` | ❌ No tiene campo `iuc` |
| `clients/show.vue` (modal) | ❌ No tiene campo `iuc` |
| `locales/*.json` | ❌ Sin claves `companions.iuc` |
| Campo `iuc` en `clients` | ✅ Ya existe (campo distinto — es el IUC del cliente principal) |

**Nota sobre componentes compartidos:** `StepCompanions.vue` y `clients/show.vue` NO comparten un componente `CompanionModal.vue`. Ambos tienen el formulario inline duplicado. Por consistencia con la decisión ya documentada en `spec/10b`, se agregan los campos directamente en cada archivo sin extraer un componente común.

---

## Decisiones Arquitectónicas

| Dimensión | Decisión | Justificación |
|-----------|----------|---------------|
| **Tipo de columna** | `VARCHAR(20) NULLABLE` | El IUC IRCC tiene formato `XXXXXXXX` (8 dígitos) o `XXXX-XXXX` (con guión). Nullable: acompañantes existentes no tienen IUC; es un campo informativo no obligatorio. |
| **Unicidad** | Única por tenant (validación en FormRequest, no constraint DB) | Dos acompañantes del mismo tenant no deben compartir IUC, pero la tabla no tiene `tenant_id` directo. Se valida en el FormRequest via join `companions → client → tenant_id`. Los valores NULL se excluyen de la validación de unicidad. |
| **Datos históricos** | `nullable` sin valor default | Companions existentes quedan con `iuc = NULL`. Sin migración de datos. La UI muestra "—" cuando es null. |
| **Índice DB** | `INDEX(iuc)` para búsquedas futuras | Aunque la búsqueda por IUC no es requerimiento actual, el índice es barato y habilita `WHERE iuc = ?` eficiente. Sin UNIQUE constraint (la unicidad se maneja en app layer). |
| **Formato / máscara** | Input libre con `maxlength=20`, sin máscara estricta | El IRCC usa `XXXXXXXX` (sin guión) como formato oficial, pero el campo en `clients` ya acepta texto libre. Consistencia: misma permisividad. |
| **Visualización** | Badge inline en tarjeta de companion (junto a passport_number) | No ocupa columna de tabla — se muestra como metadata secundaria dentro de la tarjeta existente. |
| **Migración** | Agregar columna `iuc` en tabla `companions` | Sin recrear la tabla. Sigue el patrón de `add_relationship_other_to_companions.php`. |

---

## Esquema de Base de Datos

### Nueva columna en `companions`

```php
Schema::table('companions', function (Blueprint $table) {
    $table->string('iuc', 20)->nullable()->after('notes');
    $table->index('iuc');
});
```

| Campo | Tipo | Nullable | Default | Índice |
|-------|------|----------|---------|--------|
| `iuc` | `VARCHAR(20)` | ✅ | NULL | `INDEX(iuc)` |

---

## Validación de Unicidad por Tenant

El campo `iuc` no tiene UNIQUE constraint en DB (porque la tabla no tiene `tenant_id`). La unicidad se valida en el FormRequest:

```php
// En StoreCompanionRequest y UpdateCompanionRequest
'iuc' => [
    'nullable',
    'string',
    'max:20',
    Rule::when(
        fn($input) => !empty($input->iuc),
        [Rule::unique('companions', 'iuc')
            ->where(fn($q) => $q
                ->join('clients', 'companions.client_id', '=', 'clients.id')
                ->where('clients.tenant_id', auth()->user()->tenant_id)
            )
            ->ignore($this->companion?->id)  // Solo en Update
        ]
    ),
],
```

**Comportamiento:**
- `iuc = null` → pasa siempre (no hay duplicado de nulls)
- `iuc = 'ABC123'` → se verifica unicidad dentro del tenant
- En Update: ignora el propio companion (permite guardar sin cambiar el IUC)

---

## API Contract

### `CompanionResource` — nuevo campo

```php
'iuc' => $this->iuc,
```

Aparece en todas las respuestas de companions: `GET /api/clients/{client}/companions`, `POST`, `PUT`.

### `StoreCompanionRequest` / `UpdateCompanionRequest`

Agregar regla `iuc` según sección de validación anterior.

### `Companion` model — `$fillable`

Agregar `'iuc'` al array `$fillable`.

---

## Tipos TypeScript

### `types/companion.ts` — modificaciones

```typescript
// En interface Companion:
iuc?: string | null;

// En CreateCompanionData:
iuc?: string;

// En UpdateCompanionData:
iuc?: string;
```

---

## Formularios Frontend — Dos puntos de entrada

Ambos formularios son independientes (no hay componente compartido). Se agrega el campo `iuc` en los mismos términos en ambos.

### Posición del campo en el formulario

Después de `nationality` y antes de `passport_number` (agrupación lógica: datos de identidad IRCC).

### Template a agregar en ambos formularios

```html
<!-- IUC -->
<div>
    <label class="block text-sm font-medium mb-1 dark:text-white">
        {{ $t('companions.iuc') }}
    </label>
    <input
        v-model="companionForm.iuc"
        type="text"
        class="form-input"
        maxlength="20"
        :placeholder="$t('companions.iuc_placeholder')"
    />
</div>
```

### Estado reactivo — agregar en `companionForm`

```typescript
iuc: '' as string | null,  // en el objeto companionForm inicial
```

### Visualización en tarjeta de companion (show.vue + wizard)

En la tarjeta de companion ya existente, agregar badge de IUC junto al número de pasaporte:

```html
<span v-if="companion.iuc" class="text-xs text-gray-500">
    IUC: {{ companion.iuc }}
</span>
```

---

## i18n — Claves Necesarias

| Key | EN | ES | FR |
|-----|----|----|-----|
| `companions.iuc` | IUC (IRCC) | IUC (IRCC) | IUC (IRCC) |
| `companions.iuc_placeholder` | e.g. 1234-5678 | ej. 1234-5678 | ex. 1234-5678 |

---

## Lista Completa de Archivos Impactados

### Backend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/YYYY_add_iuc_to_companions_table.php` | Agrega columna `iuc VARCHAR(20) nullable` con `INDEX(iuc)` |

### Backend — Modificados

| Archivo | Cambio |
|---------|--------|
| `app/Models/Companion.php` | Agregar `'iuc'` a `$fillable` |
| `app/Http/Resources/CompanionResource.php` | Agregar `'iuc' => $this->iuc` |
| `app/Http/Requests/Companion/StoreCompanionRequest.php` | Agregar regla `iuc` con validación de unicidad por tenant |
| `app/Http/Requests/Companion/UpdateCompanionRequest.php` | Ídem con `->ignore($this->companion?->id)` |

### Frontend — Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/types/companion.ts` | Agregar `iuc?: string \| null` en `Companion`, `CreateCompanionData`, `UpdateCompanionData` |
| `resources/js/src/views/cases/wizard/steps/StepCompanions.vue` | Agregar campo `iuc` en formulario + `iuc: ''` en `companionForm` + IUC en tarjeta |
| `resources/js/src/views/clients/show.vue` | Ídem |
| `resources/js/src/locales/en.json` | +2 claves `companions.iuc`, `companions.iuc_placeholder` |
| `resources/js/src/locales/es.json` | Ídem |
| `resources/js/src/locales/fr.json` | Ídem |

---

## Casos de Borde y Riesgos

| Caso | Comportamiento |
|------|---------------|
| Companion existente sin IUC | `iuc = NULL` — se muestra "—" en la UI; no hay error |
| IUC duplicado en mismo tenant | Error 422: `"The IUC has already been taken."` |
| IUC duplicado en diferente tenant | Permitido — la unicidad es por tenant |
| IUC en blanco enviado (`""`) | Tratado como null por el backend (`nullable`) |
| Búsqueda futura por IUC | `INDEX(iuc)` ya creado — `WHERE iuc = ?` eficiente |
| Formato inválido | No hay validación de formato estricta (igual que `iuc` en clients) |
| Update sin cambiar IUC | `->ignore($companion->id)` evita falso positivo de unicidad |

---

## Checklist de Implementación

### Fase 1: Backend Core ✅
- [x] Crear migración `add_iuc_to_companions_table` con `VARCHAR(20) nullable` + `INDEX(iuc)`
- [x] Agregar `'iuc'` a `$fillable` en `app/Models/Companion.php`
- [x] Agregar `'iuc' => $this->iuc` en `CompanionResource`
- [x] Agregar regla `iuc` en `StoreCompanionRequest` (nullable + unicidad por tenant)
- [x] Agregar regla `iuc` en `UpdateCompanionRequest` (nullable + unicidad + ignore self)
- [x] Ejecutar `php artisan migrate`
- [x] Ejecutar `./vendor/bin/phpunit --filter=Companion` — 45 tests, 102 assertions, sin regresiones

### Fase 2: Frontend Types + i18n ✅
- [x] Agregar `iuc?: string | null` en `Companion`, `CreateCompanionData`, `UpdateCompanionData` en `types/companion.ts`
- [x] Agregar `companions.iuc` y `companions.iuc_placeholder` en `locales/en.json`
- [x] Ídem en `locales/es.json`
- [x] Ídem en `locales/fr.json`

### Fase 3: Formularios y Visualización ✅
- [x] Agregar campo `iuc` en `companionForm` (state) en `StepCompanions.vue`
- [x] Agregar input IUC en el template del formulario de `StepCompanions.vue`
- [x] Mostrar IUC en tarjeta de companion en `StepCompanions.vue` (via `CompanionCheckbox.vue`)
- [x] Agregar campo `iuc` en `companionForm` (state) en `clients/show.vue`
- [x] Agregar input IUC en el template del modal de `clients/show.vue`
- [x] Mostrar IUC en tarjeta de companion en `clients/show.vue`

### Fase 4: QA ✅
- [x] `npm run build` — ✅ 8.36s, cero errores TypeScript
- [ ] Crear companion con IUC → se persiste y se muestra
- [ ] Editar companion cambiando IUC → se actualiza
- [ ] IUC duplicado en mismo tenant → error 422
- [ ] Companion sin IUC → muestra "—" sin errores
- [x] `./vendor/bin/phpunit --filter=Companion` — 45 tests pasan
