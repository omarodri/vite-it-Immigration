# Fases de Implementacion Backend - Gestion de Roles y Permisos

## Metadata
- **Fecha:** 2026-02-03
- **Spec de referencia:** `spec/06_role_permission_management.md`
- **Branch:** beta_onis
- **Tiempo Total Estimado:** ~6 horas

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad |
|------|--------|--------|-----------|
| B1 | RolePolicy y Registro en AuthServiceProvider | 1h | CRITICO |
| B2 | FormRequests de Validacion (Store y Update) | 1.5h | CRITICO |
| B3 | Paginacion en RoleRepository | 1h | ALTO |
| B4 | Refactorizacion del RoleController | 1.5h | CRITICO |
| B5 | Actualizacion de Feature Tests | 1h | ALTO |

---

## Estado Actual del Backend (validado en codigo)

### Lo que ya existe y funciona
- `app/Http/Controllers/Api/RoleController.php`: CRUD completo con 6 metodos (index, show, store, update, destroy, permissions). Usa checks manuales `$request->user()->can()` y validacion inline `$request->validate()`. Tiene OpenAPI annotations.
- `app/Repositories/Contracts/RoleRepositoryInterface.php`: Interface con 8 metodos (all, findById, create, update, delete, allPermissions, permissionsGrouped, isProtected). NO tiene metodo `paginate()`.
- `app/Repositories/Eloquent/RoleRepository.php`: Implementacion completa. Constante `PROTECTED_ROLES = ['admin', 'user', 'editor']`. Metodo `all()` retorna `Collection` sin paginacion.
- `tests/Feature/Api/RoleControllerTest.php`: 12 tests pasando. Setup crea permisos, roles protegidos y usuarios admin/user. Tests validan autorizacion (403), CRUD, roles protegidos (422), validacion (422).
- `routes/api.php`: Rutas de roles definidas manualmente (no apiResource). Dentro del grupo `auth:sanctum` + `throttle:api`.

### Lo que falta (segun spec)
- RolePolicy (no existe; autorizacion se hace con checks manuales en controller)
- FormRequests para Store y Update (validacion inline en controller)
- Paginacion en listado de roles (retorna coleccion completa)

### Patrones de referencia ya implementados
- `app/Policies/UserPolicy.php`: Policy con metodos viewAny, view, create, update, delete. Logica de negocio en delete (no borrar a ti mismo, no borrar ultimo admin).
- `app/Providers/AuthServiceProvider.php`: Mapeo `$policies` con User => UserPolicy. Gate::before para admin bypass.
- `app/Http/Requests/User/StoreUserRequest.php`: authorize() con `$this->user()->can()`, rules() con validacion, messages() con mensajes custom.
- `app/Http/Requests/User/UpdateUserRequest.php`: Mismo patron. Usa `$this->route('user')` para obtener ID en regla unique.
- `app/Http/Controllers/Api/UserController.php`: Usa `$this->authorize()` con Policy, inyecta FormRequests como type-hint, delega a Service.
- `app/Repositories/Eloquent/UserRepository.php`: Metodo `paginate()` con filtros search, sort_by, sort_direction. Retorna `LengthAwarePaginator`.

---

## Fase B1: RolePolicy y Registro en AuthServiceProvider

### Objetivo
Crear una Policy dedicada para el modelo Role de Spatie, centralizando la logica de autorizacion que actualmente esta dispersa en el controller. La Policy debe encapsular la proteccion de roles protegidos (admin, editor, user) que hoy se verifica manualmente con `isProtected()` en los metodos update y destroy del controller.

### Archivos

| Archivo | Accion | Patron de referencia |
|---------|--------|---------------------|
| `app/Policies/RolePolicy.php` | **CREAR** | `app/Policies/UserPolicy.php` |
| `app/Providers/AuthServiceProvider.php` | **MODIFICAR** | Linea 17-19 ($policies array) |

### Pasos

1. **Crear `app/Policies/RolePolicy.php`**
   - Namespace: `App\Policies`
   - Importar `App\Models\User` y `Spatie\Permission\Models\Role`
   - Implementar 5 metodos siguiendo la firma de UserPolicy:
     - `viewAny(User $user): bool` - retorna `$user->can('roles.view')`
     - `view(User $user, Role $role): bool` - retorna `$user->can('roles.view')`
     - `create(User $user): bool` - retorna `$user->can('roles.create')`
     - `update(User $user, Role $role): bool` - primero verificar si el rol es protegido (admin, editor, user); si es protegido retornar false. Si no, retornar `$user->can('roles.update')`
     - `delete(User $user, Role $role): bool` - misma logica que update pero con permiso `roles.delete`
   - La lista de roles protegidos debe usar `in_array($role->name, ['admin', 'editor', 'user'])` directamente en la Policy (consistente con el spec)
   - NO agregar metodos restore ni forceDelete (Role de Spatie no usa soft deletes)

2. **Modificar `app/Providers/AuthServiceProvider.php`**
   - Agregar import de `App\Policies\RolePolicy`
   - Agregar import de `Spatie\Permission\Models\Role` (el modelo)
   - Agregar entrada al array `$policies`: `Role::class => RolePolicy::class`
   - NO modificar el Gate::before existente (admins siguen pasando todos los checks)

### Validacion

- Ejecutar `./vendor/bin/phpunit tests/Feature/Api/RoleControllerTest.php`
- Los tests existentes DEBEN seguir pasando tal como estan (la Policy aun no se usa en el controller, se sigue usando el check manual). Este paso solo crea los archivos; la integracion se hace en Fase B4.
- Verificar que el archivo RolePolicy compile sin errores: `php artisan tinker --execute="new \App\Policies\RolePolicy()"`
- Verificar que la Policy este registrada: `php artisan policy:list` (o verificar manualmente en el provider)

### Dependencias
- Ninguna. Esta es la primera fase.

---

## Fase B2: FormRequests de Validacion (Store y Update)

### Objetivo
Extraer la logica de validacion inline de los metodos `store()` y `update()` del RoleController a FormRequest classes dedicadas. Esto sigue el patron establecido por StoreUserRequest y UpdateUserRequest, separando validacion del controller y agregando la regla regex para kebab-case en nombres de rol.

### Archivos

| Archivo | Accion | Patron de referencia |
|---------|--------|---------------------|
| `app/Http/Requests/Role/StoreRoleRequest.php` | **CREAR** | `app/Http/Requests/User/StoreUserRequest.php` |
| `app/Http/Requests/Role/UpdateRoleRequest.php` | **CREAR** | `app/Http/Requests/User/UpdateUserRequest.php` |

### Pasos

1. **Crear directorio `app/Http/Requests/Role/`**
   - Verificar que existe `app/Http/Requests/User/` como referencia de la estructura de carpetas

2. **Crear `app/Http/Requests/Role/StoreRoleRequest.php`**
   - Namespace: `App\Http\Requests\Role`
   - Extender `Illuminate\Foundation\Http\FormRequest`
   - Metodo `authorize(): bool`:
     - Retornar `$this->user()->can('roles.create')` (mismo patron que StoreUserRequest)
   - Metodo `rules(): array`:
     - `name`: required, string, max:255, unique:roles, regex:/^[a-z0-9-]+$/ (kebab-case - NUEVA regla que no existe en el controller actual)
     - `permissions`: sometimes, array
     - `permissions.*`: exists:permissions,name
   - Metodo `messages(): array`:
     - `name.unique`: 'A role with this name already exists.'
     - `name.regex`: 'Role name must contain only lowercase letters, numbers, and hyphens.'
     - `permissions.*.exists`: 'One or more selected permissions do not exist.'

3. **Crear `app/Http/Requests/Role/UpdateRoleRequest.php`**
   - Namespace: `App\Http\Requests\Role`
   - Extender `Illuminate\Foundation\Http\FormRequest`
   - Importar `Illuminate\Validation\Rule`
   - Metodo `authorize(): bool`:
     - Retornar `$this->user()->can('roles.update')` (mismo patron que UpdateUserRequest)
   - Metodo `rules(): array`:
     - Obtener ID del rol con `$this->route('role')->id` (notar: route model binding inyecta el objeto Role, no un ID como en UpdateUserRequest que usa `$this->route('user')`)
     - `name`: sometimes, string, max:255, regex:/^[a-z0-9-]+$/, Rule::unique('roles')->ignore($roleId)
     - `permissions`: sometimes, array
     - `permissions.*`: exists:permissions,name
   - Metodo `messages(): array`:
     - Mismos mensajes que StoreRoleRequest

### Validacion

- Ejecutar `./vendor/bin/phpunit tests/Feature/Api/RoleControllerTest.php`
- Los tests existentes DEBEN seguir pasando (los FormRequests aun no estan conectados al controller). Este paso solo crea los archivos; la integracion se hace en Fase B4.
- Verificar que los archivos compilen: `php artisan tinker --execute="new \App\Http\Requests\Role\StoreRoleRequest()"` y lo mismo para UpdateRoleRequest.

### Dependencias
- Ninguna. Puede ejecutarse en paralelo con Fase B1.

---

## Fase B3: Paginacion en RoleRepository

### Objetivo
Agregar soporte de paginacion al repositorio de roles, siguiendo el patron exacto del metodo `paginate()` de UserRepository. Esto permite que el endpoint `index` retorne resultados paginados con busqueda y ordenamiento.

### Archivos

| Archivo | Accion | Patron de referencia |
|---------|--------|---------------------|
| `app/Repositories/Contracts/RoleRepositoryInterface.php` | **MODIFICAR** | `app/Repositories/Contracts/UserRepositoryInterface.php` |
| `app/Repositories/Eloquent/RoleRepository.php` | **MODIFICAR** | `app/Repositories/Eloquent/UserRepository.php` (metodo paginate, lineas 38-59) |

### Pasos

1. **Modificar `app/Repositories/Contracts/RoleRepositoryInterface.php`**
   - Agregar import: `use Illuminate\Contracts\Pagination\LengthAwarePaginator`
   - Agregar firma del metodo al interface: `public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator`
   - Mantener el metodo `all()` existente (sigue siendo util para selects y dropdowns donde no se necesita paginacion)

2. **Modificar `app/Repositories/Eloquent/RoleRepository.php`**
   - Agregar import: `use Illuminate\Contracts\Pagination\LengthAwarePaginator`
   - Implementar metodo `paginate()` con la siguiente logica:
     - Crear query base: `Role::query()->with('permissions')`
     - Filtro de busqueda: si `$filters['search']` no esta vacio, agregar `where('name', 'like', '%'.$filters['search'].'%')`
     - Ordenamiento: obtener `sort_by` de filtros (default: 'created_at') y `sort_direction` (default: 'desc'). Aplicar `orderBy()`
     - Retornar `$query->paginate($perPage)`
   - El metodo es mas simple que el de UserRepository porque Role solo tiene campo `name` para buscar (no tiene email ni filtro por role)

### Validacion

- Ejecutar `./vendor/bin/phpunit tests/Feature/Api/RoleControllerTest.php`
- Los tests existentes DEBEN seguir pasando (el controller aun usa `$this->roleRepository->all()`, no `paginate()`). La integracion se hace en Fase B4.
- Verificar que la interface y la implementacion estan sincronizadas: el binding en el ServiceProvider no debe fallar. Ejecutar `php artisan tinker --execute="app(\App\Repositories\Contracts\RoleRepositoryInterface::class)->paginate()"`

### Dependencias
- Ninguna. Puede ejecutarse en paralelo con Fases B1 y B2.

---

## Fase B4: Refactorizacion del RoleController

### Objetivo
Refactorizar el RoleController para usar la RolePolicy (via `$this->authorize()`), los FormRequests (via type-hint) y la paginacion del repositorio. Esto elimina los checks manuales de autorizacion, la validacion inline y el retorno de coleccion completa. El controller resultante debe seguir el patron del UserController.

### Archivos

| Archivo | Accion | Patron de referencia |
|---------|--------|---------------------|
| `app/Http/Controllers/Api/RoleController.php` | **MODIFICAR** | `app/Http/Controllers/Api/UserController.php` |

### Pasos

1. **Actualizar imports del controller**
   - Agregar: `use App\Http\Requests\Role\StoreRoleRequest`
   - Agregar: `use App\Http\Requests\Role\UpdateRoleRequest`
   - Eliminar: `use Illuminate\Http\Request` ya NO se puede eliminar porque `index()`, `show()` y `permissions()` aun lo usan como parametro. Mantenerlo.

2. **Refactorizar metodo `index()`**
   - Reemplazar `if (! $request->user()->can('roles.view'))` por `$this->authorize('viewAny', Role::class)`
   - Reemplazar `$this->roleRepository->all()` por `$this->roleRepository->paginate()`
   - Pasar filtros del request: `$request->only('search', 'sort_by', 'sort_direction')`
   - Pasar per_page: `(int) $request->get('per_page', 15)`
   - El return cambia de `response()->json(['data' => ...])` a `response()->json($roles)` (el paginator ya incluye 'data' como clave)
   - Actualizar la OpenAPI annotation para agregar parametros de query: search, sort_by, sort_direction, per_page. Seguir el patron exacto de la annotation de UserController.index()

3. **Refactorizar metodo `show()`**
   - Reemplazar `if (! $request->user()->can('roles.view'))` por `$this->authorize('view', $role)`
   - Mantener el `$role->load('permissions')` y el return sin cambios

4. **Refactorizar metodo `permissions()`**
   - Reemplazar `if (! $request->user()->can('roles.view'))` por `$this->authorize('viewAny', Role::class)`
   - Mantener el return sin cambios

5. **Refactorizar metodo `store()`**
   - Cambiar type-hint del parametro de `Request $request` a `StoreRoleRequest $request`
   - Eliminar el bloque `if (! $request->user()->can('roles.create'))` (autorizado en FormRequest)
   - Eliminar el bloque `$request->validate([...])` (validacion en FormRequest)
   - Mantener la llamada a `$this->roleRepository->create()` y el return sin cambios
   - Notar que `$request->name` y `$request->permissions` siguen funcionando porque FormRequest da acceso a los campos validados

6. **Refactorizar metodo `update()`**
   - Cambiar type-hint del parametro de `Request $request` a `UpdateRoleRequest $request`
   - Eliminar el bloque `if (! $request->user()->can('roles.update'))` (autorizado en FormRequest)
   - Eliminar el bloque `if ($this->roleRepository->isProtected($role))` con su return 422 (proteccion ahora en RolePolicy via `$this->authorize()`)
   - Agregar: `$this->authorize('update', $role)` al inicio del metodo (la Policy verifica si el rol es protegido y retorna 403)
   - Eliminar el bloque `$request->validate([...])` (validacion en FormRequest)
   - Mantener la llamada a `$this->roleRepository->update()` y el return sin cambios

7. **Refactorizar metodo `destroy()`**
   - Reemplazar `if (! $request->user()->can('roles.delete'))` por `$this->authorize('delete', $role)` (la Policy verifica si es protegido)
   - Eliminar el bloque `if ($this->roleRepository->isProtected($role))` con su return 422
   - Mantener la llamada a `$this->roleRepository->delete()` y el return sin cambios

### Cambios de comportamiento importantes

| Caso | Antes | Despues |
|------|-------|---------|
| Update rol protegido | 422 con mensaje "Cannot modify protected roles" | 403 (Policy) |
| Delete rol protegido | 422 con mensaje "Cannot delete protected roles" | 403 (Policy) |
| Index sin permiso | 403 con JSON manual | 403 via AuthorizationException |
| Store sin permiso | 403 con JSON manual | 403 via FormRequest authorize() |
| Listado de roles | Coleccion completa (sin paginacion) | Respuesta paginada con metadata (data, current_page, per_page, total, last_page) |

### Validacion

- Ejecutar `./vendor/bin/phpunit tests/Feature/Api/RoleControllerTest.php`
- ATENCION: Algunos tests van a FALLAR despues de este paso. Esto es esperado porque:
  - Los tests que esperan 422 para roles protegidos ahora recibiran 403
  - El test de index espera estructura `{data: [...]}` pero ahora la respuesta es paginada con estructura diferente
- Estos fallos se corrigen en la Fase B5
- Verificar manualmente (o via tinker) que los endpoints responden correctamente con Postman/curl si se desea

### Dependencias
- **Requiere Fase B1** (RolePolicy debe existir y estar registrada)
- **Requiere Fase B2** (StoreRoleRequest y UpdateRoleRequest deben existir)
- **Requiere Fase B3** (metodo paginate() debe existir en el repositorio)

---

## Fase B5: Actualizacion de Feature Tests

### Objetivo
Actualizar los tests existentes en RoleControllerTest para que reflejen los cambios de comportamiento introducidos por la Policy (codigos 403 en vez de 422 para roles protegidos), la paginacion (nueva estructura de respuesta en index), y agregar tests nuevos para cubrir la funcionalidad de busqueda/ordenamiento y la validacion regex del nombre.

### Archivos

| Archivo | Accion | Patron de referencia |
|---------|--------|---------------------|
| `tests/Feature/Api/RoleControllerTest.php` | **MODIFICAR** | `tests/Feature/Api/UserControllerTest.php` |

### Pasos

1. **Actualizar `test_admin_can_list_roles`**
   - Cambiar la estructura esperada de la respuesta. Actualmente verifica `['data' => [['id', 'name', 'permissions']]]`
   - Debe verificar estructura paginada: assertJsonStructure con claves `data`, `current_page`, `per_page`, `total`, `last_page`
   - Dentro de `data.*` seguir verificando `id`, `name`, `permissions`

2. **Actualizar `test_admin_cannot_update_protected_roles`**
   - Cambiar `assertStatus(422)` por `assertStatus(403)`
   - Eliminar `assertJson(['message' => 'Cannot modify protected roles'])` (la Policy no devuelve ese mensaje especifico; Laravel devuelve un mensaje generico de autorizacion)

3. **Actualizar `test_admin_cannot_delete_protected_roles`**
   - Cambiar `assertStatus(422)` por `assertStatus(403)`
   - Eliminar `assertJson(['message' => 'Cannot delete protected roles'])`

4. **Agregar test `test_index_supports_search_filter`**
   - Crear un rol custom en el setUp o en el test
   - Hacer GET a `/api/roles?search=custom`
   - Verificar que la respuesta contiene solo el rol buscado
   - Verificar que `total` en la respuesta refleja el filtro

5. **Agregar test `test_index_supports_pagination`**
   - Hacer GET a `/api/roles?per_page=2`
   - Verificar que `per_page` en la respuesta es 2
   - Verificar que `data` contiene como maximo 2 elementos

6. **Agregar test `test_create_role_validates_name_format`**
   - Intentar crear un rol con nombre que NO cumple kebab-case (ejemplo: 'Role Name' o 'UPPERCASE')
   - Verificar 422 con error de validacion en campo `name`
   - Esta validacion es NUEVA (no existia en el controller original, se agrega via StoreRoleRequest con regex)

7. **Verificar que los tests existentes que NO se modifican siguen pasando**
   - `test_regular_user_cannot_list_roles` - debe seguir dando 403
   - `test_admin_can_view_single_role` - debe seguir dando 200
   - `test_admin_can_list_permissions` - debe seguir dando 200
   - `test_admin_can_create_role` - debe seguir dando 201
   - `test_regular_user_cannot_create_role` - debe seguir dando 403
   - `test_admin_can_update_custom_role` - debe seguir dando 200
   - `test_admin_can_delete_custom_role` - debe seguir dando 200
   - `test_unauthenticated_user_cannot_access_roles` - debe seguir dando 401
   - `test_create_role_validates_unique_name` - debe seguir dando 422
   - `test_create_role_validates_permissions_exist` - debe seguir dando 422

### Validacion

- Ejecutar `./vendor/bin/phpunit tests/Feature/Api/RoleControllerTest.php` - TODOS los tests deben pasar (existentes actualizados + nuevos)
- Ejecutar `./vendor/bin/phpunit` para verificar que ningun otro test se rompio
- Verificar el conteo total de tests: debe ser al menos 15 (12 existentes - 0 eliminados + 3 nuevos)

### Dependencias
- **Requiere Fase B4** (el controller debe estar refactorizado para que los tests reflejen el nuevo comportamiento)

---

## Diagrama de Dependencias

```
B1 (RolePolicy) ──────────┐
                           ├──> B4 (Refactorizar Controller) ──> B5 (Actualizar Tests)
B2 (FormRequests) ─────────┤
                           │
B3 (Paginacion Repo) ──────┘
```

Las fases B1, B2 y B3 son independientes entre si y pueden ejecutarse en paralelo. La fase B4 requiere las tres anteriores. La fase B5 requiere B4.

---

## Resumen de Archivos

### Archivos Nuevos (3)
| Archivo | Fase | Descripcion |
|---------|------|-------------|
| `app/Policies/RolePolicy.php` | B1 | Policy con autorizacion y proteccion de roles |
| `app/Http/Requests/Role/StoreRoleRequest.php` | B2 | Validacion para creacion de roles |
| `app/Http/Requests/Role/UpdateRoleRequest.php` | B2 | Validacion para actualizacion de roles |

### Archivos Modificados (4)
| Archivo | Fase | Descripcion del cambio |
|---------|------|----------------------|
| `app/Providers/AuthServiceProvider.php` | B1 | Agregar Role => RolePolicy al array $policies |
| `app/Repositories/Contracts/RoleRepositoryInterface.php` | B3 | Agregar metodo paginate() al interface |
| `app/Repositories/Eloquent/RoleRepository.php` | B3 | Implementar metodo paginate() con filtros |
| `app/Http/Controllers/Api/RoleController.php` | B4 | Refactorizar para usar Policy, FormRequests y paginacion |

### Archivos de Test Modificados (1)
| Archivo | Fase | Descripcion del cambio |
|---------|------|----------------------|
| `tests/Feature/Api/RoleControllerTest.php` | B5 | Actualizar assertions y agregar 3 tests nuevos |

---

## Criterio de Completado Final

- [x] Todos los tests existentes actualizados y pasando (13 originales actualizados)
- [x] 3 tests nuevos agregados y pasando (search, pagination, name format)
- [x] Suite completa `./vendor/bin/phpunit` pasa sin regresiones (124 tests, 403 assertions)
- [x] RolePolicy registrada y funcional (B1 completada)
- [x] FormRequests con validacion regex para nombre kebab-case (B2 completada)
- [x] Endpoint index retorna respuesta paginada (B3 completada - paginate() en RoleRepository)
- [x] Roles protegidos retornan 403 en update y delete (B4 - via Policy + explicit check para admin bypass)
- [x] Controller sin checks manuales de autorizacion ni validacion inline (B4 - usa $this->authorize + FormRequests)
