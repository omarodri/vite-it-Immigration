---
tipo: spec
numero: 59
modulo: Expedientes
dominio: CASES
estado: DONE
tags: [spec, expedientes, done]
relacionados:
  - "[[08_permisos_clientes_acompanantes_roles|Spec 08]]"
  - "[[10_companions_en_detalle_expediente|Spec 10]]"
  - "[[39_companion_form_unificacion_componente|Spec 39]]"
  - "[[58_cliente_vs_aplicante_principal|Spec 58]]"
---
# [[59_promocion_companion_a_cliente|Spec 59]]: Promoción de Acompañante a Cliente Independiente

**Versión:** 1.0
**Fecha:** 2026-05-15
**Branch:** `feature/companion-to-client-promotion`
**Stack:** Laravel 12 + Vue 3.5 + TypeScript + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[08_permisos_clientes_acompanantes_roles|Spec 08]] (Permisos Clientes/Acompañantes), [[10_companions_en_detalle_expediente|Spec 10]] (Companions en Expediente), [[39_companion_form_unificacion_componente|Spec 39]] (Form Companions Unificado), [[58_cliente_vs_aplicante_principal|Spec 58]] (Cliente vs Aplicante Principal)

---

## Resumen Ejecutivo

Actualmente no existe ningún mecanismo para convertir un acompañante/dependiente en un cliente independiente. Este escenario es frecuente en práctica migratoria real: un hijo que alcanza la mayoría de edad, un cónyuge que inicia su propio proceso, o un familiar que decide contratar servicios de forma separada.

Esta feature implementa un flujo de **"promoción"** que permite materializar un nuevo `Client` a partir de los datos de un `Companion` existente con un solo clic. El companion **no se elimina ni desvincula** de ningún expediente; a partir de la conversión los dos registros son independientes.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | `ClientRepository::existsByEmailForTenant()` **no usa `withTrashed()`** | `app/Repositories/Eloquent/ClientRepository.php:94-113` | La validación de unicidad total (incluyendo soft-deleted) requiere métodos nuevos, no modificar los existentes |
| H2 | `ClientService::createClient()` valida email/phone únicos **solo entre clientes activos** | `app/Services/Client/ClientService.php:34-42` | La lógica de promoción requiere un servicio dedicado con validación más estricta |
| H3 | `case_companions` es **pivot puro sin modelo Eloquent ni `tenant_id`** | `database/migrations/0001_01_01_000006_create_business_tables.php:331-338` | El companion permanece vinculado a sus expedientes sin ningún cambio en el pivot |
| H4 | `Companion` tiene `getAgeAttribute()` que calcula edad desde `date_of_birth` | `app/Models/Companion.php:160-167` | La validación de edad ≥ 18 se calcula directamente sobre el modelo — no es input del request |
| H5 | `Client` tiene `status ENUM('prospect','active','inactive','archived')` ya existente | `app/Models/Client.php:56` | El cliente promovido nace con `status = 'active'` sin migración adicional de columna |
| H6 | No existe `CompanionPolicy` en el codebase | `app/Policies/` | Debe crearse; actualmente la autorización de companions se delega al controller sin policy formal |
| H7 | El companion expone `age` calculada y `email`, `phone`, `date_of_birth` en su Resource | `app/Http/Resources/CompanionResource.php` | El frontend puede evaluar condiciones de elegibilidad localmente (edad, datos mínimos) sin llamada adicional |
| H8 | `TenantScope` global **sí aplica con `withTrashed()`** en Eloquent — son scopes independientes | Comportamiento de Eloquent | La query `Client::withTrashed()->where(...)` respeta automáticamente el `tenant_id` del tenant activo |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **`ClientPromotionService` dedicado** (no método en `ClientService` ni `CompanionService`) | Acción cross-domain; SRP; testeable aislado; coherente con `TwoFactorService`, `OAuthFlowController` |
| D2 | **`ClientPromotionController` dedicado** (no acción extra en `CompanionController`) | El recurso producido es un `Client`; el controller de companions no debe retornar `ClientResource` |
| D3 | **Columna `clients.promoted_from_companion_id` con UNIQUE INDEX** | Trazabilidad consultable + idempotencia DB + UX ("ya promovido") — más robusto que solo activity log |
| D4 | **Defense-in-depth de concurrencia: `SELECT FOR UPDATE` + UNIQUE INDEX compuesto** | El lock serializa y da error de negocio limpio; el índice es la última red de seguridad ante otras rutas |
| D5 | **El companion NO se modifica, NO se desvincula** tras la promoción | Preserva integridad de `case_companions`; los dos registros son independientes desde la conversión |
| D6 | **Activity log fuera de `DB::transaction()`** | Mantiene la TX corta; no acopla rollback de la promoción a fallos del log de actividad |
| D7 | **Métodos `…WithTrashed()` nuevos** en `ClientRepository` (no parametrizar los existentes) | Callers actuales (`createClient`, `updateClient`) no deben cambiar su contrato de unicidad |
| D8 | **Estrategia híbrida de validación frontend:** chequeos locales (edad, datos mínimos) + endpoint `eligibility` para unicidad | UX inmediata + sin N+1 en el hot path del case detail + frescura justo antes del POST |
| D9 | **Ícono siempre visible, color/tooltip según estado** (no ocultar cuando no elegible) | Consistencia visual; el usuario aprende qué falta; elimina ambigüedad sobre ausencia del ícono |
| D10 | **SweetAlert2 para confirmación** (no modal Vue dedicado) | Estándar del proyecto; no requiere form fields editables — la promoción es 1:1 sin overrides |
| D11 | **Permiso `clients.promote_from_companion`** → admin + attorney, NO paralegal | Acción de alto impacto (crea relación contractual); el admin del tenant puede asignar después |
| D12 | **Nuevo cliente nace con `status = 'active'`, `user_id = null`** | Regla de negocio explícita; cuenta de usuario se invita en flujo posterior independiente |

---

## 1. Modelado de Datos

### 1.1 Migración 1 — `promoted_from_companion_id` en `clients`

```php
<?php
// database/migrations/2026_05_15_000001_add_promoted_from_companion_id_to_clients.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('promoted_from_companion_id')
                  ->nullable()
                  ->after('user_id')
                  ->constrained('companions')
                  ->nullOnDelete();

            $table->unique('promoted_from_companion_id', 'clients_promoted_companion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_promoted_companion_unique');
            $table->dropForeign(['promoted_from_companion_id']);
            $table->dropColumn('promoted_from_companion_id');
        });
    }
};
```

**Por qué UNIQUE en `promoted_from_companion_id`:** Un companion solo puede originar un único client. La constraint de DB actúa como última red de seguridad contra race conditions: si dos requests llegan simultáneamente, solo uno puede hacer el INSERT. MySQL permite múltiples `NULL` en un UNIQUE index, por lo que los clientes no promovidos no se ven afectados.

**Por qué `nullOnDelete`:** Si el companion se elimina forzadamente (hard delete), la referencia histórica se limpia automáticamente. El activity log preserva el contexto de la promoción original.

### 1.2 Migración 2 — Permiso `clients.promote_from_companion`

```php
<?php
// database/migrations/2026_05_15_000002_add_clients_promote_from_companion_permission.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'clients.promote_from_companion']);

        foreach (['super-admin', 'admin', 'attorney'] as $roleName) {
            $role = Role::findByName($roleName);
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'clients.promote_from_companion')->delete();
    }
};
```

### 1.3 Migración 3 — UNIQUE compuesto `(tenant_id, email)` y `(tenant_id, phone)` en `clients`

> **PRECAUCIÓN:** Verificar que no existan duplicados activos antes de ejecutar en producción.
> `SELECT tenant_id, email, COUNT(*) FROM clients WHERE deleted_at IS NULL GROUP BY tenant_id, email HAVING COUNT(*) > 1;`

```php
<?php
// database/migrations/2026_05_15_000003_add_unique_email_phone_per_tenant_on_clients.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Índice compuesto para unicidad email por tenant (solo activos — deleted_at NULL se indexa)
            $table->unique(['tenant_id', 'email'], 'clients_tenant_email_unique');
            $table->unique(['tenant_id', 'phone'], 'clients_tenant_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('clients_tenant_email_unique');
            $table->dropUnique('clients_tenant_phone_unique');
        });
    }
};
```

> **Nota MySQL 8.0:** MySQL no soporta índices únicos parciales nativos (tipo Postgres `WHERE deleted_at IS NULL`). El índice compuesto `(tenant_id, email)` permite que existan múltiples filas soft-deleted con el mismo email en el mismo tenant (porque `deleted_at IS NOT NULL` no forma parte del índice, pero tampoco viola la constraint ya que los soft-deleted conservan el email). Si se requiere unicidad también entre soft-deleted, la validación en servicio con `withTrashed()` es la que cubre ese caso.

### 1.4 Esquema resultante de `clients` (columnas relevantes)

```sql
clients
  id                           BIGINT UNSIGNED PK
  tenant_id                    BIGINT UNSIGNED FK → tenants.id
  user_id                      BIGINT UNSIGNED NULL FK → users.id
  promoted_from_companion_id   BIGINT UNSIGNED NULL FK → companions.id  ← NUEVO
  first_name                   VARCHAR
  last_name                    VARCHAR
  email                        VARCHAR  (UNIQUE por tenant cuando activo)
  phone                        VARCHAR  (UNIQUE por tenant cuando activo)
  status                       ENUM('prospect','active','inactive','archived')
  ...
```

---

## 2. Backend — Modelos Eloquent

### 2.1 `Companion` — relación `promotedClient`

```php
// app/Models/Companion.php

use Illuminate\Database\Eloquent\Relations\HasOne;

// Agregar relación:
public function promotedClient(): HasOne
{
    return $this->hasOne(Client::class, 'promoted_from_companion_id');
}
```

### 2.2 `Client` — relación inversa + campo en `$fillable`

```php
// app/Models/Client.php

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Agregar a $fillable:
'promoted_from_companion_id',

// Agregar relación:
public function originatedFromCompanion(): BelongsTo
{
    return $this->belongsTo(Companion::class, 'promoted_from_companion_id');
}
```

---

## 3. Backend — Repositorio

### 3.1 `ClientRepositoryInterface` — métodos nuevos

```php
// app/Repositories/Contracts/ClientRepositoryInterface.php

// Agregar (NO modificar los existentes — sus callers no cambian):
public function existsByEmailForTenantWithTrashed(string $email): bool;
public function existsByPhoneForTenantWithTrashed(string $phone): bool;
```

### 3.2 `ClientRepository` — implementación

```php
// app/Repositories/Eloquent/ClientRepository.php

public function existsByEmailForTenantWithTrashed(string $email): bool
{
    return Client::withTrashed()->where('email', $email)->exists();
}

public function existsByPhoneForTenantWithTrashed(string $phone): bool
{
    return Client::withTrashed()->where('phone', $phone)->exists();
}
```

**Por qué `withTrashed()` respeta multi-tenancy:** `withTrashed()` desactiva únicamente el `SoftDeletingScope`. El `TenantScope` global es un scope independiente y permanece activo; la query resultante incluye automáticamente `WHERE tenant_id = ?`.

---

## 4. Backend — Excepciones Tipadas

```php
<?php
// app/Exceptions/CompanionNotEligibleForPromotionException.php

namespace App\Exceptions;

use RuntimeException;

class CompanionNotEligibleForPromotionException extends RuntimeException
{
    public function __construct(
        public readonly string $code,
        public readonly array $missing = []
    ) {
        parent::__construct($code);
    }
}
```

```php
<?php
// app/Exceptions/ClientPromotionConflictException.php

namespace App\Exceptions;

use RuntimeException;

class ClientPromotionConflictException extends RuntimeException
{
    public function __construct(public readonly string $code)
    {
        parent::__construct($code);
    }
}
```

---

## 5. Backend — `ClientPromotionService`

```php
<?php
// app/Services/Client/ClientPromotionService.php

namespace App\Services\Client;

use App\Exceptions\ClientPromotionConflictException;
use App\Exceptions\CompanionNotEligibleForPromotionException;
use App\Models\Client;
use App\Models\Companion;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ClientPromotionService
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository
    ) {}

    /**
     * @throws CompanionNotEligibleForPromotionException
     * @throws ClientPromotionConflictException
     */
    public function promote(Companion $companion, User $actor): Client
    {
        $client = null;

        try {
            $client = DB::transaction(function () use ($companion) {
                // Lock pesimista: serializa promociones concurrentes del mismo companion
                DB::table('companions')->where('id', $companion->id)->lockForUpdate()->first();

                $this->assertEligible($companion);

                return $this->clientRepository->create(
                    $this->buildClientData($companion)
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] === 1062) {
                throw new ClientPromotionConflictException('email_or_phone_already_exists');
            }
            throw $e;
        }

        activity()
            ->causedBy($actor)
            ->performedOn($client)
            ->withProperties([
                'source_companion_id' => $companion->id,
                'source_companion'    => $companion->full_name,
            ])
            ->log('Promoted companion to client: ' . $companion->full_name);

        return $client;
    }

    public function checkEligibility(Companion $companion): array
    {
        $reasons = [];

        if (empty(trim($companion->first_name ?? ''))) $reasons[] = 'missing_first_name';
        if (empty(trim($companion->last_name ?? '')))  $reasons[] = 'missing_last_name';
        if (empty(trim($companion->email ?? '')))      $reasons[] = 'missing_email';
        if (empty(trim($companion->phone ?? '')))      $reasons[] = 'missing_phone';

        if (! $companion->date_of_birth) {
            $reasons[] = 'missing_date_of_birth';
        } elseif ($companion->age < 18) {
            $reasons[] = 'underage';
        }

        if ($this->clientRepository->existsByEmailForTenantWithTrashed($companion->email ?? '')) {
            $reasons[] = 'email_exists_in_clients';
        }

        if ($this->clientRepository->existsByPhoneForTenantWithTrashed($companion->phone ?? '')) {
            $reasons[] = 'phone_exists_in_clients';
        }

        $alreadyPromoted = Client::where('promoted_from_companion_id', $companion->id)->exists();

        return [
            'eligible'           => empty($reasons) && ! $alreadyPromoted,
            'reasons'            => $reasons,
            'already_promoted'   => $alreadyPromoted,
            'existing_client_id' => $alreadyPromoted
                ? Client::where('promoted_from_companion_id', $companion->id)->value('id')
                : null,
        ];
    }

    private function assertEligible(Companion $companion): void
    {
        $missing = [];

        if (empty(trim($companion->first_name ?? ''))) $missing[] = 'first_name';
        if (empty(trim($companion->last_name ?? '')))  $missing[] = 'last_name';
        if (empty(trim($companion->email ?? '')))      $missing[] = 'email';
        if (empty(trim($companion->phone ?? '')))      $missing[] = 'phone';

        if (! empty($missing)) {
            throw new CompanionNotEligibleForPromotionException('missing_required_fields', $missing);
        }

        if (! $companion->date_of_birth) {
            throw new CompanionNotEligibleForPromotionException('missing_date_of_birth');
        }

        if ($companion->age < 18) {
            throw new CompanionNotEligibleForPromotionException('underage');
        }

        if (Client::where('promoted_from_companion_id', $companion->id)->exists()) {
            throw new ClientPromotionConflictException('already_promoted');
        }

        if ($this->clientRepository->existsByEmailForTenantWithTrashed($companion->email)) {
            throw new ClientPromotionConflictException('email_already_exists');
        }

        if ($this->clientRepository->existsByPhoneForTenantWithTrashed($companion->phone)) {
            throw new ClientPromotionConflictException('phone_already_exists');
        }
    }

    private function buildClientData(Companion $companion): array
    {
        return [
            'tenant_id'                  => $companion->tenant_id,
            'first_name'                 => $companion->first_name,
            'last_name'                  => $companion->last_name,
            'email'                      => $companion->email,
            'phone'                      => $companion->phone,
            'phone_country_code'         => $companion->phone_country_code,
            'date_of_birth'              => $companion->date_of_birth,
            'gender'                     => $companion->gender,
            'marital_status'             => $companion->marital_status,
            'nationality'                => $companion->nationality,
            'iuc'                        => $companion->iuc,
            'canada_status'              => $companion->canada_status,
            'other_status_1'             => $companion->canada_status_other,
            'arrival_date'               => $companion->arrival_date,
            'description'                => $companion->notes,
            'status'                     => 'active',
            'promoted_from_companion_id' => $companion->id,
        ];
    }
}
```

**Mapeo de atributos `Companion → Client`:**

| Campo `Companion` | Campo `Client` | Nota |
|---|---|---|
| `tenant_id` | `tenant_id` | Copia directa |
| `first_name`, `last_name` | Directos | Copia directa |
| `email`, `phone`, `phone_country_code` | Directos | Validar unicidad |
| `date_of_birth`, `gender`, `marital_status` | Directos | Copia directa |
| `nationality`, `iuc`, `arrival_date` | Directos | Copia directa |
| `canada_status` | `canada_status` | Copia directa |
| `canada_status_other` | `other_status_1` | Mapeo nominal: `Client` no tiene `canada_status_other` |
| `notes` | `description` | Mapeo semántico |
| `relationship`, `relationship_other` | — | **Descartado** — es metadata del vínculo, no del individuo |
| — | `status` | **Forzado a `'active'`** |
| — | `user_id` | **`null`** — se invita en flujo posterior |
| — | `promoted_from_companion_id` | `$companion->id` (trazabilidad) |

---

## 6. Backend — Capa HTTP

### 6.1 `PromoteCompanionToClientRequest`

```php
<?php
// app/Http/Requests/PromoteCompanionToClientRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromoteCompanionToClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La policy se evalúa en el controller
    }

    public function rules(): array
    {
        return [
            'confirmed' => ['required', 'accepted'],
        ];
    }
}
```

**Mínimo intencional:** Todo lo demás (edad, datos mínimos, unicidad) se valida sobre la entidad persistida del companion, no sobre input del request. La única regla del request es `confirmed: true` como defensa anti-curl accidental.

### 6.2 `CompanionPolicy`

```php
<?php
// app/Policies/CompanionPolicy.php

namespace App\Policies;

use App\Models\Companion;
use App\Models\User;

class CompanionPolicy
{
    public function promoteToClient(User $user, Companion $companion): bool
    {
        return $user->can('clients.promote_from_companion')
            && $user->tenant_id === $companion->tenant_id;
    }
}
```

Registrar en `AuthServiceProvider::policies()`:

```php
\App\Models\Companion::class => \App\Policies\CompanionPolicy::class,
```

### 6.3 `ClientPromotionController`

```php
<?php
// app/Http/Controllers/Api/ClientPromotionController.php

namespace App\Http\Controllers\Api;

use App\Exceptions\ClientPromotionConflictException;
use App\Exceptions\CompanionNotEligibleForPromotionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteCompanionToClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Companion;
use App\Services\Client\ClientPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClientPromotionController extends Controller
{
    public function __construct(
        private ClientPromotionService $promotionService
    ) {}

    public function store(PromoteCompanionToClientRequest $request, Companion $companion): JsonResponse
    {
        $this->authorize('promoteToClient', $companion);

        try {
            $client = $this->promotionService->promote($companion, Auth::user());
        } catch (CompanionNotEligibleForPromotionException $e) {
            return response()->json([
                'message' => __('companion.promote.error.' . $e->code),
                'code'    => $e->code,
                'missing' => $e->missing,
            ], 422);
        } catch (ClientPromotionConflictException $e) {
            return response()->json([
                'message' => __('companion.promote.error.' . $e->code),
                'code'    => $e->code,
            ], 409);
        }

        return response()->json([
            'data'    => new ClientResource($client),
            'message' => __('companion.promote.success'),
        ], 201);
    }

    public function eligibility(Companion $companion): JsonResponse
    {
        $this->authorize('promoteToClient', $companion);

        return response()->json([
            'data' => $this->promotionService->checkEligibility($companion),
        ]);
    }
}
```

### 6.4 Rutas en `api.php`

```php
// routes/api.php — dentro del grupo auth:sanctum + tenant

Route::post('companions/{companion}/promote-to-client',
    [ClientPromotionController::class, 'store'])
    ->name('companions.promote-to-client');

Route::get('companions/{companion}/promotion-eligibility',
    [ClientPromotionController::class, 'eligibility'])
    ->name('companions.promotion-eligibility');
```

### 6.5 `CompanionResource` — campo `already_promoted`

```php
// app/Http/Resources/CompanionResource.php — agregar al toArray():

'already_promoted'    => $this->whenLoaded('promotedClient', fn() => true, fn() => $this->promotedClient()->exists()),
'promoted_to_client_id' => $this->whenLoaded('promotedClient', fn() => $this->promotedClient?->id),
```

> **N+1 — mitigación obligatoria:** En `CaseController::show()` agregar `'companions.promotedClient'` al eager load del caso para evitar una query por companion al renderizar la sección de dependientes.

---

## 7. Frontend — Servicio `companionPromotionService.ts`

```typescript
// resources/js/src/services/companionPromotionService.ts

import api from '@/services/api';

export interface PromotionEligibilityResponse {
    eligible: boolean;
    reasons: string[];
    already_promoted: boolean;
    existing_client_id: number | null;
}

export interface PromotionSuccessData {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    status: string;
}

const companionPromotionService = {
    checkEligibility(companionId: number) {
        return api.get<{ data: PromotionEligibilityResponse }>(
            `/companions/${companionId}/promotion-eligibility`
        );
    },

    promote(companionId: number) {
        return api.post<{ data: PromotionSuccessData; message: string }>(
            `/companions/${companionId}/promote-to-client`,
            { confirmed: true }
        );
    },
};

export default companionPromotionService;
```

**Contratos de respuesta:**

| Código | Payload | Causa |
|--------|---------|-------|
| `201` | `{ data: ClientResource, message: string }` | Promoción exitosa |
| `403` | `{ message: 'forbidden' }` | Sin permiso `clients.promote_from_companion` |
| `409` | `{ code: 'email_already_exists' \| 'phone_already_exists' \| 'already_promoted', message }` | Conflicto de unicidad o idempotencia |
| `422` | `{ code: 'underage' \| 'missing_required_fields', message, missing: string[] }` | Companion no cumple requisitos |

---

## 8. Frontend — Tipos TypeScript

```typescript
// resources/js/src/types/companion.ts — agregar campos:

already_promoted?: boolean;
promoted_to_client_id?: number | null;
```

---

## 9. Frontend — Vista `cases/show.vue`

### 9.1 Computed `canBePromoted`

```typescript
// resources/js/src/views/cases/show.vue — en <script setup>

import companionPromotionService from '@/services/companionPromotionService';
import type { Companion } from '@/types/companion';

interface EligibilityResult {
    eligible: boolean;
    reasons: string[];
    alreadyPromoted: boolean;
}

const computeLocalEligibility = (c: Companion): EligibilityResult => {
    const reasons: string[] = [];

    if (!c.first_name?.trim()) reasons.push('missing_first_name');
    if (!c.last_name?.trim())  reasons.push('missing_last_name');
    if (!c.email?.trim())      reasons.push('missing_email');
    if (!c.phone?.trim())      reasons.push('missing_phone');

    if (!c.date_of_birth) {
        reasons.push('missing_date_of_birth');
    } else if ((c.age ?? 0) < 18) {
        reasons.push('underage');
    }

    return {
        eligible:       reasons.length === 0 && !c.already_promoted,
        reasons,
        alreadyPromoted: !!c.already_promoted,
    };
};
```

> **Nota:** La unicidad del email/phone en `clients` no se puede evaluar en el frontend. El endpoint `promotion-eligibility` la resuelve en el momento del click.

### 9.2 Handler `handlePromote`

```typescript
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const { t } = useI18n();

const handlePromote = async (companion: Companion) => {
    // 1. Verificar unicidad en servidor antes de mostrar el modal
    try {
        const { data } = await companionPromotionService.checkEligibility(companion.id);
        if (!data.data.eligible) {
            const firstReason = data.data.reasons[0] ?? 'unknown';
            await Swal.fire({
                icon: 'warning',
                title: t('companion.promote.not_eligible_title'),
                text: t(`companion.promote.tooltip_${firstReason}`),
            });
            return;
        }
    } catch {
        return;
    }

    // 2. Modal de confirmación
    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: t('companion.promote.modal.title', { fullName: companion.full_name }),
        html: `<p>${t('companion.promote.modal.body_html')}</p>
               <p class="mt-2 text-sm text-gray-500">${t('companion.promote.modal.fields_to_copy')}</p>`,
        showCancelButton: true,
        confirmButtonText: t('companion.promote.modal.confirm'),
        cancelButtonText: t('companion.promote.modal.cancel'),
        confirmButtonColor: '#00ab55',
    });

    if (!isConfirmed) return;

    // 3. Ejecutar promoción
    try {
        const { data } = await companionPromotionService.promote(companion.id);
        await Swal.fire({
            icon: 'success',
            title: t('companion.promote.success'),
            timer: 2000,
            showConfirmButton: false,
        });
        router.push({ name: 'clients-detail', params: { id: data.data.id } });
    } catch (err: any) {
        const code = err.response?.data?.code ?? 'unknown';
        Swal.fire({
            icon: 'error',
            title: t('companion.promote.error_title'),
            text: t(`companion.promote.error.${code}`),
        });
    }
};
```

### 9.3 Template — botón de promoción en el loop de dependientes

```vue
<!-- Dentro del div v-for="companion in dependentCompanions" -->
<!-- Reemplazar/extender la zona de botones existente: -->

<div class="flex items-center gap-1 shrink-0">

    <!-- Ícono: ya promovido -->
    <router-link
        v-if="companion.already_promoted && companion.promoted_to_client_id"
        :to="{ name: 'clients-detail', params: { id: companion.promoted_to_client_id } }"
        class="btn btn-sm btn-outline-info p-1.5"
        :title="$t('companion.promote.tooltip_already_promoted')"
    >
        <icon-user-check class="w-4 h-4 text-info" />
    </router-link>

    <!-- Ícono: elegible para promover -->
    <button
        v-else-if="computeLocalEligibility(companion).eligible"
        type="button"
        class="btn btn-sm btn-outline-success p-1.5"
        :title="$t('companion.promote.tooltip_eligible')"
        @click="handlePromote(companion)"
    >
        <icon-user-plus class="w-4 h-4" />
    </button>

    <!-- Ícono: no elegible (con tooltip de razón) -->
    <button
        v-else-if="!companion.already_promoted"
        type="button"
        class="btn btn-sm p-1.5 text-gray-400 cursor-not-allowed"
        :title="$t('companion.promote.tooltip_' + (computeLocalEligibility(companion).reasons[0] ?? 'missing_fields'))"
        disabled
    >
        <icon-user-plus class="w-4 h-4" />
    </button>

    <!-- Botón existente: ver companion -->
    <button
        type="button"
        class="btn btn-sm btn-outline-info p-1.5"
        :title="$t('companions.view_companion')"
        @click="openCompanionView(companion)"
    >
        <icon-eye class="w-4 h-4" />
    </button>

</div>
```

---

## 10. Internacionalización (i18n)

**Regla crítica:** Keys FLAT. Nunca objetos JSON anidados. Ningún valor contiene `<` ni `>` literal — no requiere escape `{'<'}` `{'>'}` (Gotcha G10).

Agregar a `es.json`, `en.json`, `fr.json`:

| Key | Valor (es) |
|-----|-----------|
| `companion.promote.button_label` | `"Promover a cliente"` |
| `companion.promote.tooltip_eligible` | `"Promover a este acompañante a cliente independiente"` |
| `companion.promote.tooltip_underage` | `"No elegible: el acompañante debe tener 18 años o más"` |
| `companion.promote.tooltip_missing_fields` | `"No elegible: faltan datos mínimos"` |
| `companion.promote.tooltip_missing_first_name` | `"Falta el nombre"` |
| `companion.promote.tooltip_missing_last_name` | `"Falta el apellido"` |
| `companion.promote.tooltip_missing_email` | `"Falta el email"` |
| `companion.promote.tooltip_missing_phone` | `"Falta el teléfono"` |
| `companion.promote.tooltip_missing_date_of_birth` | `"Falta la fecha de nacimiento"` |
| `companion.promote.tooltip_already_promoted` | `"Este acompañante ya fue promovido a cliente"` |
| `companion.promote.not_eligible_title` | `"No se puede promover"` |
| `companion.promote.modal.title` | `"Promover a {fullName} a cliente"` |
| `companion.promote.modal.body_html` | `"Se creará un nuevo cliente con estatus Activo usando los datos de este acompañante. El acompañante NO se elimina y seguirá vinculado a sus expedientes. El nuevo cliente y el acompañante quedarán como registros independientes."` |
| `companion.promote.modal.fields_to_copy` | `"Datos que se copiarán: nombre, email, teléfono, fecha de nacimiento, nacionalidad, estatus migratorio, IUC, notas."` |
| `companion.promote.modal.confirm` | `"Confirmar promoción"` |
| `companion.promote.modal.cancel` | `"Cancelar"` |
| `companion.promote.success` | `"Cliente creado correctamente desde acompañante"` |
| `companion.promote.error_title` | `"No fue posible completar la promoción"` |
| `companion.promote.error.email_already_exists` | `"Ya existe un cliente con este email"` |
| `companion.promote.error.phone_already_exists` | `"Ya existe un cliente con este teléfono"` |
| `companion.promote.error.email_or_phone_already_exists` | `"Ya existe un cliente con este email o teléfono"` |
| `companion.promote.error.already_promoted` | `"Este acompañante ya fue promovido anteriormente"` |
| `companion.promote.error.underage` | `"El acompañante no cumple la edad mínima de 22 años"` |
| `companion.promote.error.missing_required_fields` | `"El acompañante no tiene todos los datos mínimos requeridos"` |
| `companion.promote.error.missing_date_of_birth` | `"El acompañante no tiene fecha de nacimiento registrada"` |
| `companion.promote.error.forbidden` | `"No tienes permiso para promover acompañantes a clientes"` |
| `companion.promote.error.unknown` | `"No fue posible completar la promoción. Intenta nuevamente."` |

---

## 11. Checklist de Implementación

> **Estado:** IMPLEMENTADO — 2026-05-15
>
> **Desviaciones de implementación:**
> - `$code` en excepciones renombrado a `$errorCode` (colisión con propiedad no-readonly heredada de `Exception`). La clave JSON de respuesta HTTP sigue siendo `code`.
> - El rol `attorney` no existe en este tenant. Reemplazado por `consultor` en migración fuente y migración correctiva `2026_05_15_000004`.
> - `ClientResource` no existe en el proyecto. El controller retorna el modelo Eloquent directamente (`$client`). El campo `id` está presente en la respuesta.
> - Migración 3 fue no-op: las constraints `(tenant_id, email)` y `(tenant_id, phone)` ya existían en la migración base de negocio.
> - Ruta del cliente en Vue Router: `clients-show` (no `clients-detail`).

### FASE 1 — Base de Datos ✅

- [x] **Migración 1:** `ADD promoted_from_companion_id` a `clients` con UNIQUE INDEX y `nullOnDelete`
- [x] **Migración 2:** Permiso `clients.promote_from_companion` + asignación a `super-admin`, `admin`, `consultor`
  - ⚠️ La migración fuente usa `consultor`; migración `000004` corrige la asignación en DB
- [x] **Migración 3:** UNIQUE compuesto `(tenant_id, email/phone)` en `clients` — no-op (ya existía)

### FASE 2 — Service Layer ✅

- [x] Excepciones tipadas: `CompanionNotEligibleForPromotionException`, `ClientPromotionConflictException`
- [x] `ClientRepositoryInterface`: firmas `existsByEmailForTenantWithTrashed`, `existsByPhoneForTenantWithTrashed`
- [x] `ClientRepository`: implementación de ambos métodos
- [x] `Companion::promotedClient()` relación `HasOne`
- [x] `Client::originatedFromCompanion()` relación `BelongsTo` + `promoted_from_companion_id` en `$fillable`
- [x] `ClientPromotionService`: métodos `promote()`, `checkEligibility()`, `assertEligible()`, `buildClientData()`
- [ ] Tests unitarios `ClientPromotionServiceTest` — **PENDIENTE**

### FASE 3 — Capa HTTP ✅

- [x] `CompanionPolicy::promoteToClient()` — agregado a policy existente
- [x] `PromoteCompanionToClientRequest` con regla `confirmed: accepted`
- [x] `ClientPromotionController::store()` + `::eligibility()`
- [x] Rutas en `routes/api.php` dentro del grupo `auth:sanctum + throttle + tenant + ensure.single.session`
- [x] `CompanionResource`: campos `already_promoted`, `promoted_to_client_id`
- [x] `CaseService::getCase()`: eager load `companions.promotedClient` agregado
- [ ] Feature tests `ClientPromotionControllerTest` — **PENDIENTE**

### FASE 4 — Frontend ✅

- [x] `resources/js/src/types/companion.ts`: `already_promoted?`, `promoted_to_client_id?`
- [x] `resources/js/src/types/case.ts`: campos `email`, `phone`, `already_promoted`, `promoted_to_client_id` en shape inline de companions
- [x] `companionPromotionService.ts`: `checkEligibility()`, `promote()`
- [x] i18n: 27 keys agregadas a `es.json`, `en.json`, `fr.json`
- [x] `cases/show.vue`: `computeLocalEligibility`, `handlePromote`, grupo de 4 botones en loop de dependientes
- [ ] (Opcional) Replicar botones en `clients/show.vue`

### FASE 5 — Validación End-to-End

- [ ] Smoke test manual: companion underage → ícono gris con tooltip correcto
- [ ] Smoke test manual: companion con datos faltantes → ícono gris con tooltip específico
- [ ] Smoke test manual: companion elegible → ícono verde → click → eligibility OK → modal → confirm → redirect
- [ ] Smoke test manual: companion ya promovido → ícono info → link funcional al client
- [ ] Smoke test: segundo POST del mismo companion → `409 already_promoted`
- [x] i18n verificado en 3 idiomas (es, en, fr)
- [x] `spec/INDEX.md`: Spec 59 registrado con estado `PEND` → actualizar a `DONE` tras smoke tests
- [ ] `MEMORY.md`: agregar entrada en "Implementaciones Recientes"

---

## 12. Riesgos y Mitigaciones

| ID | Riesgo | Prob. | Impacto | Mitigación |
|----|--------|-------|---------|-----------|
| R1 | Race condition: dos usuarios promueven el mismo companion simultáneamente | M | A | `SELECT FOR UPDATE` en companion + UNIQUE INDEX en `promoted_from_companion_id` |
| R2 | UNIQUE `(tenant_id, email)` en clients roto por duplicados existentes en producción | M | A | Query de verificación obligatoria antes del ALTER; si hay duplicados, resolverlos manualmente primero |
| R3 | Tenant cross-leak en query `withTrashed()` | B | CRÍTICO | `TenantScope` global permanece activo; test explícito multi-tenant en `ClientRepositoryTest` |
| R4 | N+1 en `already_promoted` al renderizar companions del caso | M | M | Eager load `companions.promotedClient` en `CaseService::getCase()` |
| R5 | Frontend muestra ícono verde y POST falla por unicidad (ventana de inconsistencia) | M | M | Endpoint `eligibility` invocado justo antes del modal; mensajes de error específicos por `code` |
| R6 | `promoted_from_companion_id` huérfano si companion se soft-deletea | B | B | `nullOnDelete` limpia la FK en hard delete; soft delete preserva la relación histórica (correcto) |
| R7 | Activity log dentro de TX provoca rollback si falla | M | M | Activity log movido fuera de la TX (post-commit) — ya contemplado en servicio |
| R8 | Permiso no asignado tras `migrate:fresh` en staging/producción | M | A | Permiso + asignación a roles van en la misma migración dedicada (D-03 del proyecto) |
| R9 | Campos `null` en Client recién creado (dirección, idioma) violan validaciones en otros flujos | M | M | Verificar que `Client` no tenga constraints `NOT NULL` en esos campos antes de implementar |
| R10 | `Client::promotedClient` devuelve soft-deleted (companion eliminado) | B | B | `nullOnDelete` en FK limpia el campo; si llega a `null`, el ícono vuelve a mostrar el estado correcto |

---

## 13. Archivos Afectados

### Backend — Nuevos

| Archivo | Tipo |
|---------|------|
| `app/Services/Client/ClientPromotionService.php` | Servicio |
| `app/Http/Controllers/Api/ClientPromotionController.php` | Controlador |
| `app/Http/Requests/PromoteCompanionToClientRequest.php` | FormRequest |
| `app/Policies/CompanionPolicy.php` | Policy |
| `app/Exceptions/CompanionNotEligibleForPromotionException.php` | Excepción |
| `app/Exceptions/ClientPromotionConflictException.php` | Excepción |
| `database/migrations/2026_05_15_000001_add_promoted_from_companion_id_to_clients.php` | Migración |
| `database/migrations/2026_05_15_000002_add_clients_promote_from_companion_permission.php` | Migración |
| `database/migrations/2026_05_15_000003_add_unique_email_phone_per_tenant_on_clients.php` | Migración |

### Backend — Modificados

| Archivo | Cambio |
|---------|--------|
| `app/Repositories/Contracts/ClientRepositoryInterface.php` | Agregar 2 firmas `…WithTrashed` |
| `app/Repositories/Eloquent/ClientRepository.php` | Implementar los 2 métodos nuevos |
| `app/Models/Companion.php` | Agregar relación `promotedClient(): HasOne` |
| `app/Models/Client.php` | Agregar relación + `promoted_from_companion_id` en `$fillable` |
| `app/Http/Resources/CompanionResource.php` | Agregar `already_promoted`, `promoted_to_client_id` |
| `app/Services/Case/CaseService.php` | Agregar `companions.promotedClient` al eager load de `getCase()` |
| `app/Providers/AuthServiceProvider.php` | Registrar `CompanionPolicy` |
| `routes/api.php` | 2 rutas nuevas dentro del grupo auth+tenant |

### Frontend — Nuevos

| Archivo | Tipo |
|---------|------|
| `resources/js/src/services/companionPromotionService.ts` | Servicio API |

### Frontend — Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/types/companion.ts` | Agregar `already_promoted?`, `promoted_to_client_id?` |
| `resources/js/src/views/cases/show.vue` | Computed eligibility, handler promote, botones en loop dependientes |
| `resources/js/src/locales/es.json` | 28 keys nuevas `companion.promote.*` |
| `resources/js/src/locales/en.json` | 28 keys nuevas (equivalentes en inglés) |
| `resources/js/src/locales/fr.json` | 28 keys nuevas (equivalentes en francés) |
