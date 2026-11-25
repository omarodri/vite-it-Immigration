---
tipo: spec
numero: 64
modulo: Clientes y Acompañantes
dominio: CLNT
estado: PART
tags: [spec, clientes, parcial]
relacionados:
  - "[[07_epic_2.1_expedientes_core|Spec 07]]"
  - "[[08_permisos_clientes_acompanantes_roles|Spec 08]]"
  - "[[09_epic_2.2_case_wizard|Spec 09]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
  - "[[58_cliente_vs_aplicante_principal|Spec 58]]"
  - "[[59_promocion_companion_a_cliente|Spec 59]]"
---
# [[64_clientes_empresa_patrocinador_beneficiario|Spec 64]] — Clientes Empresa: Patrocinador + Beneficiario + Familia

**Dominio:** CLNT / CASES  
**Estado:** PART  
**Relacionados:** 07 (Core Expedientes), 08 (Permisos Clientes), 09 (Wizard Creación), 56 (Directorios Wizard), 58 (Cliente vs Aplicante Principal), 59 (Promoción Companion→Cliente)  
**Creado:** 2026-05-24  
**Autor:** Análisis de Impacto — Architect Agent + Validación de Reglas de Negocio

---

## Resumen Ejecutivo

El sistema asume actualmente que todo cliente es una **persona física**. Las tres reglas de negocio que guían esta expansión son:

1. Un cliente puede ser **empresa** o **persona**.
2. Una empresa actúa como **patrocinador (sponsor)** de un **empleado (beneficiario)** en un proceso migratorio.
3. El expediente del empleado puede o no incluir **integrantes de la familia del empleado** como dependientes.

La arquitectura correcta para este modelo es:

```
Empresa (Client, type='company')          ← contrata, paga, firma
    └── ImmigrationCase.client_id
            │
            ├── primary_applicant_companion_id → Companion (relationship='beneficiary')  ← empleado
            │                                       [El que realmente aplica e inmigra]
            │
            └── case_companions → Companion (relationship='spouse')    ← familia del empleado
            └── case_companions → Companion (relationship='child')
```

**Estrategia de persistencia:** Single Table Inheritance (STI) en `clients` con discriminador `type`. El empleado/beneficiario se modela como `Companion` con `relationship='beneficiary'`, aprovechando la infraestructura ya implementada en [[58_cliente_vs_aplicante_principal|Spec 58]] (`primary_applicant_type='companion'` + `primary_applicant_companion_id`). No se requieren tablas nuevas para el beneficiario.

**Implicación crítica vs. análisis previo:** Los companions **NO se bloquean** para clientes empresa — es exactamente lo contrario. Para un expediente corporativo, al menos un companion con `relationship='beneficiary'` es **obligatorio**. La familia del empleado son companions regulares del mismo expediente.

---

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | Spec 58 ya implementó `primary_applicant_type ENUM('client','companion')` y `primary_applicant_companion_id FK→companions` en `cases` | `database/migrations/` + `app/Models/ImmigrationCase.php:54-55` | El puntero al empleado es `primary_applicant_companion_id` — sin nueva tabla ni columna en `cases` |
| H2 | `ImmigrationCase::primaryApplicant()` ya retorna `Client\|Companion` — funciona transparentemente para ambos tipos | `app/Models/ImmigrationCase.php:244-257` | Para empresa, retorna el Companion-beneficiario. Cero cambios en este método. |
| H3 | `CaseService::generateCaseNumber()` ya acepta `string $primaryLastName` (no `Client $client`) desde Spec 58 | `app/Services/Case/CaseService.php:210-240` (aprox.) | El código `AAAA` del expediente de empresa usará el apellido del empleado, NO el nombre de la empresa — por diseño correcto |
| H4 | `clients.first_name` y `clients.last_name` son actualmente `NOT NULL` en la migración base | `database/migrations/0001_01_01_000006_create_business_tables.php` | Deben volverse `NULLABLE` para permitir clientes empresa (que no tienen nombre/apellido personal) |
| H5 | `companions.relationship` es un `VARCHAR` o `ENUM` — verificar si admite extensión sin migración de datos | `database/migrations/` (tabla `companions`) | Agregar `'beneficiary'` como valor permitido si es ENUM; si es VARCHAR, solo validar en el Request |
| H6 | `Companion` tiene `first_name`, `last_name`, `date_of_birth`, `nationality`, `passport_number` — suficiente para capturar datos del empleado | `app/Models/Companion.php` | El modelo Companion es adecuado para representar al beneficiario sin cambios estructurales |
| H7 | `ClientResource` serializa `first_name`, `last_name` — NO tiene `display_name` ni campos corporativos | `app/Http/Resources/ClientResource.php` | Agregar `display_name`, `type`, y campos empresa con `when()` condicional |
| H8 | [[59_promocion_companion_a_cliente|Spec 59]] (`CompanionPromotionService::promote()`) crea un `Client` sin asignar `type` | `app/Services/Companion/ClientPromotionService.php` | Agregar `'type' => 'person'` explícito en el payload de promoción |
| H9 | `FolderService` construye el nombre de carpeta del caso con datos del cliente | `app/Services/Document/FolderService.php` | Para empresa, la carpeta debe usar el nombre del empleado (via `primaryApplicant()`), no la razón social |
| H10 | `GlobalSearchService` busca clientes por `first_name + last_name LIKE` | `app/Services/Search/GlobalSearchService.php` | Debe buscar por `display_name` (columna virtual generada) para cubrir empresas |

---

### Alineación con Spec 44 — Formato de Visualización de Nombres

Cuando se listen o muestren clientes de **tipo persona** dentro del contexto de empresas patrocinadoras (listados mixtos, vistas de expediente, wizard, diálogos), se debe respetar estrictamente la preferencia de visualización de nombres del tenant definida en **[[44_preferencia_visualizacion_nombres|Spec 44]]**.

**Regla crítica:**

| Tipo | Campo a usar en UI | Por qué |
|------|-------------------|---------|
| `company` | `display_name` (columna MySQL generada) | Resuelve `trade_name ?? company_name`. No aplica `name_format`. |
| `person` | `full_name` (accessor PHP en backend) | Respeta `tenant.settings.name_format` (`first_last` \| `last_first`). |

**`display_name` para personas NO es format-aware:** La columna `GENERATED STORED` en MySQL está hardcodeada como `CONCAT(first_name, ' ', last_name)` (siempre `first_last`). Aunque la API la expone, el frontend debe preferir `full_name` para personas porque el accessor PHP sí consulta la preferencia del tenant (con cache de 5 min).

**Patrón de referencia implementado en `views/clients/list.vue` (`displayNameOf()`):**
```typescript
const displayNameOf = (client: Client): string => {
    if (client.type === 'company') {
        return client.display_name || client.trade_name || client.company_name || `#${client.id}`;
    }
    // Persons: full_name respects tenant name_format (Spec 44). display_name is hardcoded first_last.
    return client.full_name || `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim() || `#${client.id}`;
};
```

Este patrón debe replicarse en cualquier vista, componente o helper que muestre nombres de clientes.

---

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **STI sobre tabla `clients` existente** — columna `type ENUM('person','company')` | Cero regresión en relaciones existentes (client_id FKs en `cases`, `documents`, `companions`, `time_logs`). Polimorfismo requeriría reescribir 33+ referencias. |
| D2 | **Empleado = `Companion` con `relationship='beneficiary'`** | Reutiliza infraestructura de Spec 58. El puntero `primary_applicant_companion_id` ya existe en `cases`. El modelo `Companion` ya tiene todos los campos personales necesarios. |
| D3 | **Companions NO bloqueados para empresa** — al menos uno con `relationship='beneficiary'` es OBLIGATORIO | Regla de negocio R3: la familia del empleado son companions del expediente. Bloquear companions destruiría el modelo de negocio corporativo. |
| D4 | **Código `AAAA` del expediente = apellido del empleado (beneficiario)** | Spec 58 ya resuelve esto: cuando `primary_applicant_type='companion'`, `generateCaseNumber()` usa el `last_name` del companion. El nombre de la empresa NO va en el código. |
| D5 | **Columna virtual `display_name` GENERATED STORED** en `clients` | Unifica búsqueda, ordenamiento e índice FULLTEXT sin lógica duplicada. `STORED` (no `VIRTUAL`) para soportar índices. |
| D6 | **`first_name` y `last_name` se vuelven NULLABLE** en `clients` | Empresas no tienen nombre/apellido personal. Registros existentes (todos `person`) ya tienen valores — sin backfill. |
| D7 | **Dos FormRequests separados** (`StorePersonClientRequest` + `StoreCompanyClientRequest`) + dispatcher | Validación específica por tipo, con `prohibited` en campos del tipo opuesto. Más claro que un solo request con `Rule::requiredIf`. |
| D8 | **`ClientTypeSelectorModal.vue`** — modal previo a la creación (no dentro del formulario) | UX: empresa tiene 8 campos diferentes a persona; un solo formulario condicional sería confuso. Después de seleccionar tipo, no se puede cambiar. |
| D9 | **Cambio de `type` post-creación bloqueado** a nivel de service y frontend | Decisión estructural irreversible — cambia el modelo de datos completo del cliente. |
| D10 | **Step 3 del Wizard para empresa muestra sección "Empleado Beneficiario" OBLIGATORIA** antes de la sección de familia | UX: el beneficiario es el sujeto migratorio. Sin él, el expediente es inválido. La sección se distingue visualmente de los companions familiares. |
| D11 | **`Companion::relationship='beneficiary'` determina el rol del empleado** — UI adapta etiquetas | Evita crear un modelo nuevo. El campo `relationship` ya existe y es el discriminador natural. |
| D12 | **`FolderService` usa `$case->primaryApplicant()->last_name . ', ' . $case->primaryApplicant()->first_name`** para la carpeta raíz del caso | Para empresa, la carpeta es del empleado (el que immigra), no de la empresa patrocinadora. |
| D13 | **Spec 59 (promoción) siempre asigna `type='person'`** — companions son personas por definición | Sin elección en el flujo de promoción — es automático y correcto. |

---

## 1. Base de Datos

### 1.1 Migración — Columnas `type` y campos empresa en `clients`

```php
<?php
// database/migrations/2026_05_25_000001_add_company_fields_to_clients.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Discriminador STI — backfill automático 'person' para registros existentes
            $table->enum('type', ['person', 'company'])
                  ->default('person')
                  ->after('tenant_id');

            // Campos exclusivos de empresa (todos nullable)
            $table->string('company_name', 255)->nullable()->after('type');
            $table->string('trade_name', 255)->nullable()->after('company_name');
            $table->string('tax_id', 50)->nullable()->after('trade_name');
            $table->string('industry', 100)->nullable()->after('tax_id');
            $table->string('website', 255)->nullable()->after('industry');
            $table->string('legal_rep_name', 255)->nullable()->after('website');
            $table->string('legal_rep_title', 100)->nullable()->after('legal_rep_name');

            // Campos de persona que ahora son nullable (empresas no los tienen)
            // NOTA: Si existen como NOT NULL, modificar. Si ya son nullable, omitir.
            $table->string('first_name', 255)->nullable()->change();
            $table->string('last_name', 255)->nullable()->change();

            // Índices
            $table->index(['tenant_id', 'type'], 'idx_clients_tenant_type');
            $table->index(['tenant_id', 'tax_id'], 'idx_clients_tenant_tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_tenant_type');
            $table->dropIndex('idx_clients_tenant_tax_id');
            $table->dropColumn([
                'type', 'company_name', 'trade_name', 'tax_id',
                'industry', 'website', 'legal_rep_name', 'legal_rep_title',
            ]);
            // Revertir nullable no es seguro si hay filas — omitir en down() intencionalmente
        });
    }
};
```

### 1.2 Migración — Columna virtual `display_name` + FULLTEXT

```php
<?php
// database/migrations/2026_05_25_000002_add_display_name_to_clients.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL 8.0: columna generada STORED (indexable)
        // STORED permite FULLTEXT y ORDER BY sin calcular en cada query
        DB::statement("
            ALTER TABLE clients
            ADD COLUMN display_name VARCHAR(255)
                GENERATED ALWAYS AS (
                    CASE
                        WHEN type = 'company'
                            THEN COALESCE(trade_name, company_name, '')
                        ELSE
                            TRIM(CONCAT(
                                COALESCE(first_name, ''), ' ',
                                COALESCE(last_name, '')
                            ))
                    END
                ) STORED
        ");

        DB::statement("
            ALTER TABLE clients
            ADD FULLTEXT INDEX ft_clients_display_name (display_name)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE clients DROP INDEX ft_clients_display_name');
        DB::statement('ALTER TABLE clients DROP COLUMN display_name');
    }
};
```

**Por qué `STORED` y no `VIRTUAL`:** MySQL no permite índices FULLTEXT sobre columnas `VIRTUAL`. `STORED` materializa el valor en disco (costo mínimo: ~255 bytes por fila) pero habilita indexación completa.

### 1.3 Migración — Agregar `'beneficiary'` a `companions.relationship`

```php
<?php
// database/migrations/2026_05_25_000003_add_beneficiary_to_companions_relationship.php
// Solo si companions.relationship es un ENUM. Si es VARCHAR, agregar validación en Request únicamente.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar tipo actual antes de modificar
        // Si es VARCHAR: esta migración no es necesaria (solo añadir validación en Request)
        DB::statement("
            ALTER TABLE companions
            MODIFY COLUMN relationship
                ENUM('spouse','child','parent','sibling','other','beneficiary')
                NOT NULL DEFAULT 'other'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE companions
            MODIFY COLUMN relationship
                ENUM('spouse','child','parent','sibling','other')
                NOT NULL DEFAULT 'other'
        ");
    }
};
```

**Atención G2:** Si `companions.relationship` tiene un índice o FK que lo referencia, seguir el patrón: `dropForeign → dropIndex → MODIFY → recrear FK`.

### 1.4 Migración — Permisos (Gotcha G4 — siempre en migración)

```php
<?php
// database/migrations/2026_05_25_000004_add_company_client_permissions.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos diferenciados por tipo de cliente
        $viewCompany   = Permission::firstOrCreate(['name' => 'clients.view_company',   'guard_name' => 'web']);
        $createCompany = Permission::firstOrCreate(['name' => 'clients.create_company', 'guard_name' => 'web']);
        $editCompany   = Permission::firstOrCreate(['name' => 'clients.edit_company',   'guard_name' => 'web']);

        // Roles que pueden gestionar clientes empresa
        foreach (['super-admin', 'admin', 'consultor'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $role->givePermissionTo([$viewCompany, $createCompany, $editCompany]);
        }

        // 'apoyo' solo puede ver empresas, no crearlas/editarlas
        $apoyo = Role::findByName('apoyo', 'web');
        $apoyo->givePermissionTo([$viewCompany]);
    }

    public function down(): void
    {
        Permission::where('name', 'like', 'clients.%_company')->delete();
    }
};
```

### 1.5 Esquema resultante de `clients` (columnas relevantes)

```sql
clients
  id                BIGINT UNSIGNED PK
  tenant_id         BIGINT UNSIGNED FK → tenants.id
  type              ENUM('person','company') NOT NULL DEFAULT 'person'   -- STI discriminador
  first_name        VARCHAR(255) NULL                                    -- nullable para empresa
  last_name         VARCHAR(255) NULL                                    -- nullable para empresa
  company_name      VARCHAR(255) NULL                                    -- empresa: razón social
  trade_name        VARCHAR(255) NULL                                    -- empresa: nombre comercial
  tax_id            VARCHAR(50)  NULL                                    -- empresa: RFC/CUIT/EIN
  industry          VARCHAR(100) NULL
  website           VARCHAR(255) NULL
  legal_rep_name    VARCHAR(255) NULL
  legal_rep_title   VARCHAR(100) NULL
  display_name      VARCHAR(255) GENERATED ALWAYS AS (...) STORED        -- columna virtual
  email             VARCHAR(255) ...
  phone             VARCHAR(30)  ...
  -- resto de columnas comunes sin cambios --
  INDEX idx_clients_tenant_type (tenant_id, type)
  INDEX idx_clients_tenant_tax_id (tenant_id, tax_id)
  FULLTEXT ft_clients_display_name (display_name)
```

---

## 2. Backend — Modelos Eloquent

### 2.1 `Client` — cambios

```php
// app/Models/Client.php

// Agregar a $fillable:
'type',
'company_name',
'trade_name',
'tax_id',
'industry',
'website',
'legal_rep_name',
'legal_rep_title',

// Agregar a $casts:
'type' => 'string',

// Agregar constantes:
public const TYPE_PERSON  = 'person';
public const TYPE_COMPANY = 'company';

// Agregar scopes:
public function scopeOfType($query, ?string $type): mixed
{
    if (is_null($type) || $type === 'all') return $query;
    return $query->where('type', $type);
}

public function scopePersons($query): mixed
{
    return $query->where('type', self::TYPE_PERSON);
}

public function scopeCompanies($query): mixed
{
    return $query->where('type', self::TYPE_COMPANY);
}

// Agregar accessors de conveniencia:
public function getIsCompanyAttribute(): bool
{
    return $this->type === self::TYPE_COMPANY;
}

public function getIsPersonAttribute(): bool
{
    return $this->type === self::TYPE_PERSON;
}

// NOTA: display_name ya es accesible como columna generada — no requiere accessor PHP.
// $client->display_name retorna el valor calculado por MySQL.
```

### 2.2 `Companion` — agregar constante de relación

```php
// app/Models/Companion.php

// Agregar constante:
public const RELATIONSHIP_BENEFICIARY = 'beneficiary';

// Agregar scope:
public function scopeBeneficiary($query): mixed
{
    return $query->where('relationship', self::RELATIONSHIP_BENEFICIARY);
}
```

---

## 3. Backend — Service Layer

### 3.1 `ClientService` — adaptaciones

```php
// app/Services/Client/ClientService.php

// En create(): asegurar que campos del tipo opuesto queden en null antes de guardar
public function create(array $data, int $tenantId): Client
{
    $type = $data['type'] ?? Client::TYPE_PERSON;
    
    // Limpiar campos que no corresponden al tipo seleccionado
    if ($type === Client::TYPE_COMPANY) {
        $data = array_merge($data, [
            'first_name'      => null,
            'last_name'       => null,
            'date_of_birth'   => null,
            'marital_status'  => null,
            'nationality'     => null,
        ]);
    } else {
        $data = array_merge($data, [
            'company_name'    => null,
            'trade_name'      => null,
            'tax_id'          => null,
            'industry'        => null,
            'website'         => null,
            'legal_rep_name'  => null,
            'legal_rep_title' => null,
        ]);
    }

    $client = new Client($data);
    $client->tenant_id = $tenantId;
    $client->save();
    return $client;
}

// En update(): bloquear cambio de type
public function update(Client $client, array $data): Client
{
    if (isset($data['type']) && $data['type'] !== $client->type) {
        throw new \App\Exceptions\BusinessException(
            __('clients.validation.cannot_change_type')
        );
    }
    unset($data['type']); // nunca actualizar type post-creación
    $client->update($data);
    return $client;
}

// search(): usar display_name en lugar de first_name/last_name LIKE
public function search(string $term, ?string $type = null, int $tenantId): \Illuminate\Support\Collection
{
    return Client::query()
        ->where('tenant_id', $tenantId)
        ->ofType($type)
        ->where(function ($q) use ($term) {
            $q->whereRaw('MATCH(display_name) AGAINST (? IN BOOLEAN MODE)', [$term . '*'])
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('iuc', 'like', "%{$term}%")
              ->orWhere('tax_id', 'like', "%{$term}%");
        })
        ->orderBy('display_name')
        ->limit(50)
        ->get();
}
```

### 3.2 `CompanionService` — NO bloquear para empresa; validar beneficiario

```php
// app/Services/Companion/CompanionService.php

// REMOVER cualquier lógica que bloquee companions para clientes empresa.
// Para empresa: el companion con relationship='beneficiary' es el empleado.

// Agregar validación: un case de empresa DEBE tener exactamente un beneficiario
public function validateBeneficiaryForCompanyCase(ImmigrationCase $case): void
{
    if ($case->client->type !== Client::TYPE_COMPANY) return;

    $hasBeneficiary = $case->companions()
        ->where('relationship', Companion::RELATIONSHIP_BENEFICIARY)
        ->exists();

    if (!$hasBeneficiary) {
        throw new \App\Exceptions\BusinessException(
            __('cases.validation.company_case_requires_beneficiary')
        );
    }
}
```

### 3.3 `ClientPromotionService` (Spec 59) — forzar `type='person'`

```php
// app/Services/Companion/ClientPromotionService.php

// En el método promote(), al construir el payload del nuevo cliente:
$clientData = [
    'type'       => Client::TYPE_PERSON,  // ← agregar explícito
    'first_name' => $companion->first_name,
    'last_name'  => $companion->last_name,
    // ...resto de campos persona...
];
```

### 3.4 `FolderService` — usar `primaryApplicant()` para nombre de carpeta

```php
// app/Services/Document/FolderService.php

// Reemplazar lógica que usa $case->client->last_name directamente:
private function buildCaseFolderName(ImmigrationCase $case): string
{
    $applicant = $case->primaryApplicant();
    
    $lastName  = $applicant->last_name  ?? '';
    $firstName = $applicant->first_name ?? '';
    
    $name = trim("{$lastName}, {$firstName}") ?: ($case->client->display_name ?? "Case-{$case->id}");
    
    return FolderNameValidator::sanitize($name);
}
```

### 3.5 `GlobalSearchService` — buscar por `display_name`

```php
// app/Services/Search/GlobalSearchService.php

// En la query de clientes, reemplazar:
// ->where(fn($q) => $q->where('first_name', 'like', ...) ->orWhere('last_name', 'like', ...))
// Por:
->where(function ($q) use ($term) {
    $q->whereRaw('MATCH(c.display_name) AGAINST (? IN BOOLEAN MODE)', [$term . '*'])
      ->orWhere('c.email', 'like', "%{$term}%")
      ->orWhere('c.tax_id', 'like', "%{$term}%");
})
// Asegurar que la query seleccione c.display_name y c.type para el resultado
```

---

## 4. Backend — Requests / Validación

### 4.1 Dispatcher `StoreClientRequest`

```php
<?php
// app/Http/Requests/Client/StoreClientRequest.php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $type = $this->input('type', 'person');

        if ($type === 'company') {
            return (new StoreCompanyClientRequest())->rules();
        }
        return (new StorePersonClientRequest())->rules();
    }
}
```

### 4.2 `StorePersonClientRequest`

```php
<?php
// app/Http/Requests/Client/StorePersonClientRequest.php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type'         => ['required', Rule::in(['person'])],
            'first_name'   => ['required', 'string', 'max:255'],
            'last_name'    => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'date_of_birth'=> ['nullable', 'date'],
            'marital_status'=> ['nullable', 'string', 'max:50'],
            'nationality'  => ['nullable', 'string', 'max:100'],
            'iuc'          => ['nullable', 'string', 'max:50'],
            'address'      => ['nullable', 'string', 'max:500'],
            'notes'        => ['nullable', 'string', 'max:2000'],

            // Campos de empresa: prohibidos
            'company_name'   => ['prohibited'],
            'trade_name'     => ['prohibited'],
            'tax_id'         => ['prohibited'],
            'industry'       => ['prohibited'],
            'website'        => ['prohibited'],
            'legal_rep_name' => ['prohibited'],
            'legal_rep_title'=> ['prohibited'],
        ];
    }
}
```

### 4.3 `StoreCompanyClientRequest`

```php
<?php
// app/Http/Requests/Client/StoreCompanyClientRequest.php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyClientRequest extends FormRequest
{
    public function rules(): array
    {
        $tenantId = app('tenant')->id;

        return [
            'type'          => ['required', Rule::in(['company'])],
            'company_name'  => ['required', 'string', 'max:255'],
            'trade_name'    => ['nullable', 'string', 'max:255'],
            'tax_id'        => [
                'required', 'string', 'max:50',
                Rule::unique('clients')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'industry'      => ['nullable', 'string', 'max:100'],
            'website'       => ['nullable', 'url', 'max:255'],
            'legal_rep_name'=> ['nullable', 'string', 'max:255'],
            'legal_rep_title'=> ['nullable', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string', 'max:500'],
            'notes'         => ['nullable', 'string', 'max:2000'],

            // Campos de persona: prohibidos
            'first_name'    => ['prohibited'],
            'last_name'     => ['prohibited'],
            'date_of_birth' => ['prohibited'],
            'marital_status'=> ['prohibited'],
            'nationality'   => ['prohibited'],
        ];
    }
}
```

### 4.4 `UpdateClientRequest` (dispatcher — mismo patrón)

```php
// app/Http/Requests/Client/UpdateClientRequest.php

public function rules(): array
{
    // Resolver tipo desde el registro existente, no desde el input (type es inmutable)
    $client = $this->route('client');
    $type   = $client?->type ?? $this->input('type', 'person');

    if ($type === 'company') {
        $rules = (new UpdateCompanyClientRequest())->rules();
    } else {
        $rules = (new UpdatePersonClientRequest())->rules();
    }

    // Bloquear siempre el cambio de type
    $rules['type'] = ['sometimes', Rule::in([$type])];
    return $rules;
}
```

### 4.5 Validación adicional en `StoreCaseRequest` para expedientes de empresa

```php
// app/Http/Requests/Case/StoreCaseRequest.php — agregar regla:

// Si el client es tipo company, el companion beneficiario es obligatorio
'companion_ids' => [
    'sometimes', 'array',
    function ($attribute, $value, $fail) {
        $client = Client::find($this->input('client_id'));
        if ($client?->type === Client::TYPE_COMPANY) {
            $hasBeneficiary = Companion::whereIn('id', $value ?? [])
                ->where('relationship', Companion::RELATIONSHIP_BENEFICIARY)
                ->exists();
            if (!$hasBeneficiary) {
                $fail(__('cases.validation.company_case_requires_beneficiary'));
            }
        }
    },
],
```

---

## 5. Backend — API Layer / Resources

### 5.1 `ClientResource` — campos condicionales por tipo

```php
// app/Http/Resources/ClientResource.php

public function toArray($request): array
{
    return [
        'id'           => $this->id,
        'type'         => $this->type,
        'display_name' => $this->display_name,
        'email'        => $this->email,
        'phone'        => $this->phone,
        'address'      => $this->address,
        'iuc'          => $this->iuc,
        'status'       => $this->status,

        // Persona
        'first_name'    => $this->when($this->type === 'person', $this->first_name),
        'last_name'     => $this->when($this->type === 'person', $this->last_name),
        'date_of_birth' => $this->when($this->type === 'person', $this->date_of_birth?->format('Y-m-d')),
        'marital_status'=> $this->when($this->type === 'person', $this->marital_status),
        'nationality'   => $this->when($this->type === 'person', $this->nationality),

        // Empresa
        'company_name'   => $this->when($this->type === 'company', $this->company_name),
        'trade_name'     => $this->when($this->type === 'company', $this->trade_name),
        'tax_id'         => $this->when($this->type === 'company', $this->tax_id),
        'industry'       => $this->when($this->type === 'company', $this->industry),
        'website'        => $this->when($this->type === 'company', $this->website),
        'legal_rep_name' => $this->when($this->type === 'company', $this->legal_rep_name),
        'legal_rep_title'=> $this->when($this->type === 'company', $this->legal_rep_title),

        'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
    ];
}
```

### 5.2 `ImmigrationCaseResource` — `client.display_name` y `primary_applicant`

```php
// app/Http/Resources/ImmigrationCaseResource.php
// Asegurar que el eager load incluya display_name (es columna, no relación)
// y que el primary_applicant sea el companion-beneficiario para casos de empresa

'client' => $this->when($this->relationLoaded('client'), fn () => [
    'id'           => $this->client?->id,
    'type'         => $this->client?->type,
    'display_name' => $this->client?->display_name,
]),

'primary_applicant' => $this->when(
    $this->relationLoaded('primaryApplicantCompanion') || $this->relationLoaded('client'),
    function () {
        if ($this->primary_applicant_type === 'companion' && $this->primaryApplicantCompanion) {
            return new CompanionResource($this->primaryApplicantCompanion);
        }
        return new ClientResource($this->client);
    }
),
```

### 5.3 `ClientController` — aceptar filtro `?type=`

```php
// app/Http/Controllers/Api/ClientController.php

public function index(Request $request): AnonymousResourceCollection
{
    $type    = $request->query('type');    // 'person' | 'company' | null (todos)
    $search  = $request->query('search');
    $perPage = min((int) $request->query('per_page', 15), 100);

    $clients = Client::query()
        ->ofType($type)
        ->when($search, fn ($q) =>
            $q->whereRaw('MATCH(display_name) AGAINST (? IN BOOLEAN MODE)', [$search . '*'])
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('iuc', 'like', "%{$search}%")
              ->orWhere('tax_id', 'like', "%{$search}%")
        )
        ->orderBy('display_name')
        ->paginate($perPage);

    return ClientResource::collection($clients);
}
```

---

## 6. Frontend — Wizard (Casos Empresa)

### 6.1 Estado del Wizard — campos adicionales

```typescript
// En el composable/store que gestiona el estado del wizard:
// Agregar al state:
clientType: null as 'person' | 'company' | null,
beneficiaryCompanionId: null as number | null,
```

### 6.2 `StepClient.vue` — mostrar nota de rol para empresa

Cuando el cliente seleccionado es `type='company'`, agregar un aviso informativo bajo el `ClientCard`:

```vue
<div v-if="selectedClient?.type === 'company'"
     class="mt-3 p-3 bg-info/10 border border-info/20 rounded-lg text-sm text-info">
    <icon-info-circle class="inline w-4 h-4 ltr:mr-1 rtl:ml-1" />
    {{ $t('wizard.step2.company_sponsor_note') }}
</div>
```

### 6.3 `StepCompanions.vue` — sección "Empleado Beneficiario" para empresa

Cuando `wizard.clientType === 'company'`, renderizar una sección especial ANTES de los companions familiares:

```vue
<template>
    <!-- SECCIÓN BENEFICIARIO (solo para empresa) -->
    <div v-if="isCompanyClient"
         class="mb-6 p-4 border-2 border-primary/40 rounded-lg bg-primary/5">

        <h4 class="font-semibold text-gray-900 dark:text-white mb-1 flex items-center gap-2">
            <icon-user-check class="w-5 h-5 text-primary" />
            {{ $t('wizard.step3.beneficiary_title') }}
            <span class="text-danger">*</span>
        </h4>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            {{ $t('wizard.step3.beneficiary_desc') }}
        </p>

        <!-- Si no hay beneficiario seleccionado aún -->
        <div v-if="!beneficiaryCompanion">
            <button type="button"
                    class="btn btn-outline-primary btn-sm"
                    @click="openAddBeneficiaryModal">
                <icon-plus class="w-4 h-4 ltr:mr-1 rtl:ml-1" />
                {{ $t('wizard.step3.add_beneficiary') }}
            </button>
            <p v-if="validationAttempted && !beneficiaryCompanion"
               class="text-danger text-sm mt-1">
                {{ $t('wizard.step3.beneficiary_required') }}
            </p>
        </div>

        <!-- Beneficiario ya agregado -->
        <div v-else class="flex items-center justify-between p-3 bg-white dark:bg-dark rounded-lg shadow-sm">
            <div>
                <p class="font-medium">{{ beneficiaryCompanion.full_name }}</p>
                <p class="text-xs text-gray-500">{{ $t('companions.relationship.beneficiary') }}</p>
            </div>
            <button type="button" class="text-danger text-sm" @click="removeBeneficiary">
                {{ $t('common.remove') }}
            </button>
        </div>
    </div>

    <!-- SECCIÓN FAMILIA DEL EMPLEADO (opcional — misma UI que companions actuales) -->
    <div class="mt-4">
        <h4 class="font-semibold text-gray-900 dark:text-white mb-2">
            {{ isCompanyClient
               ? $t('wizard.step3.employee_family_title')
               : $t('wizard.step3.companions_title') }}
        </h4>
        <!-- Componente de companions existente -->
        <CompanionsList v-model="selectedCompanionIds" :exclude-ids="beneficiaryId ? [beneficiaryId] : []" />
    </div>
</template>
```

**Lógica en `<script setup>`:**

```typescript
const isCompanyClient = computed(() => wizard.clientType === 'company');

const beneficiaryCompanion = computed(() =>
    selectedCompanions.value.find(c => c.relationship === 'beneficiary') ?? null
);
const beneficiaryId = computed(() => beneficiaryCompanion.value?.id ?? null);

// Abrir modal con relationship pre-seleccionado en 'beneficiary'
function openAddBeneficiaryModal() {
    companionModal.open({ defaultRelationship: 'beneficiary', lockRelationship: true });
}

function removeBeneficiary() {
    if (!beneficiaryId.value) return;
    wizard.selectedCompanionIds = wizard.selectedCompanionIds.filter(id => id !== beneficiaryId.value);
}

// Bloqueo de avance: empresa requiere beneficiario
function canAdvance(): boolean {
    if (isCompanyClient.value) return !!beneficiaryCompanion.value;
    return true; // para personas, companions son opcionales
}

// Al agregar el beneficiario, también registrarlo como primary_applicant
watch(beneficiaryId, (id) => {
    if (id && isCompanyClient.value) {
        wizard.primaryApplicantType = 'companion';
        wizard.primaryApplicantCompanionId = id;
    }
});
```

### 6.4 `StepSummary.vue` — mostrar jerarquía empresa/empleado

```vue
<!-- Para expedientes de empresa, mostrar jerarquía clara -->
<section v-if="wizard.clientType === 'company'" class="mb-4">
    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">
        {{ $t('wizard.step7.company_sponsor') }}
    </h4>
    <p class="font-semibold">{{ selectedClient.display_name }}</p>
    <span class="badge badge-outline-success text-xs">{{ $t('clients.type.company') }}</span>
</section>

<section class="mb-4">
    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">
        {{ $t('wizard.step7.primary_applicant') }}
    </h4>
    <p class="font-semibold">{{ primaryApplicantName }}</p>
    <span class="badge badge-outline-primary text-xs">
        {{ wizard.clientType === 'company'
           ? $t('wizard.step7.employee_beneficiary')
           : $t('wizard.step7.primary_applicant') }}
    </span>
</section>
```

---

## 7. Frontend — CRUD de Clientes

### 7.1 Nuevos componentes

```
resources/js/src/components/clients/
├── ClientTypeSelectorModal.vue   (nuevo — paso previo a la creación)
├── PersonForm.vue                (nuevo — extraído de CreateClientModal actual)
├── CompanyForm.vue               (nuevo — formulario empresa)
├── ClientTypeBadge.vue           (nuevo — badge reutilizable Persona/Empresa)
└── CreateClientModal.vue         (refactor — wrapper que renderiza PersonForm o CompanyForm)
```

### 7.2 `ClientTypeSelectorModal.vue` — estructura

```vue
<template>
    <div class="modal-overlay">
        <div class="modal-box max-w-md">
            <h3 class="text-lg font-semibold mb-4">{{ $t('clients.type_selector.title') }}</h3>

            <div class="grid grid-cols-2 gap-4">
                <!-- Card Persona -->
                <button class="card-selector" @click="select('person')">
                    <icon-user class="w-10 h-10 text-primary mx-auto mb-2" />
                    <p class="font-semibold">{{ $t('clients.type.person') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $t('clients.type_selector.person_description') }}</p>
                </button>

                <!-- Card Empresa -->
                <button class="card-selector" @click="select('company')">
                    <icon-building class="w-10 h-10 text-success mx-auto mb-2" />
                    <p class="font-semibold">{{ $t('clients.type.company') }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $t('clients.type_selector.company_description') }}</p>
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
const emit = defineEmits<{ select: [type: 'person' | 'company'] }>();
function select(type: 'person' | 'company') { emit('select', type); }
</script>
```

### 7.3 `CompanyForm.vue` — campos corporativos

```vue
<template>
    <form @submit.prevent="$emit('submit', form)">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label>{{ $t('clients.fields.company_name') }} *</label>
                <input v-model="form.company_name" type="text" class="form-input" required />
            </div>
            <div>
                <label>{{ $t('clients.fields.trade_name') }}</label>
                <input v-model="form.trade_name" type="text" class="form-input" />
            </div>
            <div>
                <label>{{ $t('clients.fields.tax_id') }} *</label>
                <input v-model="form.tax_id" type="text" class="form-input" required />
            </div>
            <div>
                <label>{{ $t('clients.fields.industry') }}</label>
                <input v-model="form.industry" type="text" class="form-input" />
            </div>
            <div>
                <label>{{ $t('clients.fields.website') }}</label>
                <input v-model="form.website" type="url" class="form-input" />
            </div>
            <div>
                <label>{{ $t('clients.fields.legal_rep_name') }}</label>
                <input v-model="form.legal_rep_name" type="text" class="form-input" />
            </div>
            <div>
                <label>{{ $t('clients.fields.legal_rep_title') }}</label>
                <input v-model="form.legal_rep_title" type="text" class="form-input" />
            </div>
            <div>
                <label>{{ $t('common.email') }}</label>
                <input v-model="form.email" type="email" class="form-input" />
            </div>
            <div>
                <label>{{ $t('common.phone') }}</label>
                <input v-model="form.phone" type="tel" class="form-input" />
            </div>
        </div>
    </form>
</template>
```

### 7.4 `ClientTypeBadge.vue`

```vue
<template>
    <span :class="['badge text-xs', type === 'company' ? 'badge-outline-success' : 'badge-outline-info']">
        {{ type === 'company' ? $t('clients.type.company') : $t('clients.type.person') }}
    </span>
</template>
<script lang="ts" setup>
defineProps<{ type: 'person' | 'company' }>();
</script>
```

### 7.5 `views/clients/index.vue` — filtro por tipo + badge en nombre

```typescript
// Agregar al estado del listado (useListFilters o local):
const typeFilter = ref<'all' | 'person' | 'company'>('all');

// En la llamada al API:
clientService.list({ type: typeFilter.value !== 'all' ? typeFilter.value : undefined, ... });
```

```vue
<!-- Barra de herramientas: agregar segmentado de tipo -->
<div class="flex gap-1 border border-gray-200 dark:border-dark rounded-lg p-0.5">
    <button v-for="opt in ['all', 'person', 'company']" :key="opt"
            :class="['btn btn-sm', typeFilter === opt ? 'btn-primary' : 'btn-ghost']"
            @click="typeFilter = opt">
        {{ $t(`clients.filter.${opt}`) }}
    </button>
</div>

<!-- En la columna de nombre de la tabla: -->
<template #cell(display_name)="{ row }">
    <div class="flex items-center gap-2">
        <span>{{ row.display_name }}</span>
        <ClientTypeBadge :type="row.type" />
    </div>
</template>
```

### 7.6 `views/clients/show.vue` — adaptación condicional

```vue
<!-- Header condicional por tipo -->
<template v-if="client.type === 'company'">
    <h1>{{ client.display_name }}</h1>
    <p class="text-sm text-gray-500">{{ client.company_name }}</p>
    <ClientTypeBadge type="company" />

    <!-- Bloque representante legal -->
    <div v-if="client.legal_rep_name" class="mt-2 text-sm">
        <span class="text-gray-400">{{ $t('clients.fields.legal_rep_name') }}:</span>
        {{ client.legal_rep_name }}
        <span v-if="client.legal_rep_title" class="text-gray-400">({{ client.legal_rep_title }})</span>
    </div>

    <!-- Sección de Expedientes con nombre del Empleado -->
    <!-- El listado de cases ya muestra el case_number que incluye apellido del empleado -->
</template>

<template v-else>
    <!-- Vista persona — sin cambios -->
</template>

<!-- Tab de companions: visible para ambos tipos, pero con etiqueta diferente para empresa -->
<div v-if="activeTab === 'companions'">
    <h3>{{ client.type === 'company'
          ? $t('clients.tabs.sponsored_employees')
          : $t('clients.tabs.companions') }}</h3>
    <!-- Lista companions: para empresa, el beneficiario tiene badge especial -->
</div>
```

---

## 8. Internacionalización (i18n)

**Regla crítica (G6):** Keys FLAT. Nunca objetos JSON anidados. Solo `es.json`, `en.json`, `fr.json`.  
**Regla crítica (G10):** No usar `<` ni `>` directamente — escapar como `{'<'}` y `{'>'}`.

### 8.1 Keys a agregar

```json
"clients.type.person": "Persona",
"clients.type.company": "Empresa",
"clients.type_selector.title": "¿Qué tipo de cliente deseas crear?",
"clients.type_selector.person_description": "Persona física individual",
"clients.type_selector.company_description": "Empresa o persona jurídica patrocinadora",
"clients.filter.all": "Todos",
"clients.filter.person": "Personas",
"clients.filter.company": "Empresas",
"clients.fields.company_name": "Razón social",
"clients.fields.trade_name": "Nombre comercial",
"clients.fields.tax_id": "Identificación fiscal (RFC / CUIT / EIN)",
"clients.fields.industry": "Sector / industria",
"clients.fields.website": "Sitio web",
"clients.fields.legal_rep_name": "Representante legal",
"clients.fields.legal_rep_title": "Cargo del representante",
"clients.tabs.sponsored_employees": "Empleados Patrocinados",
"clients.validation.cannot_change_type": "El tipo de cliente no puede modificarse después de creado",
"cases.validation.company_case_requires_beneficiary": "Un expediente de empresa requiere al menos un empleado beneficiario",
"companions.relationship.beneficiary": "Empleado / Beneficiario",
"wizard.step2.company_sponsor_note": "Esta empresa actuará como patrocinadora. En el siguiente paso registrarás al empleado beneficiario y su familia.",
"wizard.step3.beneficiary_title": "Empleado Beneficiario",
"wizard.step3.beneficiary_desc": "Registra al empleado que será el sujeto del proceso migratorio. Este campo es obligatorio para expedientes de empresa.",
"wizard.step3.add_beneficiary": "Agregar empleado beneficiario",
"wizard.step3.beneficiary_required": "Debes registrar al empleado beneficiario antes de continuar.",
"wizard.step3.employee_family_title": "Integrantes de la Familia del Empleado (opcional)",
"wizard.step7.company_sponsor": "Empresa Patrocinadora",
"wizard.step7.employee_beneficiary": "Empleado Beneficiario",
"wizard.step7.primary_applicant": "Aplicante Principal"
```

Agregar equivalentes en `en.json` y `fr.json`.

---

## 9. TypeScript — Tipos Afectados

```typescript
// resources/js/src/types/client.ts

export interface Client {
    id: number;
    type: 'person' | 'company';
    display_name: string;
    email?: string;
    phone?: string;
    address?: string;
    iuc?: string;
    status: string;

    // Persona
    first_name?: string;
    last_name?: string;
    date_of_birth?: string;
    marital_status?: string;
    nationality?: string;

    // Empresa
    company_name?: string;
    trade_name?: string;
    tax_id?: string;
    industry?: string;
    website?: string;
    legal_rep_name?: string;
    legal_rep_title?: string;

    created_at?: string;
}

// resources/js/src/types/companion.ts — agregar relación beneficiario
export type CompanionRelationship = 
    'spouse' | 'child' | 'parent' | 'sibling' | 'other' | 'beneficiary';

// resources/js/src/types/case.ts — el ImmigrationCase ya tiene primary_applicant por Spec 58
// Solo asegurar que primary_applicant usa el tipo Client | Companion unificado
```

---

## 10. Impacto en el Resto del Sistema

| Área | Impacto | Acción requerida |
|---|---|---|
| `GlobalSearchService` | MEDIO | Reemplazar query de clientes por FULLTEXT sobre `display_name` |
| `Spec 57 (Radar Fechas)` | BAJO | `ImportantDateAlertResource` debe emitir `client_display_name` (no `first_name + last_name`) |
| `Spec 59 (Promoción)` | BAJO | Agregar `'type' => 'person'` explícito en `ClientPromotionService::promote()` |
| `Spec 56 (Folders Wizard)` | MEDIO | `FolderService::buildCaseFolderName()` debe usar `primaryApplicant()` no `client` directo |
| `DashboardController` | BAJO | Si muestra nombre del cliente en casos recientes → usar `client.display_name` |
| `Spec 63 (Hitos Dashboard)` | BAJO | `UpcomingMilestonesService::serialize()` usa `client->full_name` — cambiar a `client->display_name` |
| `Portal del cliente empresa` | BAJO | Usar `legal_rep_name` como nombre visible en el perfil del portal (MVP) |
| `Backup (Spec 38)` | BAJO | `TenantBackupService` exporta `clients` — verificar que incluye columnas nuevas |
| Ordenamiento en tablas | BAJO | Cualquier `:order-by="'last_name'"` en tablas de clientes → cambiar a `'display_name'` |

---

## 11. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| `first_name` / `last_name` `NOT NULL` en producción — `ALTER TABLE` falla | ALTA | ALTO | Migración 1.1 incluye `->nullable()->change()` explícito. Ejecutar en ventana de mantenimiento. |
| Columna `display_name` STORED en tabla con muchos registros — tiempo de migración | MEDIA | MEDIO | Ejecutar en horario de bajo tráfico. MySQL 8 reconstruye ~1M filas/min en InnoDB. |
| Wizard envía `primary_applicant_type='companion'` con ID del beneficiario, pero Spec 58 ya valida esto | BAJA | BAJO | El backend de Spec 58 valida que `primary_applicant_companion_id` pertenezca al case. Sin cambio requerido. |
| Companion-beneficiario eliminado del caso post-creación deja el case sin aplicante | BAJA | MEDIO | `nullOnDelete()` ya existe en `primary_applicant_companion_id`. Agregar validación en `CompanionController::destroy()`: si es beneficiario de empresa, rechazar con 422. |
| Búsqueda FULLTEXT no activa en MySQL < 8.0 (FTS sobre `STORED` requiere InnoDB) | BAJA | BAJO | Stack ya usa MySQL 8.0. Verificar con `SELECT VERSION()` antes del deploy. |
| Spec 59: companion promovido hereda `type='person'` — sin acción visual en UI | BAJA | BAJO | El flujo es automático. No hay opción de seleccionar tipo durante promoción — es correcto por diseño. |

---

## 12. Checklist de Implementación

### FASE 1 — Base de Datos (sin downtime · reversible)

- [x] **1.1** Ejecutar migración `2026_05_25_000001_add_company_fields_to_clients.php` — ✓ 274ms DONE
- [x] **1.2** Ejecutar migración `2026_05_25_000002_add_display_name_to_clients.php` — ✓ 65ms DONE
- [x] **1.3** ~~Migración `companions.relationship` ENUM~~ — `relationship` es `VARCHAR(50)`, no ENUM. Solo validación en Request. No se requiere migración.
- [x] **1.4** Ejecutar migración `2026_05_25_000003_add_company_client_permissions.php` — ✓ 100ms DONE

### FASE 2 — Backend: Modelos y Servicios

- [x] **2.1** `app/Models/Client.php` — `type`/campos empresa en `$fillable`, constantes `TYPE_PERSON/TYPE_COMPANY`, scopes `ofType()`/`persons()`/`companies()`, accessors `full_name`/`sort_name`/`initials` null-safe para empresa, `scopeSearch()` cubre campos empresa + `tax_id`
- [x] **2.2** `app/Models/Companion.php` — constante `RELATIONSHIP_BENEFICIARY = 'beneficiary'` y scope `scopeBeneficiaries()` ✓
- [x] **2.3** `app/Services/Client/ClientService.php` — `createClient()` null-out campos opuestos, `updateClient()` bloquea cambio de type
- [x] **2.4** `app/Services/Companion/ClientPromotionService.php` — `'type' => 'person'` en payload de promoción
- [x] **2.5** `app/Services/Document/FolderService.php` — rutas usan `case_number`, no nombre de cliente. Sin cambios requeridos ✓
- [x] **2.6** `app/Services/Search/GlobalSearchService.php` — búsqueda por `display_name` + `tax_id`; SELECT incluye `type`, `company_name`, `trade_name` ✓

### FASE 3 — Backend: Requests y Controller

- [x] **3.1** Crear `app/Http/Requests/Client/StorePersonClientRequest.php` ✓
- [x] **3.2** Crear `app/Http/Requests/Client/StoreCompanyClientRequest.php` ✓
- [x] **3.3** Crear `app/Http/Requests/Client/UpdatePersonClientRequest.php` ✓
- [x] **3.4** Crear `app/Http/Requests/Client/UpdateCompanyClientRequest.php` ✓
- [x] **3.5** `StoreClientRequest.php` refactorizado como dispatcher ✓
- [x] **3.6** `UpdateClientRequest.php` refactorizado como dispatcher + bloqueo de type ✓
- [ ] **3.7** `StoreCaseRequest.php` — validación empresa requiere companion beneficiario *(pendiente — Fase Wizard)*
- [ ] **3.8** `app/Http/Resources/ClientResource.php` — no existe; el controller serializa el modelo directamente. La columna `display_name` es accesible como campo del modelo. *(pendiente — si se necesita Resource dedicado)*
- [x] **3.10** `app/Http/Controllers/Api/ClientController.php` — `index()` acepta `?type=` filter; forwarding a repository ✓

### FASE 4 — Frontend: Vistas CRUD de Clientes

- [x] **4.1** `resources/js/src/components/icon/icon-building.vue` — ícono SVG edificio creado ✓
- [x] **4.4** *(Integrado en vistas)* — badge de tipo inline en `list.vue` y `show.vue` ✓
- [x] **4.6** `resources/js/src/views/clients/list.vue` — filtro segmentado Todos/Personas/Empresas, badge de tipo en columna nombre, avatar de empresa ✓
- [x] **4.7** `resources/js/src/views/clients/show.vue` — display condicional persona/empresa, tab companions renombrado para empresas ✓
- [x] **4.8** `resources/js/src/views/clients/edit.vue` — formulario condicional por tipo, bloqueo visual de cambio de tipo ✓
- [x] `resources/js/src/views/clients/create.vue` — selector de tipo previo, campos empresa/persona condicionales ✓

### FASE 5 — Frontend: Wizard *(pendiente — sprint siguiente)*

- [ ] **5.1** `StepClient.vue` — nota informativa cuando cliente es empresa *(pendiente baja prioridad)*
- [x] **5.2** `StepCompanions.vue` — sección "Empleado Beneficiario" obligatoria para empresa; auto-set `primary_applicant_type='companion'` ✓
- [x] **5.3** `StepSummary.vue` — jerarquía empresa/empleado/familia; computed `isCompanyCase` + `familyMembers` ✓

### FASE 6 — Stores y Services Frontend

- [x] **6.2** `resources/js/src/services/clientService.ts` — parámetro `type` en `getClients()` ✓
- [ ] **6.1** `resources/js/src/stores/useClientStore.ts` — flujo de creación tipado *(pendiente baja prioridad)*
- [x] **6.3** `composables/useCaseWizard.ts` — `clientType`, `setClientType()`, validación Step 3 para empresas ✓

### FASE 7 — i18n

- [x] **7.1** `resources/js/src/locales/es.json` — 25 keys FLAT agregadas ✓
- [x] **7.2** `resources/js/src/locales/en.json` — 25 keys FLAT agregadas ✓
- [x] **7.3** `resources/js/src/locales/fr.json` — 25 keys FLAT agregadas ✓

### FASE 8 — TypeScript

- [x] **8.1** `resources/js/src/types/client.ts` — `ClientType`, `display_name`, campos empresa, `first_name`/`last_name` nullable, `ClientFilters.type` ✓
- [x] **8.2** `resources/js/src/types/companion.ts` — `'beneficiary'` ya incluido en `RelationshipType` ✓

### FASE 9 — Cierre

- [x] **9.1** Build Vite: ✓ sin errores (9.57s — DT-09 completo)
- [ ] **9.2** Tests PHP *(pendiente — deuda técnica baja prioridad)*
- [ ] **9.3** Prueba manual flujo completo *(pendiente)*
- [x] **9.4** `spec/INDEX.md` actualizado con Spec 64
- [x] **9.5** `MEMORY.md` actualizado
- [x] **9.6** Deuda técnica DT-09 completada ✓

---

## 13. Notas de Implementación

**Verificación previa antes de iniciar:**
```bash
# Confirmar tipo actual de companions.relationship
php artisan tinker --execute="DB::select('DESCRIBE companions');"

# Confirmar que first_name / last_name son actualmente NOT NULL
php artisan tinker --execute="DB::select('SHOW COLUMNS FROM clients LIKE \'first_name\'');"

# Confirmar versión MySQL (debe ser 8.0+)
php artisan tinker --execute="DB::select('SELECT VERSION()');"
```

**Prueba de la columna virtual tras migración:**
```bash
php artisan tinker --execute="
\$c = App\Models\Client::first();
echo \$c->display_name; // debe mostrar 'Nombre Apellido' para persona existente
"
```

**No romper expedientes existentes:** Todos los casos existentes tienen `client.type='person'` (default de la migración) y `primary_applicant_type='client'` (default de Spec 58). El wizard y las vistas existentes continúan funcionando sin cambios para el flujo de persona.
