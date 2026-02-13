# Plan de Implementacion Backend: Epic 2.1 - Expedientes Core

## Metadata
- **Fecha:** 2026-02-10
- **Version:** 1.0
- **Epic:** 2.1 - Expedientes Core
- **Tipo:** Plan de Implementacion Backend
- **Tiempo Estimado Total:** 28 horas
- **Archivos a Crear:** 17
- **Archivos a Modificar:** 3

---

## Resumen de Subfases Backend

| Subfase | Nombre | Tiempo | Archivos | Status |
|---------|--------|--------|----------|--------|
| 1A | Modelo CaseType | 1h | 1 | PENDIENTE |
| 1B | Modelo ImmigrationCase | 2h | 1 | PENDIENTE |
| 1C | Interfaces de Repositorio | 1h | 2 | PENDIENTE |
| 1D | Implementaciones de Repositorio | 3h | 2 | PENDIENTE |
| 2A | CaseService | 4h | 1 | PENDIENTE |
| 2B | CasePolicy | 1.5h | 1 | PENDIENTE |
| 2C | Configuracion de Providers | 0.5h | 2 (modificar) | PENDIENTE |
| 3A | Form Requests | 2h | 3 | PENDIENTE |
| 3B | API Resources | 1.5h | 2 | PENDIENTE |
| 3C | CaseTypeController | 1h | 1 | PENDIENTE |
| 3D | CaseController | 3h | 1 | PENDIENTE |
| 3E | Rutas API | 0.5h | 1 (modificar) | PENDIENTE |
| 4A | Factories | 1.5h | 2 | PENDIENTE |
| 4B | Feature Tests | 5h | 1 | PENDIENTE |

---

## SUBFASE 1A: Modelo CaseType (1h)

### Archivo a Crear
```
app/Models/CaseType.php
```

### Dependencias
- Migracion `2026_02_08_221203_create_case_types_table.php` ejecutada

### Definicion del Modelo

#### Propiedades
```php
protected $fillable = [
    'tenant_id',
    'name',
    'code',
    'category',
    'description',
    'is_active',
];

protected $casts = [
    'is_active' => 'boolean',
];
```

#### Constantes
```php
public const CATEGORY_TEMPORARY = 'temporary_residence';
public const CATEGORY_PERMANENT = 'permanent_residence';
public const CATEGORY_HUMANITARIAN = 'humanitarian';

public const CATEGORY_LABELS = [
    self::CATEGORY_TEMPORARY => 'Residencia Temporal',
    self::CATEGORY_PERMANENT => 'Residencia Permanente',
    self::CATEGORY_HUMANITARIAN => 'Humanitario',
];
```

#### Relaciones
| Metodo | Tipo | Modelo Relacionado | Descripcion |
|--------|------|-------------------|-------------|
| `cases()` | HasMany | ImmigrationCase | Casos de este tipo |

#### Scopes
| Scope | Parametros | Descripcion |
|-------|------------|-------------|
| `scopeActive($query)` | ninguno | Filtra solo tipos activos |
| `scopeByCategory($query, $category)` | string $category | Filtra por categoria |
| `scopeGlobalOrTenant($query, $tenantId)` | int $tenantId | Tipos globales (tenant_id null) o del tenant |

#### Accessors
| Accessor | Retorno | Descripcion |
|----------|---------|-------------|
| `getCategoryLabelAttribute()` | string | Etiqueta en espanol de la categoria |

### Criterios de Aceptacion
- [ ] Modelo tiene todos los fillable definidos segun migracion
- [ ] Constantes de categorias definidas con labels en espanol
- [ ] Scope `active()` filtra `is_active = true`
- [ ] Scope `byCategory()` filtra por categoria correctamente
- [ ] Scope `globalOrTenant()` retorna tipos donde tenant_id IS NULL OR tenant_id = $tenantId
- [ ] Relacion `cases()` definida correctamente

---

## SUBFASE 1B: Modelo ImmigrationCase (2h)

### Archivo a Crear
```
app/Models/ImmigrationCase.php
```

### Nota Importante
El modelo se llama `ImmigrationCase` porque `case` es palabra reservada en PHP. Debe configurar `protected $table = 'cases'`.

### Dependencias
- Subfase 1A completada (CaseType)
- Modelo Client existente
- Modelo User existente
- Migracion `2026_02_08_221204_create_cases_table.php` ejecutada

### Definicion del Modelo

#### Configuracion Base
```php
use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

protected $table = 'cases';
```

#### Propiedades
```php
protected $fillable = [
    'tenant_id',
    'case_number',
    'client_id',
    'case_type_id',
    'assigned_to',
    'status',
    'priority',
    'progress',
    'language',
    'description',
    'hearing_date',
    'fda_deadline',
    'brown_sheet_date',
    'evidence_deadline',
    'archive_box_number',
    'closed_at',
    'closure_notes',
];

protected $casts = [
    'hearing_date' => 'date',
    'fda_deadline' => 'date',
    'brown_sheet_date' => 'date',
    'evidence_deadline' => 'date',
    'closed_at' => 'date',
    'progress' => 'integer',
];
```

#### Constantes
```php
// Estados
public const STATUS_ACTIVE = 'active';
public const STATUS_INACTIVE = 'inactive';
public const STATUS_ARCHIVED = 'archived';
public const STATUS_CLOSED = 'closed';

public const STATUS_LABELS = [
    self::STATUS_ACTIVE => 'Activo',
    self::STATUS_INACTIVE => 'Inactivo',
    self::STATUS_ARCHIVED => 'Archivado',
    self::STATUS_CLOSED => 'Cerrado',
];

// Prioridades
public const PRIORITY_URGENT = 'urgent';
public const PRIORITY_HIGH = 'high';
public const PRIORITY_MEDIUM = 'medium';
public const PRIORITY_LOW = 'low';

public const PRIORITY_LABELS = [
    self::PRIORITY_URGENT => 'Urgente',
    self::PRIORITY_HIGH => 'Alta',
    self::PRIORITY_MEDIUM => 'Media',
    self::PRIORITY_LOW => 'Baja',
];
```

#### Relaciones
| Metodo | Tipo | Modelo Relacionado | Descripcion |
|--------|------|-------------------|-------------|
| `client()` | BelongsTo | Client | Cliente del caso |
| `caseType()` | BelongsTo | CaseType | Tipo de caso |
| `assignedTo()` | BelongsTo | User | Usuario asignado (nullable) |

#### Scopes
| Scope | Parametros | Descripcion |
|-------|------------|-------------|
| `scopeActive($query)` | ninguno | status = 'active' |
| `scopeByStatus($query, $status)` | string $status | Filtra por status |
| `scopeByPriority($query, $priority)` | string $priority | Filtra por prioridad |
| `scopeByAssignee($query, $userId)` | int $userId | Filtra por usuario asignado |
| `scopeByCaseType($query, $caseTypeId)` | int $caseTypeId | Filtra por tipo de caso |
| `scopeByClient($query, $clientId)` | int $clientId | Filtra por cliente |
| `scopeSearch($query, $search)` | string $search | Busca en case_number y description |
| `scopeUpcoming($query, $days)` | int $days = 30 | Casos con hearing_date en los proximos N dias |

#### Accessors
| Accessor | Retorno | Descripcion |
|----------|---------|-------------|
| `getStatusLabelAttribute()` | string | Etiqueta del status en espanol |
| `getPriorityLabelAttribute()` | string | Etiqueta de la prioridad en espanol |
| `getProgressPercentageAttribute()` | string | Progreso formateado como "X%" |
| `getDaysUntilHearingAttribute()` | ?int | Dias hasta la audiencia (null si no hay fecha) |

#### Activity Log Options
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly([
            'status', 'priority', 'progress', 'assigned_to',
            'hearing_date', 'description',
        ])
        ->logOnlyDirty()
        ->useLogName('cases')
        ->dontSubmitEmptyLogs();
}
```

### Criterios de Aceptacion
- [ ] Modelo usa `protected $table = 'cases'`
- [ ] Traits BelongsToTenant, HasFactory, LogsActivity, SoftDeletes aplicados
- [ ] Todos los campos de la migracion en fillable
- [ ] Casts para fechas y progress definidos
- [ ] Constantes STATUS_* y PRIORITY_* con labels en espanol
- [ ] Relaciones client(), caseType(), assignedTo() funcionan
- [ ] Todos los scopes implementados y filtran correctamente
- [ ] Accessors retornan valores esperados
- [ ] Activity log registra cambios en campos clave

---

## SUBFASE 1C: Interfaces de Repositorio (1h)

### Archivos a Crear
```
app/Repositories/Contracts/CaseTypeRepositoryInterface.php
app/Repositories/Contracts/CaseRepositoryInterface.php
```

### CaseTypeRepositoryInterface

#### Metodos
| Metodo | Parametros | Retorno | Descripcion |
|--------|------------|---------|-------------|
| `findById(int $id)` | int | ?CaseType | Buscar por ID |
| `getActive(int $tenantId)` | int | Collection | Tipos activos del tenant |
| `getByCategory(string $category, int $tenantId)` | string, int | Collection | Tipos por categoria |
| `findByCode(string $code)` | string | ?CaseType | Buscar por codigo |

### CaseRepositoryInterface

#### Metodos
| Metodo | Parametros | Retorno | Descripcion |
|--------|------------|---------|-------------|
| `findById(int $id)` | int | ?ImmigrationCase | Buscar por ID |
| `paginate(array $filters, int $perPage)` | array, int | LengthAwarePaginator | Listar con filtros y paginacion |
| `create(array $data)` | array | ImmigrationCase | Crear caso |
| `update(ImmigrationCase $case, array $data)` | ImmigrationCase, array | ImmigrationCase | Actualizar caso |
| `delete(ImmigrationCase $case)` | ImmigrationCase | bool | Eliminar caso (soft delete) |
| `getByClient(int $clientId)` | int | Collection | Casos de un cliente |
| `countByStatus(string $status)` | string | int | Contar por status |
| `countByPriority(string $priority)` | string | int | Contar por prioridad |
| `getNextSequence(CaseType $caseType, int $year)` | CaseType, int | int | Siguiente secuencia para case_number |
| `existsByCaseNumber(string $caseNumber)` | string | bool | Verificar si existe case_number |
| `getUpcomingHearings(int $days)` | int | Collection | Casos con audiencias proximas |
| `getStatistics()` | ninguno | array | Estadisticas generales |

### Criterios de Aceptacion
- [ ] Interfaces definen todos los metodos necesarios
- [ ] Tipos de retorno correctamente especificados
- [ ] Documentacion PHPDoc en cada metodo

---

## SUBFASE 1D: Implementaciones de Repositorio (3h)

### Archivos a Crear
```
app/Repositories/Eloquent/CaseTypeRepository.php
app/Repositories/Eloquent/CaseTypeRepository.php
app/Repositories/Eloquent/CaseRepository.php
```

### CaseTypeRepository

#### Implementacion de Metodos
| Metodo | Logica Principal |
|--------|------------------|
| `findById` | `CaseType::find($id)` |
| `getActive` | `CaseType::active()->globalOrTenant($tenantId)->orderBy('name')->get()` |
| `getByCategory` | `CaseType::active()->byCategory($category)->globalOrTenant($tenantId)->get()` |
| `findByCode` | `CaseType::where('code', $code)->first()` |

### CaseRepository

#### Implementacion de Metodos
| Metodo | Logica Principal |
|--------|------------------|
| `findById` | `ImmigrationCase::with(['client', 'caseType', 'assignedTo'])->find($id)` |
| `paginate` | Query builder con filtros dinamicos, tenant scope automatico |
| `create` | Asignar tenant_id del usuario autenticado, crear registro |
| `update` | `$case->update($data); return $case->fresh()` |
| `delete` | `$case->delete()` (soft delete) |
| `getByClient` | `ImmigrationCase::byClient($clientId)->orderByDesc('created_at')->get()` |
| `countByStatus` | `ImmigrationCase::byStatus($status)->count()` |
| `countByPriority` | `ImmigrationCase::byPriority($priority)->count()` |
| `getNextSequence` | Query MAX de case_number para el tipo y anio, +1 |
| `existsByCaseNumber` | `ImmigrationCase::where('case_number', $caseNumber)->exists()` |
| `getUpcomingHearings` | `ImmigrationCase::upcoming($days)->orderBy('hearing_date')->get()` |
| `getStatistics` | Agregar conteos por status y prioridad |

#### Filtros Soportados en paginate()
| Filtro | Campo | Operador |
|--------|-------|----------|
| `search` | case_number, description | LIKE |
| `status` | status | = |
| `priority` | priority | = |
| `case_type_id` | case_type_id | = |
| `assigned_to` | assigned_to | = |
| `client_id` | client_id | = |
| `hearing_from` | hearing_date | >= |
| `hearing_to` | hearing_date | <= |
| `sort_by` | campo dinamico | ORDER BY |
| `sort_direction` | asc/desc | ASC/DESC |

#### Eager Loading en paginate()
- client (solo id, first_name, last_name)
- caseType (solo id, name, code, category)
- assignedTo (solo id, name)

### Criterios de Aceptacion
- [ ] Repositorios implementan sus interfaces correctamente
- [ ] Tenant isolation aplicado automaticamente via BelongsToTenant
- [ ] Eager loading optimizado (solo campos necesarios)
- [ ] Filtros dinamicos funcionan correctamente
- [ ] Paginacion incluye metadata correcta
- [ ] getNextSequence genera secuencia unica por tipo/anio

---

## SUBFASE 2A: CaseService (4h)

### Archivo a Crear
```
app/Services/Case/CaseService.php
```

### Dependencias
- Subfase 1C y 1D completadas (Repositorios)

### Constructor
```php
public function __construct(
    private CaseRepositoryInterface $caseRepository,
    private CaseTypeRepositoryInterface $caseTypeRepository
) {}
```

### Metodos Publicos

| Metodo | Parametros | Retorno | Descripcion |
|--------|------------|---------|-------------|
| `listCases` | array $filters, int $perPage = 15 | LengthAwarePaginator | Listar con filtros |
| `getCase` | ImmigrationCase $case | ImmigrationCase | Obtener con relaciones |
| `createCase` | array $data | ImmigrationCase | Crear caso con case_number generado |
| `updateCase` | ImmigrationCase $case, array $data | ImmigrationCase | Actualizar caso |
| `deleteCase` | ImmigrationCase $case | void | Eliminar caso |
| `assignCase` | ImmigrationCase $case, int $userId | ImmigrationCase | Asignar a usuario |
| `updateStatus` | ImmigrationCase $case, string $status | ImmigrationCase | Cambiar status |
| `closeCase` | ImmigrationCase $case, string $notes | ImmigrationCase | Cerrar con notas |
| `getTimeline` | ImmigrationCase $case | Collection | Activity log del caso |
| `getStatistics` | ninguno | array | Estadisticas dashboard |

### Metodos Privados

| Metodo | Parametros | Retorno | Descripcion |
|--------|------------|---------|-------------|
| `generateCaseNumber` | CaseType $caseType | string | Generar numero unico |

#### Logica de generateCaseNumber
```
Formato: {YEAR}-{TYPE_CODE}-{SEQUENCE}
Ejemplo: 2026-ASYLUM-00042

1. Obtener anio actual
2. Obtener codigo del tipo (ej: "ASYLUM")
3. Obtener siguiente secuencia via repository
4. Formatear secuencia con ceros a la izquierda (5 digitos)
5. Retornar concatenacion
```

### Logica de Negocio por Metodo

#### createCase
1. Obtener CaseType por case_type_id
2. Generar case_number con generateCaseNumber()
3. Agregar case_number a $data
4. Crear via repository dentro de transaccion
5. Registrar activity log
6. Retornar caso creado

#### updateCase
1. Ejecutar dentro de transaccion
2. Actualizar via repository
3. Registrar activity log con campos modificados
4. Retornar caso actualizado

#### assignCase
1. Validar que usuario pertenece al mismo tenant
2. Actualizar assigned_to
3. Registrar activity log con usuario anterior y nuevo
4. Retornar caso actualizado

#### closeCase
1. Validar que status no sea ya 'closed'
2. Actualizar status a 'closed', closed_at a now(), closure_notes
3. Registrar activity log
4. Retornar caso cerrado

#### getTimeline
```php
return Activity::forSubject($case)
    ->latest()
    ->with('causer')
    ->get();
```

#### getStatistics
```php
return [
    'total' => ImmigrationCase::count(),
    'by_status' => [
        'active' => $this->caseRepository->countByStatus('active'),
        'inactive' => $this->caseRepository->countByStatus('inactive'),
        'archived' => $this->caseRepository->countByStatus('archived'),
        'closed' => $this->caseRepository->countByStatus('closed'),
    ],
    'by_priority' => [
        'urgent' => $this->caseRepository->countByPriority('urgent'),
        'high' => $this->caseRepository->countByPriority('high'),
        'medium' => $this->caseRepository->countByPriority('medium'),
        'low' => $this->caseRepository->countByPriority('low'),
    ],
    'upcoming_hearings' => $this->caseRepository->getUpcomingHearings(30)->count(),
];
```

### Criterios de Aceptacion
- [ ] Todos los metodos publicos implementados
- [ ] generateCaseNumber genera numeros unicos
- [ ] Transacciones envuelven operaciones de escritura
- [ ] Activity log registra todas las operaciones
- [ ] getTimeline retorna historial completo
- [ ] getStatistics retorna estructura correcta

---

## SUBFASE 2B: CasePolicy (1.5h)

### Archivo a Crear
```
app/Policies/CasePolicy.php
```

### Dependencias
- Modelo ImmigrationCase existente

### Metodos de Autorizacion

| Metodo | Parametros | Permiso Requerido | Validacion Adicional |
|--------|------------|-------------------|---------------------|
| `viewAny` | User $user | cases.view | ninguna |
| `view` | User $user, ImmigrationCase $case | cases.view | tenant_id match |
| `create` | User $user | cases.create | ninguna |
| `update` | User $user, ImmigrationCase $case | cases.update | tenant_id match |
| `delete` | User $user, ImmigrationCase $case | cases.delete | tenant_id match |
| `assign` | User $user, ImmigrationCase $case | cases.assign | tenant_id match |

### Estructura de cada Metodo
```php
public function view(User $user, ImmigrationCase $case): bool
{
    return $user->can('cases.view')
        && $case->tenant_id === $user->tenant_id;
}
```

### Permisos Requeridos
Verificar que estos permisos existen en RolePermissionSeeder:
- cases.view (YA EXISTE)
- cases.create (YA EXISTE)
- cases.update (YA EXISTE)
- cases.delete (YA EXISTE)
- cases.assign (DEBE AGREGARSE)

### Criterios de Aceptacion
- [ ] Todos los metodos de autorizacion implementados
- [ ] Cada metodo verifica permiso Y tenant_id match
- [ ] Policy sigue patron de CompanionPolicy

---

## SUBFASE 2C: Configuracion de Providers (0.5h)

### Archivos a Modificar
```
app/Providers/RepositoryServiceProvider.php
app/Providers/AuthServiceProvider.php
database/seeders/RolePermissionSeeder.php
```

### RepositoryServiceProvider - Agregar Bindings
```php
use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Repositories\Contracts\CaseTypeRepositoryInterface;
use App\Repositories\Eloquent\CaseRepository;
use App\Repositories\Eloquent\CaseTypeRepository;

// En register()
$this->app->bind(CaseRepositoryInterface::class, CaseRepository::class);
$this->app->bind(CaseTypeRepositoryInterface::class, CaseTypeRepository::class);
```

### AuthServiceProvider - Registrar Policy
```php
use App\Models\ImmigrationCase;
use App\Policies\CasePolicy;

// En $policies array
ImmigrationCase::class => CasePolicy::class,
```

### RolePermissionSeeder - Agregar Permiso
```php
// En array $permissions, agregar:
['name' => 'cases.assign', 'display_name' => 'Assign Cases'],

// En roles que deben tener el permiso:
// admin: agregar 'cases.assign'
// consultor: agregar 'cases.assign'
```

### Criterios de Aceptacion
- [ ] Bindings de repositorios agregados
- [ ] Policy registrada en AuthServiceProvider
- [ ] Permiso cases.assign agregado al seeder
- [ ] Roles admin y consultor tienen cases.assign

---

## SUBFASE 3A: Form Requests (2h)

### Archivos a Crear
```
app/Http/Requests/Case/StoreCaseRequest.php
app/Http/Requests/Case/UpdateCaseRequest.php
app/Http/Requests/Case/AssignCaseRequest.php
```

### StoreCaseRequest

#### Reglas de Validacion
| Campo | Reglas |
|-------|--------|
| client_id | required, exists:clients,id |
| case_type_id | required, exists:case_types,id |
| priority | sometimes, in:urgent,high,medium,low |
| language | sometimes, string, max:10 |
| description | nullable, string, max:5000 |
| hearing_date | nullable, date, after_or_equal:today |
| fda_deadline | nullable, date |
| brown_sheet_date | nullable, date |
| evidence_deadline | nullable, date |

#### Validacion Adicional
- client_id debe pertenecer al tenant del usuario autenticado
- case_type_id debe estar activo

```php
public function withValidator($validator)
{
    $validator->after(function ($validator) {
        // Validar que client pertenece al tenant
        $client = Client::find($this->client_id);
        if ($client && $client->tenant_id !== Auth::user()->tenant_id) {
            $validator->errors()->add('client_id', 'Invalid client');
        }

        // Validar que case_type esta activo
        $caseType = CaseType::find($this->case_type_id);
        if ($caseType && !$caseType->is_active) {
            $validator->errors()->add('case_type_id', 'Case type is not active');
        }
    });
}
```

### UpdateCaseRequest

#### Reglas de Validacion
| Campo | Reglas |
|-------|--------|
| status | sometimes, in:active,inactive,archived,closed |
| priority | sometimes, in:urgent,high,medium,low |
| progress | sometimes, integer, min:0, max:100 |
| language | sometimes, string, max:10 |
| description | nullable, string, max:5000 |
| hearing_date | nullable, date |
| fda_deadline | nullable, date |
| brown_sheet_date | nullable, date |
| evidence_deadline | nullable, date |
| archive_box_number | nullable, string, max:50 |
| closure_notes | nullable, string, max:2000, required_if:status,closed |

### AssignCaseRequest

#### Reglas de Validacion
| Campo | Reglas |
|-------|--------|
| assigned_to | required, exists:users,id |

#### Validacion Adicional
- assigned_to debe pertenecer al mismo tenant

### Criterios de Aceptacion
- [ ] Todas las validaciones implementadas
- [ ] Mensajes de error personalizados en espanol
- [ ] Validacion de tenant isolation en campos relacionales
- [ ] Required_if para closure_notes cuando status es closed

---

## SUBFASE 3B: API Resources (1.5h)

### Archivos a Crear
```
app/Http/Resources/CaseResource.php
app/Http/Resources/CaseTypeResource.php
```

### CaseResource

#### Campos a Incluir
```php
return [
    'id' => $this->id,
    'case_number' => $this->case_number,
    'tenant_id' => $this->tenant_id,
    'client_id' => $this->client_id,
    'case_type_id' => $this->case_type_id,
    'assigned_to' => $this->assigned_to,

    // Status & Priority
    'status' => $this->status,
    'status_label' => $this->status_label,
    'priority' => $this->priority,
    'priority_label' => $this->priority_label,
    'progress' => $this->progress,
    'progress_percentage' => $this->progress_percentage,
    'language' => $this->language,

    // Description
    'description' => $this->description,

    // Dates
    'hearing_date' => $this->hearing_date?->format('Y-m-d'),
    'fda_deadline' => $this->fda_deadline?->format('Y-m-d'),
    'brown_sheet_date' => $this->brown_sheet_date?->format('Y-m-d'),
    'evidence_deadline' => $this->evidence_deadline?->format('Y-m-d'),
    'days_until_hearing' => $this->days_until_hearing,

    // Archive
    'archive_box_number' => $this->archive_box_number,

    // Closure
    'closed_at' => $this->closed_at?->format('Y-m-d'),
    'closure_notes' => $this->closure_notes,

    // Timestamps
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),

    // Conditional Relations
    'client' => $this->whenLoaded('client', fn() => [
        'id' => $this->client->id,
        'full_name' => $this->client->full_name,
        'email' => $this->client->email,
    ]),
    'case_type' => $this->whenLoaded('caseType', fn() => new CaseTypeResource($this->caseType)),
    'assigned_user' => $this->whenLoaded('assignedTo', fn() => [
        'id' => $this->assignedTo->id,
        'name' => $this->assignedTo->name,
    ]),
];
```

### CaseTypeResource

#### Campos a Incluir
```php
return [
    'id' => $this->id,
    'tenant_id' => $this->tenant_id,
    'name' => $this->name,
    'code' => $this->code,
    'category' => $this->category,
    'category_label' => $this->category_label,
    'description' => $this->description,
    'is_active' => $this->is_active,
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

### Criterios de Aceptacion
- [ ] Todos los campos del modelo incluidos
- [ ] Accessors (status_label, etc.) incluidos
- [ ] Fechas formateadas correctamente
- [ ] Relaciones condicionales con whenLoaded
- [ ] Relaciones anidadas solo muestran campos necesarios

---

## SUBFASE 3C: CaseTypeController (1h)

### Archivo a Crear
```
app/Http/Controllers/Api/CaseTypeController.php
```

### Metodos

#### index()
- **Ruta:** GET /api/case-types
- **Autorizacion:** Autenticado (casos.view implicitamente via tenant)
- **Logica:**
  1. Obtener tenant_id del usuario
  2. Llamar `caseTypeRepository->getActive($tenantId)`
  3. Retornar `CaseTypeResource::collection($caseTypes)`

#### show(CaseType $caseType)
- **Ruta:** GET /api/case-types/{caseType}
- **Autorizacion:** Autenticado + tenant match
- **Logica:**
  1. Verificar que caseType es global o del tenant del usuario
  2. Retornar `new CaseTypeResource($caseType)`

### Criterios de Aceptacion
- [ ] Endpoints funcionan correctamente
- [ ] Solo retorna tipos activos del tenant o globales
- [ ] Resources aplicados correctamente

---

## SUBFASE 3D: CaseController (3h)

### Archivo a Crear
```
app/Http/Controllers/Api/CaseController.php
```

### Dependencias
- CaseService
- Form Requests
- CaseResource
- CasePolicy

### Constructor
```php
use AuthorizesRequests;

public function __construct(
    private CaseService $caseService
) {}
```

### Metodos

#### index(Request $request)
- **Ruta:** GET /api/cases
- **Autorizacion:** $this->authorize('viewAny', ImmigrationCase::class)
- **Parametros Query:** search, status, priority, case_type_id, assigned_to, client_id, hearing_from, hearing_to, sort_by, sort_direction, per_page
- **Logica:**
  1. Autorizar viewAny
  2. Obtener filtros de request
  3. Llamar `caseService->listCases($filters, $perPage)`
  4. Retornar respuesta paginada con CaseResource

#### store(StoreCaseRequest $request)
- **Ruta:** POST /api/cases
- **Autorizacion:** Implicita en FormRequest + authorize en controller
- **Logica:**
  1. Autorizar create
  2. Llamar `caseService->createCase($request->validated())`
  3. Retornar 201 con CaseResource y mensaje

#### show(ImmigrationCase $case)
- **Ruta:** GET /api/cases/{case}
- **Autorizacion:** $this->authorize('view', $case)
- **Logica:**
  1. Autorizar view
  2. Llamar `caseService->getCase($case)`
  3. Retornar CaseResource

#### update(UpdateCaseRequest $request, ImmigrationCase $case)
- **Ruta:** PUT /api/cases/{case}
- **Autorizacion:** $this->authorize('update', $case)
- **Logica:**
  1. Autorizar update
  2. Llamar `caseService->updateCase($case, $request->validated())`
  3. Retornar CaseResource con mensaje

#### destroy(ImmigrationCase $case)
- **Ruta:** DELETE /api/cases/{case}
- **Autorizacion:** $this->authorize('delete', $case)
- **Logica:**
  1. Autorizar delete
  2. Llamar `caseService->deleteCase($case)`
  3. Retornar 200 con mensaje

#### assign(AssignCaseRequest $request, ImmigrationCase $case)
- **Ruta:** POST /api/cases/{case}/assign
- **Autorizacion:** $this->authorize('assign', $case)
- **Logica:**
  1. Autorizar assign
  2. Llamar `caseService->assignCase($case, $request->assigned_to)`
  3. Retornar CaseResource con mensaje

#### timeline(ImmigrationCase $case)
- **Ruta:** GET /api/cases/{case}/timeline
- **Autorizacion:** $this->authorize('view', $case)
- **Logica:**
  1. Autorizar view
  2. Llamar `caseService->getTimeline($case)`
  3. Retornar array de actividades

#### statistics()
- **Ruta:** GET /api/cases/statistics
- **Autorizacion:** $this->authorize('viewAny', ImmigrationCase::class)
- **Logica:**
  1. Autorizar viewAny
  2. Llamar `caseService->getStatistics()`
  3. Retornar JSON

### OpenAPI Annotations
Agregar anotaciones OA similares a ClientController para documentacion automatica.

### Criterios de Aceptacion
- [ ] Todos los endpoints implementados
- [ ] Autorizacion aplicada en cada metodo
- [ ] Respuestas con formato consistente
- [ ] Manejo de errores apropiado
- [ ] Documentacion OpenAPI

---

## SUBFASE 3E: Rutas API (0.5h)

### Archivo a Modificar
```
routes/api.php
```

### Rutas a Agregar
```php
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseTypeController;

// Dentro del grupo middleware(['auth:sanctum', 'throttle:api', 'tenant'])

// Case Types (read-only)
Route::get('/case-types', [CaseTypeController::class, 'index']);
Route::get('/case-types/{caseType}', [CaseTypeController::class, 'show']);

// Cases
Route::get('/cases/statistics', [CaseController::class, 'statistics']);
Route::apiResource('cases', CaseController::class);
Route::post('/cases/{case}/assign', [CaseController::class, 'assign']);
Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline']);
```

### Nota sobre Route Model Binding
El parametro `{case}` debera resolver al modelo ImmigrationCase. Configurar en RouteServiceProvider si es necesario:
```php
Route::model('case', ImmigrationCase::class);
```

### Criterios de Aceptacion
- [ ] Todas las rutas registradas
- [ ] Route model binding funciona para {case}
- [ ] statistics antes de apiResource para evitar conflicto
- [ ] Rutas dentro del grupo protegido correcto

---

## SUBFASE 4A: Factories (1.5h)

### Archivos a Crear
```
database/factories/CaseTypeFactory.php
database/factories/ImmigrationCaseFactory.php
```

### CaseTypeFactory

#### Definicion Base
```php
protected $model = CaseType::class;

public function definition(): array
{
    $category = fake()->randomElement(['temporary_residence', 'permanent_residence', 'humanitarian']);

    return [
        'tenant_id' => null, // Global by default
        'name' => fake()->words(2, true) . ' Visa',
        'code' => strtoupper(fake()->unique()->lexify('???_???')),
        'category' => $category,
        'description' => fake()->optional()->sentence(),
        'is_active' => true,
    ];
}
```

#### Estados
| Estado | Descripcion |
|--------|-------------|
| `inactive()` | is_active = false |
| `temporary()` | category = temporary_residence |
| `permanent()` | category = permanent_residence |
| `humanitarian()` | category = humanitarian |
| `forTenant(Tenant $tenant)` | tenant_id = $tenant->id |

### ImmigrationCaseFactory

#### Definicion Base
```php
protected $model = ImmigrationCase::class;

public function definition(): array
{
    $status = fake()->randomElement(['active', 'inactive', 'archived', 'closed']);

    return [
        'tenant_id' => Tenant::factory(),
        'case_number' => $this->generateCaseNumber(),
        'client_id' => Client::factory(),
        'case_type_id' => CaseType::factory(),
        'assigned_to' => null,
        'status' => $status,
        'priority' => fake()->randomElement(['urgent', 'high', 'medium', 'low']),
        'progress' => fake()->numberBetween(0, 100),
        'language' => fake()->randomElement(['es', 'en', 'fr']),
        'description' => fake()->optional()->paragraph(),
        'hearing_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 year'),
        'fda_deadline' => fake()->optional()->dateTimeBetween('+1 week', '+6 months'),
        'brown_sheet_date' => fake()->optional()->dateTimeBetween('-6 months', 'now'),
        'evidence_deadline' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
        'archive_box_number' => $status === 'closed' ? fake()->bothify('BOX-###') : null,
        'closed_at' => $status === 'closed' ? fake()->dateTimeBetween('-1 year', 'now') : null,
        'closure_notes' => $status === 'closed' ? fake()->sentence() : null,
    ];
}

private function generateCaseNumber(): string
{
    $year = date('Y');
    $code = fake()->randomElement(['ASYLUM', 'WORK', 'STUDENT', 'EXPRESS_ENTRY']);
    $sequence = fake()->unique()->numberBetween(1, 99999);
    return sprintf('%s-%s-%05d', $year, $code, $sequence);
}
```

#### Estados
| Estado | Descripcion |
|--------|-------------|
| `active()` | status = active |
| `closed()` | status = closed, closed_at = now, closure_notes |
| `urgent()` | priority = urgent |
| `withClient(Client $client)` | client_id = $client->id, tenant_id = $client->tenant_id |
| `withAssignee(User $user)` | assigned_to = $user->id |
| `withHearing()` | hearing_date en proximos 30 dias |
| `asylum()` | case_type para asylum |

### Criterios de Aceptacion
- [ ] Factories generan datos validos
- [ ] Estados modifican atributos correctamente
- [ ] case_number generado es unico
- [ ] Relaciones creadas automaticamente si no se especifican

---

## SUBFASE 4B: Feature Tests (5h)

### Archivo a Crear
```
tests/Feature/CaseTest.php
```

### Setup del Test
```php
use RefreshDatabase;

protected Tenant $tenant;
protected User $adminUser;
protected User $regularUser;
protected Client $client;
protected CaseType $caseType;

protected function setUp(): void
{
    parent::setUp();

    $this->seed(RolePermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->adminUser->assignRole('admin');

    $this->regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->regularUser->assignRole('cliente');

    $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->caseType = CaseType::first(); // From migration seeder
}
```

### Tests de Listado
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_list_cases | Admin puede listar casos | 200, cuenta correcta |
| test_cases_are_paginated | Paginacion funciona | meta.current_page, meta.per_page |
| test_can_filter_by_status | Filtro por status | Solo casos con status especificado |
| test_can_filter_by_priority | Filtro por prioridad | Solo casos con prioridad especificada |
| test_can_filter_by_case_type | Filtro por tipo | Solo casos del tipo especificado |
| test_can_filter_by_assignee | Filtro por asignado | Solo casos del usuario |
| test_can_filter_by_client | Filtro por cliente | Solo casos del cliente |
| test_can_search_cases | Busqueda por case_number | Resultados coincidentes |
| test_unauthorized_user_cannot_list_cases | Usuario sin permiso | 403 |

### Tests de Creacion
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_create_case | Admin puede crear | 201, datos en DB |
| test_case_number_is_generated_automatically | case_number se genera | Formato correcto |
| test_create_case_requires_client_id | Validacion client_id | 422, error de validacion |
| test_create_case_requires_case_type_id | Validacion case_type_id | 422, error de validacion |
| test_cannot_create_case_with_client_from_another_tenant | Aislamiento tenant | 422 |
| test_unauthorized_user_cannot_create_case | Sin permiso | 403 |

### Tests de Lectura
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_view_case | Admin puede ver detalle | 200, datos correctos |
| test_case_includes_relations | Relaciones cargadas | client, case_type presentes |
| test_cannot_view_case_from_another_tenant | Aislamiento tenant | 404 |
| test_unauthorized_user_cannot_view_case | Sin permiso | 403 |

### Tests de Actualizacion
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_update_case | Admin puede actualizar | 200, datos actualizados |
| test_can_update_case_status | Cambio de status | status cambiado |
| test_can_update_case_priority | Cambio de prioridad | priority cambiado |
| test_can_update_case_progress | Cambio de progreso | progress cambiado |
| test_closure_notes_required_when_closing | Validacion cierre | 422 si falta closure_notes |
| test_unauthorized_user_cannot_update_case | Sin permiso | 403 |

### Tests de Eliminacion
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_delete_case | Admin puede eliminar | 200, soft deleted |
| test_case_is_soft_deleted | Soft delete funciona | deleted_at no null |
| test_unauthorized_user_cannot_delete_case | Sin permiso | 403 |

### Tests de Asignacion
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_admin_can_assign_case | Admin puede asignar | 200, assigned_to actualizado |
| test_cannot_assign_to_user_from_another_tenant | Aislamiento tenant | 422 |
| test_unauthorized_user_cannot_assign_case | Sin permiso | 403 |

### Tests de Timeline
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_can_get_case_timeline | Timeline disponible | 200, array de actividades |
| test_timeline_includes_recent_activity | Actividad registrada | activity log entries |

### Tests de Estadisticas
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_can_get_case_statistics | Stats disponibles | 200, estructura correcta |
| test_statistics_include_counts_by_status | Conteos por status | by_status presente |
| test_statistics_include_counts_by_priority | Conteos por prioridad | by_priority presente |

### Tests de Tenant Isolation
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_case_auto_assigned_to_user_tenant | Tenant automatico | tenant_id correcto |
| test_user_only_sees_own_tenant_cases | Aislamiento en listado | Solo casos propios |

### Tests de Activity Logging
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_creating_case_logs_activity | Log en creacion | activity_log entry |
| test_updating_case_logs_activity | Log en actualizacion | activity_log entry |
| test_assigning_case_logs_activity | Log en asignacion | activity_log entry |

### Tests de Case Types
| Test | Descripcion | Assertions |
|------|-------------|------------|
| test_can_list_case_types | Listar tipos | 200, array de tipos |
| test_case_types_include_global_and_tenant | Global + tenant | Ambos incluidos |
| test_can_view_case_type | Ver tipo | 200, datos correctos |

### Criterios de Aceptacion
- [ ] Minimo 35 tests
- [ ] Cobertura de todos los endpoints
- [ ] Tests de validaciones
- [ ] Tests de autorizacion
- [ ] Tests de tenant isolation
- [ ] Tests de activity logging
- [ ] Todos los tests pasan

---

## Resumen de Archivos

### Backend: 17 archivos nuevos

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── CaseController.php
│   │   └── CaseTypeController.php
│   ├── Requests/Case/
│   │   ├── StoreCaseRequest.php
│   │   ├── UpdateCaseRequest.php
│   │   └── AssignCaseRequest.php
│   └── Resources/
│       ├── CaseResource.php
│       └── CaseTypeResource.php
├── Models/
│   ├── ImmigrationCase.php
│   └── CaseType.php
├── Policies/
│   └── CasePolicy.php
├── Repositories/
│   ├── Contracts/
│   │   ├── CaseRepositoryInterface.php
│   │   └── CaseTypeRepositoryInterface.php
│   └── Eloquent/
│       ├── CaseRepository.php
│       └── CaseTypeRepository.php
└── Services/Case/
    └── CaseService.php

database/factories/
├── CaseTypeFactory.php
└── ImmigrationCaseFactory.php

tests/Feature/
└── CaseTest.php
```

### Archivos a Modificar: 3
```
routes/api.php
app/Providers/RepositoryServiceProvider.php
app/Providers/AuthServiceProvider.php
database/seeders/RolePermissionSeeder.php (si cases.assign no existe)
```

---

## Orden de Implementacion Recomendado

1. **SUBFASE 1A** - CaseType Model (base para CaseType en otros)
2. **SUBFASE 1B** - ImmigrationCase Model (depende de CaseType)
3. **SUBFASE 1C** - Interfaces de Repositorio
4. **SUBFASE 1D** - Implementaciones de Repositorio
5. **SUBFASE 2A** - CaseService (depende de repositorios)
6. **SUBFASE 2B** - CasePolicy
7. **SUBFASE 2C** - Configuracion de Providers
8. **SUBFASE 3A** - Form Requests
9. **SUBFASE 3B** - API Resources
10. **SUBFASE 3C** - CaseTypeController
11. **SUBFASE 3D** - CaseController
12. **SUBFASE 3E** - Rutas API
13. **SUBFASE 4A** - Factories
14. **SUBFASE 4B** - Feature Tests

---

## Notas Adicionales

### Route Model Binding para ImmigrationCase
En `app/Providers/RouteServiceProvider.php`, agregar si es necesario:
```php
use App\Models\ImmigrationCase;

public function boot(): void
{
    Route::model('case', ImmigrationCase::class);
}
```

### Actualizacion de Client Model
Descomentar la relacion `cases()` en `app/Models/Client.php`:
```php
public function cases(): HasMany
{
    return $this->hasMany(ImmigrationCase::class);
}
```

### Case Number Uniqueness
El case_number debe ser unico globalmente (no solo por tenant). La constraint UNIQUE en la migracion ya lo garantiza.

---

**Documento generado para implementacion de Epic 2.1 Backend**
**Fecha: 2026-02-10**
