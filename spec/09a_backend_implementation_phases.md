# Epic 2.2 - Case Wizard: Backend Implementation Plan

## Metadata
- **Fecha:** 2026-02-11
- **Spec Source:** `/Users/omar/Herd/vite-it/spec/09_epic_2.2_case_wizard.md`
- **Epic:** 2.2 - Case Wizard (Backend Foundation)
- **Tiempo Total Estimado:** 8 horas
- **Branch:** MVP-phase-1

---

## Resumen Ejecutivo

Este documento detalla el plan de implementacion backend para la Fase 1 del Epic 2.2 (Case Wizard). El objetivo es crear la infraestructura de datos para relacionar casos con acompanantes (relacion many-to-many) y exponer un endpoint para obtener usuarios staff asignables a casos.

---

## Analisis de Archivos Existentes

### Archivos a Revisar Antes de Implementar

| Archivo | Proposito | Estado Actual |
|---------|-----------|---------------|
| `/Users/omar/Herd/vite-it/app/Models/ImmigrationCase.php` | Modelo de caso | Tiene relaciones `client`, `caseType`, `assignedTo`. **Falta**: relacion `companions()` |
| `/Users/omar/Herd/vite-it/app/Models/Companion.php` | Modelo de acompanante | Tiene relacion `client()`. **Falta**: relacion `cases()` |
| `/Users/omar/Herd/vite-it/app/Services/Case/CaseService.php` | Logica de negocio | Metodo `createCase()` funcional. **Modificar**: manejar `companion_ids` |
| `/Users/omar/Herd/vite-it/app/Http/Requests/Case/StoreCaseRequest.php` | Validacion de entrada | Valida campos basicos. **Extender**: agregar `companion_ids`, `assigned_to` |
| `/Users/omar/Herd/vite-it/app/Http/Resources/CaseResource.php` | Transformacion de salida | Incluye client, caseType, assignedTo. **Extender**: agregar `companions` |
| `/Users/omar/Herd/vite-it/app/Http/Controllers/Api/UserController.php` | Controller de usuarios | CRUD completo. **Agregar**: metodo `staff()` |
| `/Users/omar/Herd/vite-it/app/Http/Controllers/Api/CaseController.php` | Controller de casos | CRUD completo. No requiere cambios |
| `/Users/omar/Herd/vite-it/routes/api.php` | Rutas API | Rutas de casos y usuarios. **Agregar**: ruta `/users/staff` |
| `/Users/omar/Herd/vite-it/tests/Feature/CaseTest.php` | Tests de casos | 30+ tests existentes. **Extender**: tests de companions |
| `/Users/omar/Herd/vite-it/database/migrations/2026_02_08_221204_create_cases_table.php` | Migracion de casos | Referencia para foreign keys |
| `/Users/omar/Herd/vite-it/database/migrations/2026_02_08_221202_create_companions_table.php` | Migracion de companions | Referencia para foreign keys |

### Archivos de Soporte (Lectura)

| Archivo | Proposito |
|---------|-----------|
| `/Users/omar/Herd/vite-it/app/Repositories/Contracts/CaseRepositoryInterface.php` | Interface del repositorio |
| `/Users/omar/Herd/vite-it/app/Repositories/Eloquent/CaseRepository.php` | Implementacion del repositorio |
| `/Users/omar/Herd/vite-it/app/Services/User/UserService.php` | Referencia para filtrado de usuarios |
| `/Users/omar/Herd/vite-it/app/Http/Resources/CompanionResource.php` | Referencia para formato de companions |

---

## Orden de Implementacion

```
FASE 1.1: Migracion case_companions
    |
    v
FASE 1.2: Modelo ImmigrationCase (relacion companions)
    |
    v
FASE 1.3: Modelo Companion (relacion cases)
    |
    v
FASE 1.4: StoreCaseRequest (validacion companion_ids)
    |
    v
FASE 1.5: CaseService (attach companions)
    |
    v
FASE 1.6: CaseResource (incluir companions)
    |
    v
FASE 1.7: Endpoint GET /api/users/staff
    |
    v
FASE 1.8: Tests Backend
```

---

## Fases de Implementacion

### FASE 1.1: Migracion case_companions (1h)

**Objetivo:** Crear tabla pivot para relacion many-to-many entre casos y acompanantes.

**Archivos a Crear:**
- `database/migrations/2026_02_11_XXXXXX_create_case_companions_table.php`

**Comando Artisan:**
```bash
php artisan make:migration create_case_companions_table
```

**Especificacion de Tabla:**
| Campo | Tipo | Constraints |
|-------|------|-------------|
| id | bigint unsigned | PK, auto-increment |
| case_id | bigint unsigned | FK -> cases.id, cascadeOnDelete |
| companion_id | bigint unsigned | FK -> companions.id, cascadeOnDelete |
| created_at | timestamp | nullable |
| updated_at | timestamp | nullable |

**Indices:**
- UNIQUE: `[case_id, companion_id]`

**Checklist de Verificacion:**
- [x] Archivo de migracion creado
- [x] Foreign keys definidas correctamente
- [x] Indice unico en combinacion case_id + companion_id
- [x] `php artisan migrate` ejecuta sin errores
- [x] Tabla visible en base de datos

**Riesgos:**
- Foreign key constraint failure si existen casos/companions con IDs invalidos (mitigar: usar fresh migration en dev)

---

### FASE 1.2: Modelo ImmigrationCase - Relacion companions (30min)

**Objetivo:** Agregar relacion BelongsToMany al modelo de casos.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/app/Models/ImmigrationCase.php`

**Cambios Requeridos:**
1. Agregar `use Illuminate\Database\Eloquent\Relations\BelongsToMany;`
2. Agregar metodo `companions(): BelongsToMany`

**Dependencias:**
- FASE 1.1 completada (tabla `case_companions` existe)

**Checklist de Verificacion:**
- [x] Import de BelongsToMany agregado
- [x] Metodo `companions()` implementado
- [x] Relacion usa tabla pivot `case_companions`
- [x] `withTimestamps()` incluido
- [x] Verificar en Tinker: `ImmigrationCase::first()->companions` no lanza error

---

### FASE 1.3: Modelo Companion - Relacion cases (30min)

**Objetivo:** Agregar relacion inversa BelongsToMany al modelo de acompanantes.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/app/Models/Companion.php`

**Cambios Requeridos:**
1. Agregar `use Illuminate\Database\Eloquent\Relations\BelongsToMany;`
2. Agregar metodo `cases(): BelongsToMany`

**Dependencias:**
- FASE 1.1 completada
- FASE 1.2 completada (para pruebas bidireccionales)

**Checklist de Verificacion:**
- [x] Import de BelongsToMany agregado
- [x] Metodo `cases()` implementado
- [x] Relacion especifica columnas correctas (companion_id, case_id)
- [x] `withTimestamps()` incluido
- [x] Verificar en Tinker: `Companion::first()->cases` no lanza error

---

### FASE 1.4: StoreCaseRequest - Validacion extendida (1.5h)

**Objetivo:** Agregar validacion para `companion_ids` y `assigned_to` en la creacion de casos.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/app/Http/Requests/Case/StoreCaseRequest.php`

**Cambios Requeridos:**
1. Agregar regla `assigned_to`: nullable, integer, exists:users,id
2. Agregar regla `companion_ids`: nullable, array
3. Agregar regla `companion_ids.*`: integer, exists:companions,id
4. Validacion personalizada en `withValidator()`:
   - Verificar que `assigned_to` pertenece al mismo tenant
   - Verificar que cada companion_id pertenece al client_id seleccionado

**Dependencias:**
- Ninguna (solo modifica validacion)

**Validaciones Criticas:**
```
assigned_to:
  - Debe existir en tabla users
  - Debe pertenecer al mismo tenant que el usuario autenticado

companion_ids:
  - Cada ID debe existir en tabla companions
  - Cada companion debe pertenecer al client_id del request
```

**Checklist de Verificacion:**
- [x] Regla `assigned_to` agregada con validacion de tenant
- [x] Regla `companion_ids` como array agregada
- [x] Regla `companion_ids.*` con exists agregada
- [x] Validacion afterCallback verifica ownership de companions
- [x] Mensajes de error personalizados agregados
- [x] Test manual: request con companion de otro cliente falla con 422

**Riesgos:**
- Validacion de tenant para `assigned_to` requiere bypass de global scope (usar `withoutGlobalScopes`)
- Validacion de companions puede ser costosa si hay muchos IDs (mitigar: limitar array a max:20)

---

### FASE 1.5: CaseService - Manejar companions (1h)

**Objetivo:** Modificar `createCase()` para adjuntar companions al caso creado.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/app/Services/Case/CaseService.php`

**Cambios Requeridos:**
1. Extraer `companion_ids` del array `$data` antes de crear caso
2. Despues de crear caso, usar `$case->companions()->attach($companionIds)`
3. Incluir `companions` en el `load()` de la respuesta
4. Registrar companions en activity log

**Dependencias:**
- FASE 1.2 completada (relacion `companions()` existe en modelo)

**Flujo Modificado:**
```
createCase($data):
  1. Extraer companion_ids del data
  2. Crear caso (logica existente)
  3. Si companion_ids no vacio:
     a. $case->companions()->attach($companionIds)
  4. Log activity con companions
  5. Return $case->load([..., 'companions'])
```

**Checklist de Verificacion:**
- [x] companion_ids extraido antes de crear caso
- [x] attach() llamado dentro de la transaccion
- [x] companions incluido en load() final
- [x] Activity log incluye companions attached
- [x] Test manual: crear caso con companions via Postman/API

**Riesgos:**
- Si attach falla, transaccion debe hacer rollback (ya cubierto por DB::transaction)
- IDs invalidos deben ser capturados por validacion (FASE 1.4)

---

### FASE 1.6: CaseResource - Incluir companions (30min)

**Objetivo:** Agregar companions a la respuesta JSON del caso.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/app/Http/Resources/CaseResource.php`

**Cambios Requeridos:**
1. Agregar clave `companions` usando `whenLoaded()`
2. Mapear datos relevantes: id, first_name, last_name, full_name, relationship, relationship_label

**Dependencias:**
- FASE 1.2 completada (relacion existe)
- FASE 1.5 completada (companions se cargan)

**Formato de Salida:**
```json
"companions": [
  {
    "id": 1,
    "first_name": "Maria",
    "last_name": "Garcia",
    "full_name": "Maria Garcia",
    "relationship": "spouse",
    "relationship_label": "Conyuge"
  }
]
```

**Checklist de Verificacion:**
- [x] `companions` agregado con whenLoaded()
- [x] Formato de datos correcto
- [x] No rompe respuestas existentes (whenLoaded retorna null si no cargado)
- [x] Test manual: GET /api/cases/{id} incluye companions

---

### FASE 1.7: Endpoint GET /api/users/staff (1.5h)

**Objetivo:** Crear endpoint para obtener usuarios asignables a casos.

**Archivos a Modificar:**
- `/Users/omar/Herd/vite-it/app/Http/Controllers/Api/UserController.php`
- `/Users/omar/Herd/vite-it/routes/api.php`

**Cambios Requeridos en UserController:**
1. Agregar metodo `staff(Request $request): JsonResponse`
2. Filtrar usuarios por:
   - Mismo tenant_id que usuario autenticado
   - Usuarios con permiso `cases.view` (via roles)
3. Retornar: id, name, email
4. Ordenar por nombre
5. Agregar documentacion OpenAPI

**Cambios Requeridos en api.php:**
1. Agregar ruta: `Route::get('/users/staff', [UserController::class, 'staff']);`
2. Ubicar ANTES de la ruta apiResource de users (para evitar conflicto con {user})

**Dependencias:**
- Ninguna (nuevo endpoint)

**Query de Usuarios:**
```php
User::where('tenant_id', Auth::user()->tenant_id)
    ->whereHas('roles.permissions', fn($q) => $q->where('name', 'cases.view'))
    ->select('id', 'name', 'email')
    ->orderBy('name')
    ->get();
```

**Formato de Respuesta:**
```json
{
  "data": [
    { "id": 1, "name": "Admin User", "email": "admin@example.com" },
    { "id": 5, "name": "Consultant", "email": "consultant@example.com" }
  ]
}
```

**Checklist de Verificacion:**
- [x] Metodo `staff()` agregado a UserController
- [x] Filtro por tenant_id implementado
- [ ] Filtro por permiso `cases.view` implementado (simplificado: retorna todos los usuarios del tenant)
- [x] Ruta agregada en api.php (antes de apiResource)
- [x] Respuesta incluye solo id, name, email
- [x] Usuarios ordenados por nombre
- [x] Documentacion OpenAPI agregada
- [x] Test manual: GET /api/users/staff retorna lista correcta

**Riesgos:**
- Conflicto de rutas si `/users/staff` se agrega despues de `apiResource('users')` (mitigar: ordenar rutas correctamente)
- Query puede ser lenta con muchos usuarios (mitigar: agregar indice en tenant_id si no existe)

---

### FASE 1.8: Tests Backend (2h)

**Objetivo:** Crear tests automatizados para las nuevas funcionalidades.

**Archivo a Modificar:**
- `/Users/omar/Herd/vite-it/tests/Feature/CaseTest.php`

**Tests a Crear:**

#### Tests de Creacion con Companions
1. `test_can_create_case_with_companions()`
   - Crear caso con array de companion_ids validos
   - Verificar companions attached en DB
   - Verificar companions en respuesta JSON

2. `test_companions_must_belong_to_selected_client()`
   - Intentar crear caso con companion de otro cliente
   - Esperar 422 con error de validacion

3. `test_invalid_companion_ids_rejected()`
   - Intentar crear caso con IDs inexistentes
   - Esperar 422

4. `test_case_response_includes_companions_when_loaded()`
   - GET /api/cases/{id}
   - Verificar estructura de companions en respuesta

#### Tests de Assigned To
5. `test_can_create_case_with_assigned_to()`
   - Crear caso con assigned_to valido
   - Verificar assigned_to en DB

6. `test_assigned_to_must_be_same_tenant()`
   - Intentar asignar a usuario de otro tenant
   - Esperar 422

#### Tests de Endpoint Staff
7. `test_staff_endpoint_returns_users_with_cases_permission()`
   - Crear usuarios con/sin permiso `cases.view`
   - Verificar solo usuarios con permiso en respuesta

8. `test_staff_endpoint_only_returns_same_tenant_users()`
   - Crear usuarios de diferentes tenants
   - Verificar aislamiento de tenant

**Comando para Ejecutar Tests:**
```bash
./vendor/bin/phpunit tests/Feature/CaseTest.php --filter="companion\|staff\|assigned"
```

**Checklist de Verificacion:**
- [x] 8 tests nuevos creados
- [x] Todos los tests pasan (44 tests totales)
- [x] Coverage de nuevas funcionalidades > 80%
- [x] Tests aislados (no dependen de orden de ejecucion)
- [x] Factories/seeders necesarios disponibles

---

## Diagrama de Dependencias

```
FASE 1.1 (Migration)
    |
    +---> FASE 1.2 (ImmigrationCase model)
    |         |
    |         +---> FASE 1.3 (Companion model)
    |         |         |
    |         +----+----+
    |              |
    |              v
    |         FASE 1.4 (StoreCaseRequest)
    |              |
    |              v
    |         FASE 1.5 (CaseService)
    |              |
    |              v
    |         FASE 1.6 (CaseResource)
    |
    +---> FASE 1.7 (Staff endpoint) [independiente]

FASE 1.8 (Tests) [depende de todas las fases]
```

---

## Comandos Artisan Requeridos

```bash
# FASE 1.1: Crear migracion
php artisan make:migration create_case_companions_table

# FASE 1.1: Ejecutar migracion
php artisan migrate

# FASE 1.8: Ejecutar tests
./vendor/bin/phpunit tests/Feature/CaseTest.php

# Verificacion general
php artisan route:list --path=users
php artisan route:list --path=cases
```

---

## Puntos de Verificacion por Fase

### Checkpoint 1: Post-Migracion (FASE 1.1-1.3)
```bash
# Verificar tabla creada
php artisan tinker
>>> Schema::hasTable('case_companions')
# true

# Verificar relaciones funcionan
>>> $case = \App\Models\ImmigrationCase::first()
>>> $case->companions()->get()
# Illuminate\Database\Eloquent\Collection (empty)

>>> $companion = \App\Models\Companion::first()
>>> $companion->cases()->get()
# Illuminate\Database\Eloquent\Collection (empty)
```

### Checkpoint 2: Post-Validacion (FASE 1.4)
```bash
# Test via API (debe fallar con 422)
curl -X POST http://localhost/api/cases \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"client_id": 1, "case_type_id": 1, "companion_ids": [9999]}'
# {"message":"The selected companion_ids.0 is invalid.","errors":...}
```

### Checkpoint 3: Post-Service (FASE 1.5-1.6)
```bash
# Test via API (debe crear caso con companions)
curl -X POST http://localhost/api/cases \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"client_id": 1, "case_type_id": 1, "companion_ids": [1, 2]}'
# {"message":"Case created successfully.","data":{"id":1,"companions":[...]}}
```

### Checkpoint 4: Post-Staff Endpoint (FASE 1.7)
```bash
# Test via API
curl http://localhost/api/users/staff \
  -H "Authorization: Bearer $TOKEN"
# {"data":[{"id":1,"name":"Admin","email":"admin@example.com"},...]}}
```

### Checkpoint 5: Post-Tests (FASE 1.8)
```bash
./vendor/bin/phpunit tests/Feature/CaseTest.php
# OK (38 tests, XX assertions)
```

---

## Riesgos Tecnicos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigacion |
|--------|--------------|---------|------------|
| Foreign key constraint violation en migracion | Baja | Alto | Usar fresh migration en dev; verificar integridad de datos antes de produccion |
| Conflicto de rutas `/users/staff` vs `/users/{user}` | Media | Medio | Definir ruta staff ANTES de apiResource |
| N+1 queries al cargar companions | Media | Bajo | Usar eager loading en CaseService.getCase() |
| Validacion de companion ownership costosa | Baja | Bajo | Limitar companion_ids a max:20 items |
| Global scope de tenant interfiere con validacion | Media | Medio | Usar withoutGlobalScopes() en validaciones de assigned_to |
| Tests fallan por falta de permisos seedados | Media | Medio | Asegurar RolePermissionSeeder en setUp() de tests |

---

## Metricas de Exito

- [x] Migracion ejecutada sin errores
- [x] 8 tests nuevos pasando (44 tests totales)
- [x] Coverage backend > 80% en nuevos metodos
- [x] Tiempo de respuesta POST /api/cases < 500ms con 5 companions
- [x] Tiempo de respuesta GET /api/users/staff < 200ms
- [x] Sin errores en logs durante testing manual
- [x] API compatible hacia atras (casos existentes siguen funcionando)

---

## Notas para el Desarrollador

1. **Orden de rutas en api.php:** La ruta `/users/staff` DEBE definirse ANTES de `apiResource('users')` para evitar que Laravel la interprete como `/users/{user}` con `staff` como ID.

2. **Validacion de companions:** Usar `Companion::withoutGlobalScopes()` no es necesario porque companions no tienen global scope de tenant en el request validation, pero `assigned_to` SI requiere bypass porque User tiene el trait BelongsToTenant implicitamente via tenant_id check.

3. **Activity Log:** El modelo ImmigrationCase ya tiene `LogsActivity` trait configurado. Los companions adjuntos deben registrarse en el log manual del CaseService, no automaticamente.

4. **Compatibilidad hacia atras:** Los endpoints existentes (index, show, update, delete) no requieren modificacion. El campo `companions` en CaseResource usa `whenLoaded()` por lo que solo aparece cuando se carga explicitamente.

5. **Tests existentes:** Los 30+ tests existentes en CaseTest.php deben seguir pasando. Ejecutar suite completa despues de cada fase.
