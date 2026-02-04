# Plan de Implementación: Gestión de Roles y Permisos

**Proyecto:** Vristo POC - Laravel 12 + Vue 3 SPA
**Arquitectura:** Clean Architecture + Repository Pattern + Service Layer
**Fecha:** 2026-02-02
**Autor:** Arquitecto de Software

---

## RESUMEN EJECUTIVO

Este plan detalla la implementación de la gestión completa de roles en el sistema, **excluyendo** funcionalidades no esenciales como CRUD de permisos (son estáticos), endpoints de sync/attach/detach adicionales, campos extras (description/display_name), vistas standalone de permisos, y unit tests de repositorios.

### Estado Actual Validado

**Backend (COMPLETO):**
- ✅ Spatie Permission v6.24 instalado y configurado
- ✅ 5 tablas relacionales activas
- ✅ 3 roles protegidos: admin, editor, user
- ✅ 13 permisos activos
- ✅ RoleController CRUD funcional (endpoints: index, show, store, update, destroy, permissions)
- ✅ RoleRepository + Interface implementado con `isProtected()`
- ✅ UserPolicy completa con lógica de negocio
- ✅ Gate global: admins pasan todos los checks
- ✅ 17 tests de RoleController pasando
- ⚠️ **FALTA:** RolePolicy (para consistencia arquitectural)
- ⚠️ **FALTA:** FormRequests de validación (StoreRoleRequest, UpdateRoleRequest)
- ⚠️ **FALTA:** Paginación en listado de roles

**Frontend (PARCIAL):**
- ✅ Tipos TypeScript completos en `types/role.ts`
- ✅ roleService.ts con getRoles(), getRole(id), getPermissions()
- ✅ Directivas `v-can` y `v-role` globales
- ✅ Composable `usePermissions()` funcional
- ✅ Auth store con getters de roles/permissions
- ✅ Navigation guards con soporte de meta.permission/meta.role
- ✅ Traducciones en en.json, es.json, fr.json
- ⚠️ **FALTA:** Completar roleService.ts (createRole, updateRole, deleteRole)
- ⚠️ **FALTA:** Store de roles (patrón de user.ts)
- ⚠️ **FALTA:** Vistas completas (list, create, edit)
- ⚠️ **FALTA:** Rutas en router
- ⚠️ **FALTA:** Menú en sidebar

### Decisiones de Arquitectura

1. **NO se implementará:**
   - CRUD de permisos (son estáticos, definidos por desarrollador en seeders)
   - Endpoints `/api/roles/{role}/sync-permissions` (se usa syncPermissions en update)
   - Campos `description` o `display_name` en tablas (Spatie usa solo `name` y `guard_name`)
   - Vista standalone `/admin/permissions`
   - Unit tests de repositorios (ya hay feature tests completos)

2. **SÍ se implementará:**
   - RolePolicy siguiendo patrón de UserPolicy
   - FormRequests para validación consistente
   - Paginación en listado de roles
   - Store de roles siguiendo patrón de user.ts
   - Vistas CRUD completas con UX de vristo
   - Protección de roles protegidos en UI y backend

---

## FASE 1: BACKEND - CONSOLIDACIÓN DE ARQUITECTURA

### 1.1. Crear RolePolicy

**Archivo:** `app/Policies/RolePolicy.php`

**Propósito:** Centralizar autorización de roles siguiendo patrón de UserPolicy.

**Patrón de referencia:** `app/Policies/UserPolicy.php`

**Contenido:**
```php
<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        // No se pueden editar roles protegidos
        if (in_array($role->name, ['admin', 'editor', 'user'])) {
            return false;
        }

        return $user->can('roles.update');
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        // No se pueden eliminar roles protegidos
        if (in_array($role->name, ['admin', 'editor', 'user'])) {
            return false;
        }

        return $user->can('roles.delete');
    }
}
```

**Dependencias:**
- Spatie Permission instalado ✅
- UserPolicy como referencia ✅

---

### 1.2. Registrar RolePolicy

**Archivo:** `app/Providers/AuthServiceProvider.php`

**Acción:** Agregar mapping de Policy.

**Cambio:**
```php
protected $policies = [
    User::class => UserPolicy::class,
    \Spatie\Permission\Models\Role::class => \App\Policies\RolePolicy::class, // NUEVO
];
```

**Patrón:** Seguir estructura existente de UserPolicy.

---

### 1.3. Crear StoreRoleRequest

**Archivo:** `app/Http/Requests/Role/StoreRoleRequest.php`

**Propósito:** Validación de creación de roles.

**Patrón de referencia:** `app/Http/Requests/User/StoreUserRequest.php`

**Contenido:**
```php
<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('roles.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles', 'regex:/^[a-z0-9-]+$/'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A role with this name already exists.',
            'name.regex' => 'Role name must contain only lowercase letters, numbers, and hyphens.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
```

**Notas:**
- Regex para forzar naming convention (lowercase-kebab-case).
- Valida que permisos existan en BD.

---

### 1.4. Crear UpdateRoleRequest

**Archivo:** `app/Http/Requests/Role/UpdateRoleRequest.php`

**Patrón de referencia:** `app/Http/Requests/User/UpdateUserRequest.php`

**Contenido:**
```php
<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('roles.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')->id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles')->ignore($roleId),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A role with this name already exists.',
            'name.regex' => 'Role name must contain only lowercase letters, numbers, and hyphens.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
```

---

### 1.5. Refactorizar RoleController para usar Policy y FormRequests

**Archivo:** `app/Http/Controllers/Api/RoleController.php`

**Acción:** Reemplazar validación inline por FormRequests y usar Policy via `$this->authorize()`.

**Cambios específicos:**

**Imports (agregar):**
```php
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
```

**Método `index()`:**
```php
public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Role::class); // Reemplazar can() inline

    return response()->json(['data' => $this->roleRepository->all()]);
}
```

**Método `show()`:**
```php
public function show(Request $request, Role $role): JsonResponse
{
    $this->authorize('view', $role); // Reemplazar can() inline

    $role->load('permissions');

    return response()->json($role);
}
```

**Método `permissions()`:**
```php
public function permissions(Request $request): JsonResponse
{
    $this->authorize('viewAny', Role::class); // Cambiar de roles.view a viewAny

    return response()->json([
        'data' => $this->roleRepository->allPermissions(),
        'grouped' => $this->roleRepository->permissionsGrouped(),
    ]);
}
```

**Método `store()` - REFACTOR COMPLETO:**
```php
public function store(StoreRoleRequest $request): JsonResponse
{
    // Validación y autorización ya hechas por FormRequest
    $role = $this->roleRepository->create([
        'name' => $request->name,
        'permissions' => $request->permissions ?? [],
    ]);

    return response()->json([
        'message' => 'Role created successfully',
        'role' => $role,
    ], 201);
}
```

**Método `update()` - REFACTOR COMPLETO:**
```php
public function update(UpdateRoleRequest $request, Role $role): JsonResponse
{
    $this->authorize('update', $role); // Policy chequea si es protegido

    $role = $this->roleRepository->update($role, [
        'name' => $request->name,
        'permissions' => $request->permissions,
    ]);

    return response()->json([
        'message' => 'Role updated successfully',
        'role' => $role,
    ]);
}
```

**Método `destroy()` - REFACTOR:**
```php
public function destroy(Request $request, Role $role): JsonResponse
{
    $this->authorize('delete', $role); // Policy chequea si es protegido

    $this->roleRepository->delete($role);

    return response()->json(['message' => 'Role deleted successfully']);
}
```

**Eliminaciones:**
- Quitar todos los checks manuales de `$request->user()->can()`
- Quitar check manual de `isProtected()` (ahora en Policy)
- Quitar `$request->validate()` inline (ahora en FormRequest)

**Notas:**
- Mantener OpenAPI annotations existentes.
- Controller queda < 15 líneas por método.
- Lógica de validación y autorización delegada.

---

### 1.6. Agregar Paginación a RoleRepository

**Archivo:** `app/Repositories/Contracts/RoleRepositoryInterface.php`

**Acción:** Agregar método `paginate()`.

**Código:**
```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
```

---

**Archivo:** `app/Repositories/Eloquent/RoleRepository.php`

**Acción:** Implementar paginación con búsqueda y orden.

**Patrón de referencia:** `app/Repositories/Eloquent/UserRepository.php`

**Código (agregar):**
```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
{
    $query = Role::query()->with('permissions');

    // Search filter
    if (!empty($filters['search'])) {
        $query->where('name', 'like', '%' . $filters['search'] . '%');
    }

    // Sort
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortDirection = $filters['sort_direction'] ?? 'desc';
    $query->orderBy($sortBy, $sortDirection);

    return $query->paginate($perPage);
}
```

---

### 1.7. Actualizar RoleController.index() para Paginación

**Archivo:** `app/Http/Controllers/Api/RoleController.php`

**Método `index()`:**
```php
public function index(Request $request): JsonResponse
{
    $this->authorize('viewAny', Role::class);

    $roles = $this->roleRepository->paginate(
        $request->only('search', 'sort_by', 'sort_direction'),
        (int) $request->get('per_page', 15)
    );

    return response()->json($roles);
}
```

**OpenAPI annotation (actualizar):**
```php
#[OA\Get(
    path: '/api/roles',
    summary: 'List roles (paginated)',
    tags: ['Roles'],
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at')),
        new OA\Parameter(name: 'sort_direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated role list'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Unauthorized'),
    ]
)]
```

---

### 1.8. Tests: Actualizar RoleController Feature Tests

**Archivo:** `tests/Feature/Api/RoleControllerTest.php`

**Acción:** Actualizar tests existentes para reflejar cambios de Policy y paginación.

**Cambios necesarios:**

1. **Tests de autorización:** Ahora usan Policy, pueden retornar códigos diferentes.
2. **Test `test_index_returns_paginated_roles`:** Verificar estructura paginada.
3. **Test de roles protegidos:** Ahora Policy retorna 403 en vez de 422.

**Ejemplo de cambio:**
```php
// ANTES
$response->assertStatus(422)
    ->assertJson(['message' => 'Cannot modify protected roles']);

// DESPUÉS
$response->assertStatus(403); // Policy retorna forbidden
```

**No se crean nuevos tests de repositorio** (decisión de no implementar unit tests de repos).

---

## FASE 2: FRONTEND - COMPLETAR GESTIÓN DE ROLES

### 2.1. Completar roleService.ts

**Archivo:** `resources/js/src/services/roleService.ts`

**Acción:** Agregar métodos CRUD faltantes.

**Patrón de referencia:** `resources/js/src/services/userService.ts`

**Código a agregar:**

```typescript
export interface CreateRoleData {
    name: string;
    permissions?: string[];
}

export interface UpdateRoleData {
    name?: string;
    permissions?: string[];
}

export interface RoleFilters {
    search?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface PaginatedRolesResponse {
    data: RoleWithPermissions[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
    links?: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}

const roleService = {
    // ... métodos existentes (getRoles, getRole, getPermissions) ...

    /**
     * Get paginated roles
     */
    async getRolesPaginated(filters?: RoleFilters): Promise<PaginatedRolesResponse> {
        const params = new URLSearchParams();

        if (filters?.search) params.append('search', filters.search);
        if (filters?.sort_by) params.append('sort_by', filters.sort_by);
        if (filters?.sort_direction) params.append('sort_direction', filters.sort_direction);
        if (filters?.per_page) params.append('per_page', filters.per_page.toString());
        if (filters?.page) params.append('page', filters.page.toString());

        const response = await api.get<PaginatedRolesResponse>(`/roles?${params.toString()}`);
        return response.data;
    },

    /**
     * Create a new role
     */
    async createRole(data: CreateRoleData): Promise<{ message: string; role: RoleWithPermissions }> {
        const response = await api.post<{ message: string; role: RoleWithPermissions }>('/roles', data);
        return response.data;
    },

    /**
     * Update a role
     */
    async updateRole(id: number, data: UpdateRoleData): Promise<{ message: string; role: RoleWithPermissions }> {
        const response = await api.put<{ message: string; role: RoleWithPermissions }>(`/roles/${id}`, data);
        return response.data;
    },

    /**
     * Delete a role
     */
    async deleteRole(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/roles/${id}`);
        return response.data;
    },
};

export default roleService;
```

**Dependencias:**
- `services/api.ts` ✅
- `types/role.ts` ✅
- `types/pagination.ts` ✅

---

### 2.2. Crear stores/role.ts

**Archivo:** `resources/js/src/stores/role.ts`

**Propósito:** Store Pinia para gestión de roles.

**Patrón de referencia:** `resources/js/src/stores/user.ts` (copiar estructura completa)

**Contenido completo:**

```typescript
/**
 * Role Store
 * Manages role list state, pagination, and CRUD operations
 */

import { defineStore } from 'pinia';
import roleService, { type RoleFilters, type CreateRoleData, type UpdateRoleData } from '@/services/roleService';
import type { Role, Permission, PermissionGroup } from '@/types/role';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface RoleState {
    roles: Role[];
    currentRole: Role | null;
    permissions: Permission[];
    permissionsGrouped: Record<string, Permission[]>;
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: RoleFilters;
    isLoading: boolean;
    error: string | null;
}

export const useRoleStore = defineStore('role', {
    state: (): RoleState => ({
        roles: [],
        currentRole: null,
        permissions: [],
        permissionsGrouped: {},
        meta: null,
        links: null,
        filters: {
            search: '',
            sort_by: 'name',
            sort_direction: 'asc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getRoleById: (state) => (id: number): Role | undefined => {
            return state.roles.find((role) => role.id === id);
        },

        totalRoles: (state): number => {
            return state.meta?.total ?? 0;
        },

        currentPage: (state): number => {
            return state.meta?.current_page ?? 1;
        },

        lastPage: (state): number => {
            return state.meta?.last_page ?? 1;
        },

        hasNextPage: (state): boolean => {
            return state.links?.next !== null;
        },

        hasPrevPage: (state): boolean => {
            return state.links?.prev !== null;
        },

        permissionGroups: (state): PermissionGroup[] => {
            return Object.entries(state.permissionsGrouped).map(([key, perms]) => ({
                name: key,
                display_name: key.charAt(0).toUpperCase() + key.slice(1),
                permissions: perms,
            }));
        },

        protectedRoles: (): string[] => {
            return ['admin', 'editor', 'user'];
        },

        isProtectedRole: (state) => (roleName: string): boolean => {
            return ['admin', 'editor', 'user'].includes(roleName);
        },
    },

    actions: {
        async fetchRoles(filters?: Partial<RoleFilters>) {
            this.isLoading = true;
            this.error = null;

            // Merge filters
            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await roleService.getRolesPaginated(this.filters);
                this.roles = response.data;

                // Handle Laravel's pagination format
                if ('meta' in response) {
                    this.meta = response.meta;
                    this.links = response.links;
                } else {
                    // Map Laravel's flat format to our meta structure
                    this.meta = {
                        current_page: (response as any).current_page,
                        from: (response as any).from,
                        last_page: (response as any).last_page,
                        per_page: (response as any).per_page,
                        to: (response as any).to,
                        total: (response as any).total,
                        path: (response as any).path || '',
                    };
                    this.links = {
                        first: (response as any).first_page_url || null,
                        last: (response as any).last_page_url || null,
                        prev: (response as any).prev_page_url || null,
                        next: (response as any).next_page_url || null,
                    };
                }
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch roles';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPermissions() {
            try {
                const response = await roleService.getPermissions();
                this.permissions = response.data;
                this.permissionsGrouped = response.grouped;
            } catch (error: any) {
                console.error('Failed to fetch permissions:', error);
            }
        },

        async fetchRole(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentRole = await roleService.getRole(id);
                return this.currentRole;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createRole(data: CreateRoleData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.createRole(data);
                // Refresh the list
                await this.fetchRoles();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateRole(id: number, data: UpdateRoleData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.updateRole(id, data);
                // Update role in list
                const index = this.roles.findIndex((r) => r.id === id);
                if (index !== -1) {
                    this.roles[index] = response.role;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteRole(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.deleteRole(id);
                // Remove from list
                this.roles = this.roles.filter((r) => r.id !== id);
                // Update total count
                if (this.meta) {
                    this.meta.total--;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        setSearch(search: string) {
            this.filters.search = search;
            this.filters.page = 1; // Reset to first page
        },

        setSort(sortBy: string, direction: 'asc' | 'desc') {
            this.filters.sort_by = sortBy;
            this.filters.sort_direction = direction;
        },

        setPage(page: number) {
            this.filters.page = page;
        },

        setPerPage(perPage: number) {
            this.filters.per_page = perPage;
            this.filters.page = 1; // Reset to first page
        },

        resetFilters() {
            this.filters = {
                search: '',
                sort_by: 'name',
                sort_direction: 'asc',
                per_page: 15,
                page: 1,
            };
        },

        clearCurrentRole() {
            this.currentRole = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
```

**Dependencias:**
- Pinia ✅
- roleService.ts ✅
- types/role.ts ✅
- types/pagination.ts ✅

**Notas:**
- Getter `isProtectedRole()` para UI conditional rendering.
- Paginación idéntica a user store.
- Store de permisos compartido (no se crea store separado).

---

### 2.3. Ajustar types/role.ts

**Archivo:** `resources/js/src/types/role.ts`

**Acción:** Simplificar interfaces eliminando campos no usados.

**IMPORTANTE:** El backend NO usa `display_name` ni `description` (Spatie solo tiene `name` y `guard_name`).

**Cambios:**

```typescript
export interface Role {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
    // ELIMINAR: display_name, description
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
    // ELIMINAR: display_name, description
}

export interface CreateRoleData {
    name: string;
    permissions?: string[]; // Array de permission names (no IDs)
    // ELIMINAR: display_name, description
}

export interface UpdateRoleData {
    name?: string;
    permissions?: string[];
    // ELIMINAR: display_name, description
}

// ELIMINAR: AssignRoleData (no se usa, roles se asignan en UserController)

export interface PermissionGroup {
    name: string;
    display_name: string; // Computed en frontend
    permissions: Permission[];
}

// ... PERMISSIONS y ROLES constants (mantener) ...
```

**Razón:** El backend solo soporta `name` y `guard_name`. Usar `display_name` generaría desincronización.

---

### 2.4. Crear vista list.vue

**Archivo:** `resources/js/src/views/admin/roles/list.vue`

**Propósito:** Listado paginado de roles con búsqueda, filtros, y acciones CRUD.

**Patrón de referencia:** `resources/js/src/views/admin/users/list.vue` (estructura completa)

**Características:**
- vue3-datatable server mode
- Skeleton loader inicial
- Empty states con/sin filtros
- Mobile cards responsive
- Protección de roles protegidos (UI muestra badge, deshabilita edit/delete)
- Búsqueda debounced
- Paginación
- Accesibilidad (a11y)

**Estructura (basada en users/list.vue):**

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('sidebar.roles') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.role_management') }}</h5>
                <router-link
                    v-can="'roles.create'"
                    to="/admin/roles/create"
                    class="btn btn-primary gap-2"
                >
                    <icon-plus class="w-5 h-5" />
                    {{ $t('roles.add_role') }}
                </router-link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5" role="search" aria-label="Filter roles">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            :placeholder="$t('roles.search_by_name')"
                            :aria-label="$t('roles.search_by_name')"
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span v-if="isDebouncing" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                            <icon-search v-else class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>

                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" aria-label="Results per page" @change="changePerPage">
                        <option :value="10">10 per page</option>
                        <option :value="15">15 per page</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                    </select>
                </div>
            </div>

            <!-- Skeleton Loader -->
            <div v-if="showSkeleton" class="animate-pulse">
                <!-- Similar a users/list.vue -->
                <div class="table-responsive">
                    <table class="table-hover">
                        <thead>
                            <tr>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in 5" :key="i">
                                <td><div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td>
                                    <div class="flex gap-2">
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Results area -->
            <div v-else-if="!showEmptyState" aria-live="polite">
                <!-- Desktop Table -->
                <div class="datatable hidden md:block">
                    <vue3-datatable
                        :key="`dt-${perPage}`"
                        :rows="roleStore.roles"
                        :columns="columns"
                        :totalRows="roleStore.totalRoles"
                        :isServerMode="true"
                        :loading="roleStore.isLoading"
                        :sortable="true"
                        :sortColumn="sortColumn"
                        :sortDirection="sortDirection"
                        :pageSize="perPage"
                        :page="currentPage"
                        @change="handleTableChange"
                        skin="whitespace-nowrap bh-table-hover"
                    >
                        <!-- Name Column -->
                        <template #name="data">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">{{ data.value.name }}</span>
                                <span v-if="isProtectedRole(data.value.name)" class="badge badge-outline-secondary text-xs">
                                    Protected
                                </span>
                            </div>
                        </template>

                        <!-- Permissions Column -->
                        <template #permissions="data">
                            <span class="text-gray-500">{{ data.value.permissions?.length || 0 }} permissions</span>
                        </template>

                        <!-- Created At Column -->
                        <template #created_at="data">
                            <span>{{ formatDate(data.value.created_at) }}</span>
                        </template>

                        <!-- Actions Column -->
                        <template #actions="data">
                            <div class="flex items-center gap-2">
                                <tippy content="View">
                                    <router-link
                                        :to="`/admin/roles/${data.value.id}`"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'roles.update'" :content="isProtectedRole(data.value.name) ? 'Cannot edit protected role' : 'Edit'">
                                    <router-link
                                        v-if="!isProtectedRole(data.value.name)"
                                        :to="`/admin/roles/${data.value.id}/edit`"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </router-link>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary p-1.5 opacity-50 cursor-not-allowed"
                                        disabled
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </button>
                                </tippy>
                                <tippy v-can="'roles.delete'" :content="isProtectedRole(data.value.name) ? 'Cannot delete protected role' : 'Delete'">
                                    <button
                                        v-if="!isProtectedRole(data.value.name)"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
                                        @click="confirmDelete(data.value)"
                                    >
                                        <icon-trash-lines class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary p-1.5 opacity-50 cursor-not-allowed"
                                        disabled
                                    >
                                        <icon-trash-lines class="w-4 h-4" />
                                    </button>
                                </tippy>
                            </div>
                        </template>
                    </vue3-datatable>
                </div>

                <!-- Mobile Cards (similar a users/list.vue) -->
                <div class="md:hidden space-y-3">
                    <!-- ... mobile card layout ... -->
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="showEmptyState" class="text-center py-10" aria-live="polite">
                <template v-if="hasActiveFilters">
                    <icon-search class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('roles.no_results_found') }}</h3>
                    <p class="text-gray-500 mb-4">{{ $t('roles.no_roles_match_search') }}</p>
                    <button type="button" class="btn btn-outline-primary gap-2" @click="clearFilters">
                        <icon-x class="w-4 h-4" />
                        {{ $t('roles.clear_filters') }}
                    </button>
                </template>
                <template v-else>
                    <icon-shield class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('roles.no_roles_yet') }}</h3>
                    <p class="text-gray-500 mb-4">{{ $t('roles.get_started_by_adding_role') }}</p>
                    <router-link v-can="'roles.create'" to="/admin/roles/create" class="btn btn-primary gap-2">
                        <icon-plus class="w-5 h-5" />
                        {{ $t('roles.add_first_role') }}
                    </router-link>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';
import { useDebounce } from '@/composables/useDebounce';
import { formatDate } from '@/utils/formatters';
import type { Role } from '@/types/role';

// Icons
import IconPlus from '@/components/icon/icon-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconShield from '@/components/icon/icon-shield.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Role Management' });

const roleStore = useRoleStore();
const { confirmDelete: confirmDeleteDialog, success, error } = useNotification();
const { debounce, isDebouncing } = useDebounce(300);

// Local state
const searchQuery = ref('');
const perPage = ref(15);
const currentPage = ref(1);
const sortColumn = ref('name');
const sortDirection = ref<'asc' | 'desc'>('asc');
const initialLoading = ref(true);

// Computed
const hasActiveFilters = computed(() => !!searchQuery.value);
const showSkeleton = computed(() => initialLoading.value && roleStore.roles.length === 0);
const showEmptyState = computed(() => !roleStore.isLoading && !initialLoading.value && roleStore.roles.length === 0);

// Table columns
const columns = computed(() => [
    { field: 'name', title: 'Role Name', minWidth: '200px' },
    { field: 'permissions', title: 'Permissions', sort: false, width: '150px' },
    { field: 'created_at', title: 'Created', width: '150px' },
    { field: 'actions', title: 'Actions', sort: false, width: '150px', headerClass: 'justify-center' },
]);

// Methods
const isProtectedRole = (roleName: string): boolean => {
    return roleStore.isProtectedRole(roleName);
};

const debouncedSearch = () => {
    debounce(() => {
        currentPage.value = 1;
        fetchRoles();
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    currentPage.value = 1;
    fetchRoles();
};

const changePerPage = () => {
    currentPage.value = 1;
    fetchRoles();
};

interface TableChangePayload {
    current_page: number;
    pagesize: number;
    offset: number;
    sort_column: string;
    sort_direction: string;
    search: string;
    column_filters: any[];
    change_type: string;
}

const handleTableChange = (data: TableChangePayload) => {
    sortColumn.value = data.sort_column;
    sortDirection.value = data.sort_direction as 'asc' | 'desc';
    currentPage.value = data.current_page;
    perPage.value = data.pagesize;
    fetchRoles();
};

const fetchRoles = async () => {
    try {
        await roleStore.fetchRoles({
            search: searchQuery.value || undefined,
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
            per_page: perPage.value,
            page: currentPage.value,
        });
    } catch (err) {
        error('Failed to load roles');
    }
};

const confirmDelete = async (role: Role) => {
    const result = await confirmDeleteDialog(
        `Are you sure you want to delete "${role.name}"?`,
        'This action cannot be undone. Users with this role will lose its permissions.'
    );

    if (result.isConfirmed) {
        try {
            await roleStore.deleteRole(role.id);
            success('Role deleted successfully');
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete role');
        }
    }
};

// Initialize
onMounted(async () => {
    try {
        await fetchRoles();
    } finally {
        initialLoading.value = false;
    }
});
</script>
```

**Dependencias:**
- roleStore ✅
- useNotification ✅
- useDebounce ✅
- formatDate ✅
- vue3-datatable ✅

**Notas específicas:**
- NO incluir bulk delete (roles no se eliminan en masa típicamente).
- Protección visual de admin/editor/user con badge "Protected" y botones disabled.
- Columna de permissions muestra solo count (no lista completa).

---

### 2.5. Crear vista create.vue

**Archivo:** `resources/js/src/views/admin/roles/create.vue`

**Propósito:** Formulario de creación de roles con checkboxes de permisos agrupados.

**Patrón de referencia:** `resources/js/src/views/admin/users/create.vue`

**Características:**
- Vuelidate para validación
- Permisos agrupados por módulo (users, roles, profile, settings, activity-logs)
- Checkboxes "Select All" por grupo
- Error handling con backend sync
- Loading states

**Estructura resumida:**

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/roles" class="text-primary hover:underline">{{ $t('sidebar.roles') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('roles.create_role') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.create_new_role') }}</h5>
                <router-link to="/admin/roles" class="btn btn-outline-secondary gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('common.back_to_list') }}
                </router-link>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-5">
                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light dark:bg-danger-dark-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" @click="errorMessage = ''" aria-label="Dismiss error">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Role Name -->
                <div>
                    <label for="name" class="mb-2 block">
                        {{ $t('roles.role_name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., manager, support-agent"
                        class="form-input"
                        :class="{ 'border-danger': v$.name.$error }"
                        :aria-invalid="v$.name.$error"
                        :aria-describedby="v$.name.$error ? 'name-error' : 'name-hint'"
                    />
                    <template v-if="v$.name.$error">
                        <p id="name-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.name.$errors[0]?.$message }}</p>
                    </template>
                    <p id="name-hint" class="text-gray-500 text-xs mt-1">
                        {{ $t('roles.name_hint') }}
                    </p>
                </div>

                <!-- Permissions -->
                <div>
                    <label class="mb-3 block font-semibold">
                        {{ $t('roles.permissions') }}
                    </label>
                    <div class="space-y-4">
                        <div v-for="group in roleStore.permissionGroups" :key="group.name" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h6 class="font-semibold text-gray-700 dark:text-gray-300">{{ group.display_name }}</h6>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="form-checkbox"
                                        :checked="isGroupFullySelected(group.name)"
                                        :indeterminate.prop="isGroupPartiallySelected(group.name)"
                                        @change="toggleGroup(group.name, $event)"
                                    />
                                    <span class="text-sm text-gray-500">{{ $t('roles.select_all') }}</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <label
                                    v-for="permission in group.permissions"
                                    :key="permission.id"
                                    class="flex items-center gap-2 cursor-pointer"
                                >
                                    <input
                                        v-model="form.permissions"
                                        type="checkbox"
                                        :value="permission.name"
                                        class="form-checkbox"
                                    />
                                    <span class="text-sm">{{ formatPermissionName(permission.name) }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/admin/roles" class="btn btn-outline-secondary">
                        {{ $t('common.cancel') }}
                    </router-link>
                    <button
                        type="submit"
                        class="btn btn-primary gap-2"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                        <icon-save v-else class="w-4 h-4" />
                        {{ $t('common.create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';
import type { CreateRoleData } from '@/services/roleService';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Create Role' });

const router = useRouter();
const roleStore = useRoleStore();
const { success, error } = useNotification();

// Form state
const form = reactive<CreateRoleData>({
    name: '',
    permissions: [],
});

const isSubmitting = ref(false);
const errorMessage = ref('');

// Validation rules
const rules = computed(() => ({
    name: {
        required: helpers.withMessage('Role name is required', required),
        format: helpers.withMessage(
            'Role name must contain only lowercase letters, numbers, and hyphens',
            (value: string) => /^[a-z0-9-]+$/.test(value)
        ),
    },
}));

const v$ = useVuelidate(rules, form);

// Methods
const formatPermissionName = (name: string): string => {
    const parts = name.split('.');
    return parts[parts.length - 1].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const isGroupFullySelected = (groupName: string): boolean => {
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];
    return groupPerms.every(p => form.permissions.includes(p.name));
};

const isGroupPartiallySelected = (groupName: string): boolean => {
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];
    const selectedCount = groupPerms.filter(p => form.permissions.includes(p.name)).length;
    return selectedCount > 0 && selectedCount < groupPerms.length;
};

const toggleGroup = (groupName: string, event: Event) => {
    const target = event.target as HTMLInputElement;
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];

    if (target.checked) {
        // Select all
        groupPerms.forEach(p => {
            if (!form.permissions.includes(p.name)) {
                form.permissions.push(p.name);
            }
        });
    } else {
        // Deselect all
        form.permissions = form.permissions.filter(
            pName => !groupPerms.some(p => p.name === pName)
        );
    }
};

const handleSubmit = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        await roleStore.createRole(form);
        success('Role created successfully');
        router.push('/admin/roles');
    } catch (err: any) {
        // Sync backend validation errors
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            if (errors.name) {
                errorMessage.value = errors.name[0];
            } else {
                errorMessage.value = err.response.data.message || 'Failed to create role';
            }
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to create role';
        }
        error(errorMessage.value);
    } finally {
        isSubmitting.value = false;
    }
};

// Initialize
onMounted(async () => {
    await roleStore.fetchPermissions();
});
</script>
```

**Dependencias:**
- Vuelidate ✅
- roleStore ✅
- useNotification ✅
- Vue Router ✅

**Notas:**
- Checkboxes indeterminate para "Select All" parcial.
- Formato de permisos: `users.view` → "View".
- Validación de naming convention (lowercase-kebab-case).

---

### 2.6. Crear vista edit.vue

**Archivo:** `resources/js/src/views/admin/roles/edit.vue`

**Propósito:** Formulario de edición de roles con precarga de datos.

**Patrón de referencia:** `resources/js/src/views/admin/users/edit.vue`

**Diferencias con create.vue:**
- Pre-cargar role actual con `roleStore.fetchRole(id)`
- Deshabilitar edición si es rol protegido (admin/editor/user)
- Mostrar warning si rol está asignado a usuarios

**Estructura resumida:**

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/roles" class="text-primary hover:underline">{{ $t('sidebar.roles') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('roles.edit_role') }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="flex items-center justify-center py-10">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block"></span>
            </div>
        </div>

        <div v-else class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.edit_role') }}</h5>
                    <p v-if="isProtected" class="text-warning text-sm mt-1">
                        <icon-info-triangle class="w-4 h-4 inline mr-1" />
                        {{ $t('roles.protected_role_warning') }}
                    </p>
                </div>
                <router-link to="/admin/roles" class="btn btn-outline-secondary gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('common.back_to_list') }}
                </router-link>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-5">
                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" @click="errorMessage = ''" aria-label="Dismiss error">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Role Name -->
                <div>
                    <label for="name" class="mb-2 block">
                        {{ $t('roles.role_name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-input"
                        :class="{ 'border-danger': v$.name.$error }"
                        :disabled="isProtected"
                        :aria-invalid="v$.name.$error"
                    />
                    <template v-if="v$.name.$error">
                        <p role="alert" class="text-danger mt-1 text-sm">{{ v$.name.$errors[0]?.$message }}</p>
                    </template>
                </div>

                <!-- Permissions (similar a create.vue) -->
                <div>
                    <label class="mb-3 block font-semibold">
                        {{ $t('roles.permissions') }}
                    </label>
                    <div v-if="isProtected" class="bg-warning/10 border border-warning/30 rounded-lg p-4 mb-4">
                        <p class="text-warning text-sm">
                            {{ $t('roles.cannot_modify_protected_permissions') }}
                        </p>
                    </div>
                    <div class="space-y-4">
                        <!-- ... checkboxes agrupados (igual que create.vue) ... -->
                        <!-- Agregar :disabled="isProtected" a todos los checkboxes -->
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/admin/roles" class="btn btn-outline-secondary">
                        {{ $t('common.cancel') }}
                    </router-link>
                    <button
                        v-if="!isProtected"
                        type="submit"
                        class="btn btn-primary gap-2"
                        :disabled="isSubmitting"
                    >
                        <span v-if="isSubmitting" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                        <icon-save v-else class="w-4 h-4" />
                        {{ $t('common.save_changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';
import type { UpdateRoleData } from '@/services/roleService';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';

useMeta({ title: 'Edit Role' });

const router = useRouter();
const route = useRoute();
const roleStore = useRoleStore();
const { success, error } = useNotification();

const roleId = computed(() => parseInt(route.params.id as string));

// Form state
const form = reactive<UpdateRoleData>({
    name: '',
    permissions: [],
});

const isLoading = ref(true);
const isSubmitting = ref(false);
const errorMessage = ref('');
const isProtected = ref(false);

// Validation rules
const rules = computed(() => ({
    name: {
        required: helpers.withMessage('Role name is required', required),
        format: helpers.withMessage(
            'Role name must contain only lowercase letters, numbers, and hyphens',
            (value: string) => /^[a-z0-9-]+$/.test(value)
        ),
    },
}));

const v$ = useVuelidate(rules, form);

// Methods (similar a create.vue)
const formatPermissionName = (name: string): string => {
    const parts = name.split('.');
    return parts[parts.length - 1].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

const isGroupFullySelected = (groupName: string): boolean => {
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];
    return groupPerms.every(p => form.permissions.includes(p.name));
};

const isGroupPartiallySelected = (groupName: string): boolean => {
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];
    const selectedCount = groupPerms.filter(p => form.permissions.includes(p.name)).length;
    return selectedCount > 0 && selectedCount < groupPerms.length;
};

const toggleGroup = (groupName: string, event: Event) => {
    if (isProtected.value) return; // No permitir cambios en roles protegidos

    const target = event.target as HTMLInputElement;
    const groupPerms = roleStore.permissionGroups.find(g => g.name === groupName)?.permissions || [];

    if (target.checked) {
        groupPerms.forEach(p => {
            if (!form.permissions.includes(p.name)) {
                form.permissions.push(p.name);
            }
        });
    } else {
        form.permissions = form.permissions.filter(
            pName => !groupPerms.some(p => p.name === pName)
        );
    }
};

const handleSubmit = async () => {
    if (isProtected.value) {
        error('Cannot modify protected roles');
        return;
    }

    const isValid = await v$.value.$validate();
    if (!isValid) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        await roleStore.updateRole(roleId.value, form);
        success('Role updated successfully');
        router.push('/admin/roles');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            if (errors.name) {
                errorMessage.value = errors.name[0];
            } else {
                errorMessage.value = err.response.data.message || 'Failed to update role';
            }
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to update role';
        }
        error(errorMessage.value);
    } finally {
        isSubmitting.value = false;
    }
};

// Initialize
onMounted(async () => {
    try {
        await Promise.all([
            roleStore.fetchRole(roleId.value),
            roleStore.fetchPermissions(),
        ]);

        const role = roleStore.currentRole;
        if (role) {
            form.name = role.name;
            form.permissions = role.permissions?.map(p => p.name) || [];
            isProtected.value = roleStore.isProtectedRole(role.name);
        } else {
            error('Role not found');
            router.push('/admin/roles');
        }
    } catch (err) {
        error('Failed to load role');
        router.push('/admin/roles');
    } finally {
        isLoading.value = false;
    }
});
</script>
```

**Notas:**
- Deshabilitar todos los inputs si `isProtected === true`.
- Mostrar warning visual si es rol protegido.
- No mostrar botón "Save Changes" si es protegido.

---

### 2.7. Crear vista show.vue (opcional pero recomendado)

**Archivo:** `resources/js/src/views/admin/roles/show.vue`

**Propósito:** Vista de solo lectura de un rol.

**Patrón de referencia:** `resources/js/src/views/admin/users/show.vue` (si existe)

**Estructura simplificada:**

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/roles" class="text-primary hover:underline">{{ $t('sidebar.roles') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ currentRole?.name }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="flex items-center justify-center py-10">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block"></span>
            </div>
        </div>

        <div v-else-if="currentRole" class="space-y-5">
            <!-- Role Info Panel -->
            <div class="panel">
                <div class="flex items-center justify-between mb-5">
                    <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.role_details') }}</h5>
                    <div class="flex gap-2">
                        <router-link to="/admin/roles" class="btn btn-outline-secondary gap-2">
                            <icon-arrow-left class="w-4 h-4" />
                            {{ $t('common.back_to_list') }}
                        </router-link>
                        <router-link
                            v-if="!isProtectedRole && can('roles.update')"
                            :to="`/admin/roles/${currentRole.id}/edit`"
                            class="btn btn-primary gap-2"
                        >
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('common.edit') }}
                        </router-link>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $t('roles.role_name') }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <p class="text-lg font-semibold dark:text-white-light">{{ currentRole.name }}</p>
                            <span v-if="isProtectedRole" class="badge badge-outline-secondary">Protected</span>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $t('roles.created_at') }}</label>
                        <p class="mt-1 dark:text-white-light">{{ formatDate(currentRole.created_at) }}</p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $t('roles.updated_at') }}</label>
                        <p class="mt-1 dark:text-white-light">{{ formatDate(currentRole.updated_at) }}</p>
                    </div>
                </div>
            </div>

            <!-- Permissions Panel -->
            <div class="panel">
                <h5 class="font-semibold text-lg dark:text-white-light mb-5">
                    {{ $t('roles.permissions') }} ({{ currentRole.permissions?.length || 0 }})
                </h5>

                <div v-if="currentRole.permissions?.length" class="space-y-4">
                    <div v-for="group in groupedPermissions" :key="group.name" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <h6 class="font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ group.display_name }}</h6>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="permission in group.permissions"
                                :key="permission.id"
                                class="badge badge-outline-primary"
                            >
                                {{ formatPermissionName(permission.name) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-10">
                    <p class="text-gray-500">{{ $t('roles.no_permissions_assigned') }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { usePermissions } from '@/composables/usePermissions';
import { useNotification } from '@/composables/useNotification';
import { formatDate } from '@/utils/formatters';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';

const route = useRoute();
const router = useRouter();
const roleStore = useRoleStore();
const { can } = usePermissions();
const { error } = useNotification();

const roleId = computed(() => parseInt(route.params.id as string));
const isLoading = ref(true);

const currentRole = computed(() => roleStore.currentRole);
const isProtectedRole = computed(() => currentRole.value ? roleStore.isProtectedRole(currentRole.value.name) : false);

const groupedPermissions = computed(() => {
    if (!currentRole.value?.permissions) return [];

    const groups: Record<string, any> = {};
    currentRole.value.permissions.forEach(permission => {
        const groupName = permission.name.split('.')[0];
        if (!groups[groupName]) {
            groups[groupName] = {
                name: groupName,
                display_name: groupName.charAt(0).toUpperCase() + groupName.slice(1),
                permissions: [],
            };
        }
        groups[groupName].permissions.push(permission);
    });

    return Object.values(groups);
});

useMeta({ title: computed(() => currentRole.value?.name || 'Role Details') });

const formatPermissionName = (name: string): string => {
    const parts = name.split('.');
    return parts[parts.length - 1].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
};

onMounted(async () => {
    try {
        await roleStore.fetchRole(roleId.value);
        if (!currentRole.value) {
            error('Role not found');
            router.push('/admin/roles');
        }
    } catch (err) {
        error('Failed to load role');
        router.push('/admin/roles');
    } finally {
        isLoading.value = false;
    }
});
</script>
```

**Notas:**
- Vista de solo lectura (no forms).
- Muestra permisos agrupados visualmente.
- Botón "Edit" solo si no es protegido y tiene permiso.

---

### 2.8. Agregar Rutas en Router

**Archivo:** `resources/js/src/router/index.ts`

**Acción:** Agregar rutas de roles con guards de autorización.

**Patrón de referencia:** Rutas de `/admin/users/*` existentes.

**Código a agregar (dentro del objeto routes):**

```typescript
// Role Management
{
    path: '/admin/roles',
    name: 'admin.roles.index',
    component: () => import('../views/admin/roles/list.vue'),
    meta: {
        layout: 'app',
        permission: 'roles.view',
    },
},
{
    path: '/admin/roles/create',
    name: 'admin.roles.create',
    component: () => import('../views/admin/roles/create.vue'),
    meta: {
        layout: 'app',
        permission: 'roles.create',
    },
},
{
    path: '/admin/roles/:id',
    name: 'admin.roles.show',
    component: () => import('../views/admin/roles/show.vue'),
    meta: {
        layout: 'app',
        permission: 'roles.view',
    },
},
{
    path: '/admin/roles/:id/edit',
    name: 'admin.roles.edit',
    component: () => import('../views/admin/roles/edit.vue'),
    meta: {
        layout: 'app',
        permission: 'roles.update',
    },
},
```

**Ubicación:** Agregar después de las rutas de users (línea ~800-900 aprox).

**Notas:**
- Todas las rutas usan `meta.permission` para navigation guard automático.
- Layout `app` (no auth).

---

### 2.9. Agregar Menú en Sidebar

**Archivo:** `resources/js/src/components/layout/Sidebar.vue`

**Acción:** Agregar ítem de menú "Roles" bajo sección Admin.

**Patrón de referencia:** Menú de Users existente.

**Ubicación:** Buscar sección "Admin" o crear si no existe.

**Código a agregar:**

```vue
<!-- Roles (bajo Users) -->
<li class="menu nav-item" v-can="'roles.view'">
    <router-link to="/admin/roles" class="nav-link group">
        <div class="flex items-center">
            <icon-shield class="group-hover:!text-primary shrink-0" />
            <span class="ltr:pl-3 rtl:pr-3 text-black dark:text-[#506690] dark:group-hover:text-white-dark">
                {{ $t('sidebar.roles') }}
            </span>
        </div>
    </router-link>
</li>
```

**Notas:**
- Usar `v-can="'roles.view'"` para mostrar solo a usuarios autorizados.
- Traducción `$t('sidebar.roles')` ya existe en en.json, es.json, fr.json.
- Icono: `icon-shield` (representativo de permisos/seguridad).

---

### 2.10. Agregar Traducciones

**Archivos:**
- `resources/js/src/locales/en.json`
- `resources/js/src/locales/es.json`
- `resources/js/src/locales/fr.json`

**Acción:** Agregar keys de traducción para roles.

**Patrón:** Seguir estructura de `users.*` existentes.

**Contenido a agregar:**

**en.json:**
```json
{
  "roles.role_management": "Role Management",
  "roles.add_role": "Add Role",
  "roles.add_first_role": "Add First Role",
  "roles.create_role": "Create Role",
  "roles.create_new_role": "Create New Role",
  "roles.edit_role": "Edit Role",
  "roles.role_details": "Role Details",
  "roles.role_name": "Role Name",
  "roles.permissions": "Permissions",
  "roles.select_all": "Select All",
  "roles.search_by_name": "Search by role name...",
  "roles.no_results_found": "No Results Found",
  "roles.no_roles_match_search": "No roles match your current search criteria.",
  "roles.no_roles_yet": "No Roles Yet",
  "roles.get_started_by_adding_role": "Get started by creating your first custom role.",
  "roles.clear_filters": "Clear Filters",
  "roles.protected_role_warning": "This is a protected system role and cannot be modified.",
  "roles.cannot_modify_protected_permissions": "Protected roles cannot have their permissions modified.",
  "roles.no_permissions_assigned": "No permissions assigned to this role.",
  "roles.name_hint": "Use lowercase letters, numbers, and hyphens only (e.g., support-agent).",
  "roles.created_at": "Created At",
  "roles.updated_at": "Updated At"
}
```

**es.json:**
```json
{
  "roles.role_management": "Gestión de Roles",
  "roles.add_role": "Agregar Rol",
  "roles.add_first_role": "Agregar Primer Rol",
  "roles.create_role": "Crear Rol",
  "roles.create_new_role": "Crear Nuevo Rol",
  "roles.edit_role": "Editar Rol",
  "roles.role_details": "Detalles del Rol",
  "roles.role_name": "Nombre del Rol",
  "roles.permissions": "Permisos",
  "roles.select_all": "Seleccionar Todos",
  "roles.search_by_name": "Buscar por nombre de rol...",
  "roles.no_results_found": "No se Encontraron Resultados",
  "roles.no_roles_match_search": "No hay roles que coincidan con su búsqueda.",
  "roles.no_roles_yet": "No Hay Roles Aún",
  "roles.get_started_by_adding_role": "Comience creando su primer rol personalizado.",
  "roles.clear_filters": "Limpiar Filtros",
  "roles.protected_role_warning": "Este es un rol del sistema protegido y no puede modificarse.",
  "roles.cannot_modify_protected_permissions": "Los roles protegidos no pueden modificar sus permisos.",
  "roles.no_permissions_assigned": "No hay permisos asignados a este rol.",
  "roles.name_hint": "Use solo letras minúsculas, números y guiones (ej., agente-soporte).",
  "roles.created_at": "Creado el",
  "roles.updated_at": "Actualizado el"
}
```

**fr.json:**
```json
{
  "roles.role_management": "Gestion des Rôles",
  "roles.add_role": "Ajouter un Rôle",
  "roles.add_first_role": "Ajouter le Premier Rôle",
  "roles.create_role": "Créer un Rôle",
  "roles.create_new_role": "Créer un Nouveau Rôle",
  "roles.edit_role": "Modifier le Rôle",
  "roles.role_details": "Détails du Rôle",
  "roles.role_name": "Nom du Rôle",
  "roles.permissions": "Permissions",
  "roles.select_all": "Tout Sélectionner",
  "roles.search_by_name": "Rechercher par nom de rôle...",
  "roles.no_results_found": "Aucun Résultat Trouvé",
  "roles.no_roles_match_search": "Aucun rôle ne correspond à vos critères de recherche.",
  "roles.no_roles_yet": "Aucun Rôle Pour l'Instant",
  "roles.get_started_by_adding_role": "Commencez par créer votre premier rôle personnalisé.",
  "roles.clear_filters": "Effacer les Filtres",
  "roles.protected_role_warning": "Il s'agit d'un rôle système protégé qui ne peut être modifié.",
  "roles.cannot_modify_protected_permissions": "Les rôles protégés ne peuvent pas modifier leurs permissions.",
  "roles.no_permissions_assigned": "Aucune permission assignée à ce rôle.",
  "roles.name_hint": "Utilisez uniquement des lettres minuscules, des chiffres et des tirets (ex., agent-support).",
  "roles.created_at": "Créé le",
  "roles.updated_at": "Mis à jour le"
}
```

**Notas:**
- Agregar dentro del objeto JSON existente (no reemplazar).
- Mantener consistencia con naming de `users.*`.

---

## FASE 3: TESTING Y VALIDACIÓN

### 3.1. Actualizar Tests de Backend

**Archivo:** `tests/Feature/Api/RoleControllerTest.php`

**Acción:** Ajustar tests existentes para reflejar cambios de Policy.

**Cambios necesarios:**

1. **Tests de roles protegidos:**
   - Cambiar esperado de `422` a `403` en intentos de editar/eliminar admin/editor/user.

2. **Test de paginación:**
   - Verificar que `GET /api/roles` retorna estructura paginada.

3. **Tests de validación:**
   - Verificar que FormRequests lanzan errores 422 correctos.

**Ejemplo de test actualizado:**

```php
/** @test */
public function cannot_update_protected_role()
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    Sanctum::actingAs($user);

    $adminRole = Role::where('name', 'admin')->first();

    $response = $this->putJson("/api/roles/{$adminRole->id}", [
        'name' => 'super-admin',
    ]);

    // ANTES: $response->assertStatus(422);
    // DESPUÉS:
    $response->assertStatus(403); // Policy retorna forbidden
}
```

**NO crear nuevos tests** (decisión de no implementar unit tests de repos).

---

### 3.2. Manual Testing del Frontend

**Checklist de validación manual:**

**Lista de Roles:**
- [ ] Paginación funciona correctamente
- [ ] Búsqueda por nombre filtra correctamente
- [ ] Skeleton loader aparece en carga inicial
- [ ] Empty state aparece cuando no hay roles
- [ ] Mobile cards se ven bien en < 768px
- [ ] Botones Edit/Delete deshabilitados en admin/editor/user
- [ ] Badge "Protected" aparece en roles protegidos

**Crear Rol:**
- [ ] Validación frontend funciona (required, formato kebab-case)
- [ ] Checkboxes de permisos agrupados por módulo
- [ ] "Select All" por grupo funciona
- [ ] Checkbox indeterminate aparece correctamente
- [ ] Errores de backend se sincronizan (nombre duplicado)
- [ ] Redirección a /admin/roles después de crear

**Editar Rol:**
- [ ] Datos se precargan correctamente
- [ ] Permisos actuales están checkeados
- [ ] Formulario deshabilitado si rol es protegido
- [ ] Warning visual aparece en roles protegidos
- [ ] Actualización funciona correctamente en roles no protegidos

**Eliminar Rol:**
- [ ] Confirmación SweetAlert aparece
- [ ] Eliminación funciona en roles no protegidos
- [ ] Error 403 aparece al intentar eliminar rol protegido
- [ ] Rol desaparece de la lista después de eliminar

**Permisos:**
- [ ] Directiva `v-can="'roles.view'"` oculta menú a no autorizados
- [ ] Navigation guard redirige a 403 sin permiso
- [ ] Admin siempre puede acceder (Gate::before)

---

### 3.3. Validación de Arquitectura

**Checklist de conformidad:**

**Backend:**
- [ ] RoleController < 20 líneas por método
- [ ] FormRequests manejan validación
- [ ] Policy maneja autorización
- [ ] Repository abstrae queries
- [ ] Service layer NO necesario (lógica simple)
- [ ] OpenAPI annotations presentes

**Frontend:**
- [ ] Store sigue patrón de user.ts
- [ ] Service sigue patrón de userService.ts
- [ ] Vistas usan Composition API `<script setup>`
- [ ] Vuelidate para validación
- [ ] Error handling con backend sync
- [ ] Traducciones completas en 3 idiomas

**Seguridad:**
- [ ] Roles protegidos no editables/eliminables (backend + frontend)
- [ ] Middleware de autenticación en todas las rutas
- [ ] CSRF protection activo (Sanctum)
- [ ] Policy checks en todas las operaciones sensibles

---

## FASE 4: DOCUMENTACIÓN Y ENTREGA

### 4.1. Actualizar Documentación del Proyecto

**Archivos a actualizar:**

1. **spec/03_backend_implementation_phases.md**
   - Marcar Fase 1 como completada
   - Agregar notas de RolePolicy y FormRequests

2. **spec/04_frontend_implementation_phases.md**
   - Marcar gestión de roles como completada
   - Documentar store, service, vistas

3. **CLAUDE.md**
   - Agregar sección de gestión de roles
   - Documentar estructura de permisos
   - Agregar ejemplos de uso de directivas

**Ejemplo de sección a agregar en CLAUDE.md:**

```markdown
## Role & Permission Management

### Backend
- **Spatie Permission v6.24** provides RBAC
- **Protected Roles:** admin, editor, user (cannot be modified/deleted)
- **13 Permissions:** users.*, roles.*, profile.*, settings.*, activity-logs.view
- **Policy-based Authorization:** RolePolicy follows UserPolicy pattern
- **FormRequests:** StoreRoleRequest, UpdateRoleRequest

### Frontend
- **Store:** `stores/role.ts` (follows user.ts pattern)
- **Service:** `services/roleService.ts` (CRUD + pagination)
- **Views:** list.vue, create.vue, edit.vue, show.vue
- **Directives:** `v-can="'roles.view'"`, `v-role="'admin'"`
- **Composable:** `usePermissions()` for programmatic checks

### Usage Examples
```vue
<!-- Hide button if user can't create roles -->
<button v-can="'roles.create'">Add Role</button>

<!-- Check permission in script -->
<script setup>
import { usePermissions } from '@/composables/usePermissions';
const { can } = usePermissions();

if (can('roles.update')) {
  // Show edit form
}
</script>
```

### Protected Roles
The system prevents modification/deletion of:
- **admin:** Full system access
- **editor:** Content management
- **user:** Basic access

UI automatically disables edit/delete buttons for these roles.
```

---

### 4.2. Crear Changelog Entry

**Archivo:** Crear `CHANGELOG.md` (si no existe)

**Contenido:**

```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added - Role & Permission Management (2026-02-02)

#### Backend
- **RolePolicy** for centralized authorization (follows UserPolicy pattern)
- **FormRequests** for validation (StoreRoleRequest, UpdateRoleRequest)
- **Pagination** in RoleController.index() with search and sorting
- Protection of system roles (admin, editor, user) via Policy

#### Frontend
- **Complete role management UI:**
  - List view with server-side pagination, search, skeleton loaders, empty states
  - Create form with grouped permission checkboxes
  - Edit form with pre-loaded data and protected role warnings
  - Show view for read-only role details
- **Role Store** (`stores/role.ts`) following user.ts pattern
- **Role Service** (`services/roleService.ts`) with full CRUD + pagination
- **Router integration** with permission-based guards
- **Sidebar menu** item for roles (Admin section)
- **Translations** in English, Spanish, and French

#### Technical Improvements
- Policy-based authorization in RoleController (replaced inline checks)
- Consistent naming conventions (lowercase-kebab-case for role names)
- Protected role validation in both frontend and backend
- Responsive mobile cards for role list
- Accessibility (a11y) improvements: ARIA labels, keyboard navigation

### Changed
- RoleController now uses `$this->authorize()` instead of manual permission checks
- RoleRepository implements pagination interface
- types/role.ts simplified (removed unsupported display_name/description fields)

### Security
- Protected roles (admin, editor, user) cannot be modified or deleted
- All role management routes protected by Sanctum + permission middleware
- Frontend UI prevents unauthorized actions via v-can directive
```

---

## RESUMEN DE ARCHIVOS AFECTADOS

### Backend (8 archivos nuevos, 4 modificados)

**Nuevos:**
1. `app/Policies/RolePolicy.php`
2. `app/Http/Requests/Role/StoreRoleRequest.php`
3. `app/Http/Requests/Role/UpdateRoleRequest.php`

**Modificados:**
1. `app/Http/Controllers/Api/RoleController.php` (refactor completo)
2. `app/Repositories/Contracts/RoleRepositoryInterface.php` (agregar paginate)
3. `app/Repositories/Eloquent/RoleRepository.php` (implementar paginate)
4. `app/Providers/AuthServiceProvider.php` (registrar RolePolicy)
5. `tests/Feature/Api/RoleControllerTest.php` (actualizar assertions)

---

### Frontend (15 archivos nuevos, 4 modificados)

**Nuevos:**
1. `resources/js/src/stores/role.ts`
2. `resources/js/src/views/admin/roles/list.vue`
3. `resources/js/src/views/admin/roles/create.vue`
4. `resources/js/src/views/admin/roles/edit.vue`
5. `resources/js/src/views/admin/roles/show.vue`

**Modificados:**
1. `resources/js/src/services/roleService.ts` (agregar CRUD completo)
2. `resources/js/src/types/role.ts` (simplificar interfaces)
3. `resources/js/src/router/index.ts` (agregar rutas)
4. `resources/js/src/components/layout/Sidebar.vue` (agregar menú)
5. `resources/js/src/locales/en.json` (agregar traducciones)
6. `resources/js/src/locales/es.json` (agregar traducciones)
7. `resources/js/src/locales/fr.json` (agregar traducciones)

---

## DEPENDENCIAS Y ORDEN DE IMPLEMENTACIÓN

### Orden Recomendado (Secuencial)

**Día 1: Backend Foundation**
1. Crear RolePolicy
2. Registrar RolePolicy en AuthServiceProvider
3. Crear StoreRoleRequest
4. Crear UpdateRoleRequest
5. Agregar paginación a RoleRepository (interface + implementación)
6. Refactorizar RoleController (usar Policy + FormRequests + paginación)
7. Ejecutar tests y ajustar assertions

**Día 2: Frontend Core**
1. Ajustar types/role.ts
2. Completar roleService.ts (CRUD + paginación)
3. Crear stores/role.ts
4. Agregar traducciones (en.json, es.json, fr.json)

**Día 3: Frontend Views**
1. Crear list.vue
2. Crear create.vue
3. Crear edit.vue
4. Crear show.vue (opcional)

**Día 4: Integration**
1. Agregar rutas en router/index.ts
2. Agregar menú en Sidebar.vue
3. Testing manual completo
4. Ajustes de UX/UI

**Día 5: Polish & Documentation**
1. Validación de arquitectura
2. Actualizar documentación
3. Crear changelog
4. Review final

---

## RIESGOS Y MITIGACIONES

### Riesgo 1: Roles protegidos eliminados accidentalmente
**Mitigación:** Doble validación en Policy (backend) + UI disabled (frontend).

### Riesgo 2: Desincronización entre backend y frontend (campos)
**Mitigación:** Backend solo soporta `name` y `guard_name` (Spatie). No agregar campos custom.

### Riesgo 3: Admin pierde acceso por error en Gate::before
**Mitigación:** Gate::before ya está implementado y testeado. No tocar.

### Riesgo 4: Paginación backend/frontend incompatible
**Mitigación:** Seguir exactamente patrón de UserRepository + userService.ts.

### Riesgo 5: Traducciones incompletas causan [missing] en UI
**Mitigación:** Copiar estructura de `users.*` y validar en 3 idiomas (en, es, fr).

---

## CRITERIOS DE ACEPTACIÓN

**Backend:**
- [ ] RolePolicy creada y registrada
- [ ] FormRequests creadas y usadas en RoleController
- [ ] Paginación funcional en GET /api/roles
- [ ] Todos los tests pasando (actualizar assertions de 422 → 403)
- [ ] Roles protegidos NO editables/eliminables via API

**Frontend:**
- [ ] roleService.ts completo (5 métodos CRUD + paginación)
- [ ] role.ts store creado con patrón de user.ts
- [ ] 4 vistas creadas (list, create, edit, show)
- [ ] Rutas agregadas con guards de permisos
- [ ] Menú visible en sidebar con `v-can`
- [ ] Traducciones completas en 3 idiomas
- [ ] Roles protegidos visualmente bloqueados (badges, disabled buttons)

**Integración:**
- [ ] Usuario sin permiso `roles.view` NO ve menú
- [ ] Usuario con `roles.view` ve lista
- [ ] Usuario con `roles.create` puede crear roles
- [ ] Usuario con `roles.update` puede editar roles NO protegidos
- [ ] Usuario con `roles.delete` puede eliminar roles NO protegidos
- [ ] Admin pasa todos los checks (Gate::before)

**UX/UI:**
- [ ] Skeleton loaders en carga inicial
- [ ] Empty states informativos
- [ ] Mobile responsive (< 768px usa cards)
- [ ] Accessibility (ARIA labels, keyboard nav)
- [ ] Notificaciones de éxito/error consistentes

---

## NOTAS FINALES

### Decisiones Arquitecturales Clave

1. **No Service Layer para Roles:** La lógica es simple (CRUD directo), no requiere transacciones complejas ni activity logging. Repository + Controller + Policy son suficientes.

2. **No CRUD de Permisos:** Los permisos son estáticos (definidos por desarrollador en seeders), no se gestionan desde UI.

3. **No Bulk Delete de Roles:** Roles no se eliminan en masa típicamente (riesgo de perder permisos de usuarios).

4. **Protección Dual (Backend + Frontend):** Roles protegidos validados en Policy (backend) y visualmente bloqueados (frontend) para UX clara.

5. **Paginación desde Inicio:** Anticipar escalabilidad (100+ roles custom en empresas grandes).

### Próximos Pasos (Fuera de Scope)

- Auditoría de cambios de roles (activity log)
- Asignación masiva de roles a usuarios
- Export/Import de roles (JSON/CSV)
- Vista de usuarios por rol
- Permisos temporales (time-bound permissions)

---

**Fin del Plan de Implementación**

---

## APÉNDICE A: COMANDOS ÚTILES

```bash
# Backend
php artisan route:list | grep roles       # Ver rutas de roles
php artisan policy:make RolePolicy        # Crear policy (ya hecho)
php artisan test --filter RoleController  # Ejecutar tests de roles

# Frontend
npm run dev                               # Dev server
npm run build                             # Production build
npm run type-check                        # Verificar tipos TypeScript

# Database
php artisan db:seed --class=RolePermissionSeeder  # Seed roles/permisos
```

---

## APÉNDICE B: ESTRUCTURA DE PERMISOS

```
users.*
  - users.view
  - users.create
  - users.update
  - users.delete

roles.*
  - roles.view
  - roles.create
  - roles.update
  - roles.delete

profile.*
  - profile.view
  - profile.update

settings.*
  - settings.view
  - settings.update

activity-logs.*
  - activity-logs.view
```

**Total:** 13 permisos en 5 grupos.

---

## APÉNDICE C: MATRIZ DE PERMISOS POR ROL

| Permiso              | admin | editor | user |
|----------------------|-------|--------|------|
| users.view           | ✅    | ✅     | ❌   |
| users.create         | ✅    | ❌     | ❌   |
| users.update         | ✅    | ❌     | ❌   |
| users.delete         | ✅    | ❌     | ❌   |
| roles.view           | ✅    | ❌     | ❌   |
| roles.create         | ✅    | ❌     | ❌   |
| roles.update         | ✅    | ❌     | ❌   |
| roles.delete         | ✅    | ❌     | ❌   |
| profile.view         | ✅    | ✅     | ✅   |
| profile.update       | ✅    | ✅     | ✅   |
| settings.view        | ✅    | ❌     | ❌   |
| settings.update      | ✅    | ❌     | ❌   |
| activity-logs.view   | ✅    | ❌     | ❌   |

**Nota:** Admin tiene acceso a TODO via `Gate::before`.

---

**Plan validado contra:**
- ✅ Código fuente actual
- ✅ Patrones establecidos (UserController, UserService, UserPolicy)
- ✅ Arquitectura Clean (Repository, Policy, FormRequest)
- ✅ Frontend patterns (user.ts store, users/list.vue)
- ✅ Spatie Permission constraints (solo name/guard_name)

**Estado:** LISTO PARA IMPLEMENTACIÓN
