# Fases de Implementacion Frontend - Gestion de Roles

**Proyecto:** Vristo POC - Laravel 12 + Vue 3 SPA
**Fecha:** 2026-02-03
**Referencia:** `spec/06_role_permission_management.md` (Fase 2)

---

## Estado Actual del Codigo (verificado)

### Archivos existentes que seran MODIFICADOS
| Archivo | Estado actual |
|---------|--------------|
| `resources/js/src/types/role.ts` | Tiene campos `display_name`, `description` que NO existen en backend. `CreateRoleData.permissions` es `number[]` pero backend espera `string[]`. Tiene `AssignRoleData` que no se usa. |
| `resources/js/src/services/roleService.ts` | Solo tiene `getRoles()`, `getRole(id)`, `getPermissions()`. Falta: `getRolesPaginated`, `createRole`, `updateRole`, `deleteRole`. Importa `Role` de `@/types/user` (no de `@/types/role`). |
| `resources/js/src/router/index.ts` | Tiene rutas de `/admin/users/*` como patron. No tiene rutas de roles. |
| `resources/js/src/components/layout/Sidebar.vue` | Seccion Admin con solo Users. Usa `v-if="canViewUsers"` (computed de permiso). No tiene item de Roles. |
| `resources/js/src/locales/en.json` | Tiene `sidebar.roles` ya definido. NO tiene keys `roles.*` para las vistas. |
| `resources/js/src/locales/es.json` | Tiene `sidebar.roles` ya definido. NO tiene keys `roles.*` para las vistas. |

### Archivos existentes como REFERENCIA (no modificar)
| Archivo | Para que sirve como referencia |
|---------|-------------------------------|
| `resources/js/src/stores/user.ts` | Patron exacto para `stores/role.ts`: state, getters, actions, filtros, paginacion Laravel flat format |
| `resources/js/src/services/userService.ts` | Patron para completar `roleService.ts`: URLSearchParams, tipado de respuestas |
| `resources/js/src/views/admin/users/list.vue` | Patron para `roles/list.vue`: vue3-datatable server mode, @change handler, skeleton, empty states, mobile cards |
| `resources/js/src/views/admin/users/create.vue` | Patron para `roles/create.vue`: Vuelidate, form reactive, error handling backend |
| `resources/js/src/views/admin/users/edit.vue` | Patron para `roles/edit.vue`: precarga de datos, Vuelidate con requiredIf, loading states |
| `resources/js/src/views/admin/users/show.vue` | Patron para `roles/show.vue`: layout de detalle, skeleton, not-found state |
| `resources/js/src/composables/useNotification.ts` | Toast notifications, confirmDelete (acepta 1 arg `itemName: string`, retorna `Promise<boolean>`) |
| `resources/js/src/composables/useDebounce.ts` | Debounce para busqueda en list view |
| `resources/js/src/composables/usePermissions.ts` | `can()`, `hasRole()` para show.vue |
| `resources/js/src/types/pagination.ts` | `PaginationMeta`, `PaginationLinks`, `PaginatedResponse<T>` |

### Archivos que se CREARAN
| Archivo | Descripcion |
|---------|-------------|
| `resources/js/src/stores/role.ts` | Store Pinia para gestion de roles |
| `resources/js/src/views/admin/roles/list.vue` | Listado paginado con vue3-datatable |
| `resources/js/src/views/admin/roles/create.vue` | Formulario de creacion con permisos agrupados |
| `resources/js/src/views/admin/roles/edit.vue` | Formulario de edicion con proteccion de roles |
| `resources/js/src/views/admin/roles/show.vue` | Vista de detalle de un rol |

### Iconos disponibles (verificados en codebase)
- `icon-plus.vue` -- existe
- `icon-lock-dots.vue` -- existe (alternativa a shield que NO existe)
- `icon-lock.vue` -- existe
- `icon-search.vue`, `icon-eye.vue`, `icon-pencil.vue`, `icon-trash-lines.vue` -- existen
- `icon-arrow-left.vue`, `icon-x.vue`, `icon-save.vue` -- existen
- `icon-info-hexagon.vue`, `icon-info-triangle.vue` -- existen
- **`icon-shield.vue` -- NO EXISTE** (el spec lo referencia pero no hay componente)

### Lecciones aprendidas del proyecto (aplicar en todas las vistas)
1. vue3-datatable en `isServerMode` emite `@change` con payload unificado, NO `@sortChange/@pageChange/@pageSizeChange`
2. `@rowSelect` emite `User[]` directamente, no `{ selectedRows: User[] }`
3. `:checkedRows` NO existe como prop del datatable
4. Se necesita `:key` con `perPage` para forzar re-init del datatable al cambiar page size
5. `useNotification().confirmDelete` acepta un solo string (`itemName`) y retorna `Promise<boolean>`. En `users/list.vue` se usa `confirm()` custom en su lugar con el alias `confirmDeleteDialog` (el destructuring renombra `confirmDelete` a `confirmDeleteDialog` pero luego llama con 2 args que no coincide con la firma)

---

## Fase F1: Tipos y Service Layer [COMPLETADO]

### Objetivo
Corregir los tipos de TypeScript para que coincidan con lo que el backend realmente devuelve, y completar `roleService.ts` con todos los metodos CRUD y paginacion necesarios.

### Archivos
- **MODIFICAR:** `resources/js/src/types/role.ts`
- **MODIFICAR:** `resources/js/src/services/roleService.ts`

### Pasos

**Paso F1.1 - Ajustar `types/role.ts`:**
1. Eliminar `display_name` y `description` de la interface `Role` (backend Spatie solo tiene `name`, `guard_name`)
2. Eliminar `display_name` y `description` de la interface `Permission`
3. Cambiar `CreateRoleData.permissions` de `number[]` a `string[]` (backend espera nombres de permisos, no IDs)
4. Eliminar `display_name` y `description` de `CreateRoleData`
5. Cambiar `UpdateRoleData.permissions` de `number[]` a `string[]`
6. Eliminar `display_name` y `description` de `UpdateRoleData`
7. Eliminar `AssignRoleData` (no se usa; la asignacion de roles a usuarios se hace en UserController)
8. Ajustar `RoleListResponse` para que soporte estructura paginada de Laravel
9. Mantener intactos: `PermissionGroup`, `PERMISSIONS`, `ROLES`, `PermissionName`, `RoleName`

**Paso F1.2 - Completar `roleService.ts`:**
1. Cambiar el import de `Role` para que venga de `@/types/role` en vez de `@/types/user`
2. Eliminar la interface `Permission` local (usar la de `@/types/role`)
3. Agregar interface `RoleFilters` con campos: `search`, `sort_by`, `sort_direction`, `per_page`, `page`
4. Agregar interface `CreateRoleData` con campos: `name`, `permissions?: string[]`
5. Agregar interface `UpdateRoleData` con campos: `name?`, `permissions?: string[]`
6. Agregar interface `PaginatedRolesResponse` para la respuesta paginada de Laravel (flat format: `data`, `current_page`, `per_page`, `total`, `last_page`, `from`, `to`)
7. Agregar metodo `getRolesPaginated(filters?: RoleFilters): Promise<PaginatedRolesResponse>` -- seguir patron de `userService.getUsers()` con `URLSearchParams`
8. Agregar metodo `createRole(data: CreateRoleData): Promise<{ message: string; role: RoleWithPermissions }>` -- POST a `/roles`
9. Agregar metodo `updateRole(id: number, data: UpdateRoleData): Promise<{ message: string; role: RoleWithPermissions }>` -- PUT a `/roles/{id}`
10. Agregar metodo `deleteRole(id: number): Promise<{ message: string }>` -- DELETE a `/roles/{id}`
11. Mantener metodos existentes `getRoles()`, `getRole()`, `getPermissions()` sin cambios

### Validacion
- `npm run build` compila sin errores de TypeScript
- No hay imports rotos en archivos que ya importan de `@/types/role` o `@/services/roleService`
- Verificar que `stores/user.ts` sigue funcionando (importa `RoleWithPermissions` de roleService)

### Dependencias
- Ninguna. Esta es la fase base.

---

## Fase F2: Role Store (Pinia) [COMPLETADO]

### Objetivo
Crear el store de Pinia para roles siguiendo exactamente el patron de `stores/user.ts`, incluyendo state management, paginacion, filtros, y operaciones CRUD.

### Archivos
- **CREAR:** `resources/js/src/stores/role.ts`

### Pasos

**Paso F2.1 - Crear `stores/role.ts`:**
1. Definir interface `RoleState` con campos: `roles: Role[]`, `currentRole: Role | null`, `permissions: Permission[]`, `permissionsGrouped: Record<string, Permission[]>`, `meta: PaginationMeta | null`, `links: PaginationLinks | null`, `filters: RoleFilters`, `isLoading: boolean`, `error: string | null`
2. Crear store con `defineStore('role', { ... })` -- option store, mismo patron que user.ts
3. Estado inicial: filtros con `sort_by: 'name'`, `sort_direction: 'asc'`, `per_page: 15`, `page: 1`
4. Implementar getters:
   - `getRoleById(id)` -- buscar en state.roles
   - `totalRoles` -- de state.meta.total
   - `currentPage` -- de state.meta.current_page
   - `lastPage` -- de state.meta.last_page
   - `hasNextPage` / `hasPrevPage` -- de state.links
   - `permissionGroups` -- transformar `permissionsGrouped` a array de `PermissionGroup`
   - `isProtectedRole(roleName)` -- retorna true si es 'admin', 'editor', o 'user'
5. Implementar actions:
   - `fetchRoles(filters?)` -- llamar `roleService.getRolesPaginated()`, mapear respuesta paginada de Laravel (flat format) a `meta` y `links`, EXACTO mismo patron que `userStore.fetchUsers()`
   - `fetchPermissions()` -- llamar `roleService.getPermissions()`, guardar en `permissions` y `permissionsGrouped`
   - `fetchRole(id)` -- llamar `roleService.getRole(id)`, guardar en `currentRole`
   - `createRole(data)` -- llamar `roleService.createRole()`, luego `fetchRoles()` para refrescar
   - `updateRole(id, data)` -- llamar `roleService.updateRole()`, actualizar en lista local
   - `deleteRole(id)` -- llamar `roleService.deleteRole()`, remover de lista local, decrementar `meta.total`
   - `setSearch(search)`, `setSort(sortBy, direction)`, `setPage(page)`, `setPerPage(perPage)` -- mutators de filtros
   - `resetFilters()`, `clearCurrentRole()`, `clearError()` -- reset helpers

### Validacion
- `npm run build` compila sin errores
- El store se puede instanciar (verificar que no hay dependencias circulares)
- Verificar tipado: `useRoleStore()` devuelve tipos correctos en autocompletado

### Dependencias
- Fase F1 debe estar completa (tipos y service actualizados)

---

## Fase F3: Traducciones [COMPLETADO]

### Objetivo
Agregar todas las keys de traduccion necesarias para las vistas de roles en ingles y espanol.

### Archivos
- **MODIFICAR:** `resources/js/src/locales/en.json`
- **MODIFICAR:** `resources/js/src/locales/es.json`

### Pasos

**Paso F3.1 - Agregar keys en `en.json`:**
1. Agregar bloque de keys `roles.*` dentro del JSON existente (no reemplazar nada):
   - `roles.role_management` = "Role Management"
   - `roles.add_role` = "Add Role"
   - `roles.add_first_role` = "Add First Role"
   - `roles.create_role` = "Create Role"
   - `roles.create_new_role` = "Create New Role"
   - `roles.edit_role` = "Edit Role"
   - `roles.role_details` = "Role Details"
   - `roles.role_name` = "Role Name"
   - `roles.permissions` = "Permissions"
   - `roles.select_all` = "Select All"
   - `roles.search_by_name` = "Search by role name..."
   - `roles.no_results_found` = "No Results Found"
   - `roles.no_roles_match_search` = "No roles match your current search criteria."
   - `roles.no_roles_yet` = "No Roles Yet"
   - `roles.get_started_by_adding_role` = "Get started by creating your first custom role."
   - `roles.clear_filters` = "Clear Filters"
   - `roles.protected_role_warning` = "This is a protected system role and cannot be modified."
   - `roles.cannot_modify_protected_permissions` = "Protected roles cannot have their permissions modified."
   - `roles.no_permissions_assigned` = "No permissions assigned to this role."
   - `roles.name_hint` = "Use lowercase letters, numbers, and hyphens only (e.g., support-agent)."
   - `roles.created_at` = "Created At"
   - `roles.updated_at` = "Updated At"
   - `roles.10_per_page`, `roles.15_per_page`, `roles.25_per_page`, `roles.50_per_page` = "10 per page", etc.
   - `roles.page` = "Page"
   - `roles.of` = "of"
   - `roles.previous` = "Previous"
   - `roles.next` = "Next"
2. Verificar que `sidebar.roles` ya existe (confirmado que existe)

**Paso F3.2 - Agregar keys en `es.json`:**
1. Agregar las mismas keys traducidas al espanol:
   - `roles.role_management` = "Gestion de Roles"
   - `roles.add_role` = "Agregar Rol"
   - (mismas keys que en.json, con traducciones al espanol segun spec seccion 2.10)

### Validacion
- `npm run build` compila sin errores
- Abrir la app, cambiar idioma a EN y ES, verificar que `sidebar.roles` se traduce
- No hay keys duplicadas ni JSON malformado

### Dependencias
- Ninguna estricta. Puede hacerse en paralelo con F2. Pero debe estar lista ANTES de F4/F5/F6/F7.

---

## Fase F4: Vista List (roles/list.vue) [COMPLETADO]

### Objetivo
Crear la vista de listado de roles con vue3-datatable en server mode, busqueda debounced, skeleton loader, empty states, mobile cards, y proteccion visual de roles protegidos (admin/editor/user).

### Archivos
- **CREAR:** `resources/js/src/views/admin/roles/list.vue`

### Pasos

**Paso F4.1 - Crear estructura base del componente:**
1. Crear directorio `resources/js/src/views/admin/roles/` si no existe
2. Crear `list.vue` con `<template>` + `<script setup lang="ts">`
3. Imports: `Vue3Datatable`, `useRoleStore`, `useNotification`, `useDebounce`, `formatDate`, icons
4. State local: `searchQuery`, `perPage` (15), `currentPage` (1), `sortColumn` ('name'), `sortDirection` ('asc'), `initialLoading` (true)
5. Computeds: `hasActiveFilters`, `showSkeleton`, `showEmptyState`

**Paso F4.2 - Implementar template:**
1. Breadcrumb: Admin > Roles (seguir patron de users/list.vue)
2. Header con titulo `$t('roles.role_management')` y boton "Add Role" con `v-can="'roles.create'"`
3. Filtros: input de busqueda con debounce + select de per_page (sin filtro de role como en users)
4. Skeleton loader: tabla con 5 filas placeholder (4 columnas: Name, Permissions, Created, Actions)
5. Desktop table con vue3-datatable:
   - `:key="'dt-' + perPage"` para forzar re-init
   - `:rows`, `:columns`, `:totalRows`, `:isServerMode="true"`, `:loading`, `:sortable`, `:sortColumn`, `:sortDirection`, `:pageSize`, `:page`
   - `@change="handleTableChange"` -- handler unificado (NO @sortChange/@pageChange)
   - NO incluir `@rowSelect` ni `:hasCheckbox` (no hay bulk delete de roles)
   - Template slots: `#name` (nombre + badge "Protected"), `#permissions` (count), `#created_at` (formatDate), `#actions` (view/edit/delete con proteccion)
6. Mobile cards: layout responsive para pantallas < md (seguir patron de users/list.vue)
7. Mobile pagination: botones Previous/Next cuando hay mas paginas
8. Empty states: con filtros activos (search icon + clear filters) y sin datos (icon + add first role)

**Paso F4.3 - Implementar logica del script:**
1. Columnas: `name` (minWidth 200px), `permissions` (sort: false, 150px), `created_at` (150px), `actions` (sort: false, 150px)
2. `isProtectedRole(name)` -- delegar a `roleStore.isProtectedRole()`
3. `debouncedSearch()` -- reset page a 1, llamar fetchRoles()
4. `clearFilters()` -- limpiar searchQuery, reset page, fetchRoles()
5. `changePerPage()` -- reset page a 1, fetchRoles()
6. `handleTableChange(data: TableChangePayload)` -- extraer sort_column, sort_direction, current_page, pagesize; llamar fetchRoles()
7. `handlePageChange(page)` -- para mobile pagination
8. `fetchRoles()` -- llamar `roleStore.fetchRoles({ search, sort_by, sort_direction, per_page, page })`
9. `confirmDelete(role)` -- usar `useNotification().confirmDelete(role.name)`, luego `roleStore.deleteRole(role.id)`, success notification
10. `onMounted()` -- `await fetchRoles()`, luego `initialLoading = false`

**Paso F4.4 - Proteccion de roles en acciones:**
1. Columna actions: View siempre visible para todos los roles
2. Edit: mostrar `router-link` solo si NO es protected; mostrar boton disabled con opacity-50 si ES protected
3. Delete: mostrar boton funcional solo si NO es protected; mostrar boton disabled si ES protected
4. Usar `v-can="'roles.update'"` y `v-can="'roles.delete'"` en los wrappers
5. Tooltips con tippy: "Cannot edit protected role" / "Cannot delete protected role" cuando disabled

**Paso F4.5 - Icono para empty state y sidebar:**
- El spec usa `icon-shield` pero este componente NO EXISTE en el codebase
- Usar `icon-lock-dots` como alternativa (existe y es representativo de seguridad/permisos)
- O usar `icon-lock` como alternativa secundaria

### Validacion
- `npm run build` compila sin errores
- Navegar a `/admin/roles` (una vez agregada la ruta en F7) muestra la vista
- Skeleton aparece durante carga inicial
- vue3-datatable renderiza datos paginados del backend
- Busqueda filtra por nombre con debounce de 300ms
- Botones Edit/Delete estan deshabilitados visualmente para roles admin/editor/user
- Mobile cards se renderizan correctamente en viewport < 768px
- Empty state aparece cuando no hay resultados

### Dependencias
- Fase F1 (tipos y service)
- Fase F2 (role store)
- Fase F3 (traducciones)

---

## Fase F5: Vista Create (roles/create.vue) [COMPLETADO]

### Objetivo
Crear formulario de creacion de roles con validacion Vuelidate, permisos agrupados por modulo con checkboxes "Select All", y sincronizacion de errores con backend.

### Archivos
- **CREAR:** `resources/js/src/views/admin/roles/create.vue`

### Pasos

**Paso F5.1 - Crear estructura base:**
1. Crear `create.vue` en `resources/js/src/views/admin/roles/`
2. Imports: `useRouter`, `useVuelidate`, `required`, `helpers` de Vuelidate, `useRoleStore`, `useNotification`, icons
3. State: `form` reactive con `name: ''` y `permissions: [] as string[]`, `isSubmitting`, `errorMessage`

**Paso F5.2 - Implementar template:**
1. Breadcrumb: Roles > Create Role
2. Header con titulo y boton "Back to List"
3. Error alert dismissible (mismo patron que users/create.vue)
4. Campo "Role Name" con:
   - Input text con `v-model="form.name"`
   - Placeholder: "e.g., manager, support-agent"
   - Vuelidate error display
   - Hint text: "Use lowercase letters, numbers, and hyphens only"
5. Seccion de permisos agrupados:
   - Iterar sobre `roleStore.permissionGroups`
   - Cada grupo en un card con borde (`border border-gray-200 rounded-lg p-4`)
   - Header del grupo: nombre capitalizado + checkbox "Select All"
   - Checkbox "Select All" con estado indeterminate cuando seleccion es parcial
   - Grid de checkboxes individuales (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`)
   - Cada checkbox con `v-model="form.permissions"` y `:value="permission.name"`
   - Label con `formatPermissionName()`: "users.view" se muestra como "View"
6. Botones: Cancel (link a /admin/roles) + Create (submit con loading spinner)

**Paso F5.3 - Implementar logica:**
1. Validacion Vuelidate:
   - `name`: required + custom validator regex `/^[a-z0-9-]+$/` (kebab-case)
   - NO validar permissions como required (un rol puede crearse sin permisos)
2. `formatPermissionName(name)`: split por '.', tomar la ultima parte, capitalizar
3. `isGroupFullySelected(groupName)`: verificar si todos los permisos del grupo estan en `form.permissions`
4. `isGroupPartiallySelected(groupName)`: verificar si algunos pero no todos estan seleccionados
5. `toggleGroup(groupName, event)`: si checked, agregar todos los del grupo; si unchecked, remover todos los del grupo
6. `handleSubmit()`:
   - Validar con v$.value.$validate()
   - Llamar `roleStore.createRole(form)`
   - Success: notification + redirect a `/admin/roles`
   - Error: extraer `err.response.data.errors.name[0]` o message generico
7. `onMounted()`: llamar `roleStore.fetchPermissions()` para cargar los grupos

### Validacion
- `npm run build` compila sin errores
- Formulario muestra errores de validacion frontend (nombre vacio, formato incorrecto)
- Permisos se agrupan correctamente por modulo (users, roles, profile, settings, activity-logs)
- "Select All" selecciona/deselecciona todos los permisos del grupo
- Estado indeterminate aparece cuando seleccion es parcial
- Errores del backend (nombre duplicado) se muestran en el alert
- Tras crear exitosamente, redirige a `/admin/roles`

### Dependencias
- Fase F1 (tipos y service)
- Fase F2 (role store -- fetchPermissions, createRole)
- Fase F3 (traducciones)

---

## Fase F6: Vista Edit (roles/edit.vue) [COMPLETADO]

### Objetivo
Crear formulario de edicion de roles con precarga de datos, proteccion de roles protegidos (formulario deshabilitado), y warning visual.

### Archivos
- **CREAR:** `resources/js/src/views/admin/roles/edit.vue`

### Pasos

**Paso F6.1 - Crear estructura base:**
1. Crear `edit.vue` en `resources/js/src/views/admin/roles/`
2. Imports: `useRouter`, `useRoute`, `useVuelidate`, `useRoleStore`, `useNotification`, icons
3. State: `form` reactive, `isLoading` (true), `isSubmitting`, `errorMessage`, `isProtected` (boolean)
4. Computed: `roleId` de `route.params.id`

**Paso F6.2 - Implementar template:**
1. Breadcrumb: Roles > Edit Role
2. Loading state: spinner centrado mientras carga (mismo patron que users/edit.vue)
3. Not found state: si el rol no existe despues de cargar
4. Header: titulo "Edit Role" + warning text si es protected + boton "Back to List"
5. Warning banner si `isProtected`: fondo amarillo con mensaje `$t('roles.protected_role_warning')`
6. Campo "Role Name":
   - Input con `:disabled="isProtected"` para roles protegidos
   - Misma validacion que create.vue
7. Seccion de permisos:
   - Warning box antes de los checkboxes si protected: "Protected roles cannot have their permissions modified."
   - Mismos checkboxes agrupados que create.vue pero con `:disabled="isProtected"` en cada checkbox
   - `toggleGroup()` debe verificar `if (isProtected.value) return;`
8. Botones: Cancel + Save Changes (ocultar Save si `isProtected`)

**Paso F6.3 - Implementar logica:**
1. Validacion Vuelidate: mismas reglas que create.vue (name required, formato kebab-case)
2. `onMounted()`:
   - `await Promise.all([roleStore.fetchRole(roleId), roleStore.fetchPermissions()])`
   - Si `roleStore.currentRole` existe: poblar form con `name` y `permissions` (mapear `p.name`)
   - Setear `isProtected = roleStore.isProtectedRole(currentRole.name)`
   - Si no existe: notification de error + redirect a `/admin/roles`
   - `isLoading = false` en finally
3. `handleSubmit()`:
   - Si `isProtected`, mostrar error y return
   - Validar con Vuelidate
   - Llamar `roleStore.updateRole(roleId, form)`
   - Success: notification + redirect
   - Error: misma logica de extraccion de errores que create.vue
4. Reusar helpers: `formatPermissionName`, `isGroupFullySelected`, `isGroupPartiallySelected`, `toggleGroup`

### Validacion
- `npm run build` compila sin errores
- Navegacion a `/admin/roles/{id}/edit` precarga los datos del rol
- Permisos actuales del rol aparecen checkeados
- Para roles protegidos (admin/editor/user): todos los inputs deshabilitados, boton Save oculto, warning visible
- Para roles no protegidos: edicion funciona, guardado redirige a lista
- Error 403 del backend al intentar editar rol protegido (si se bypasea UI) se muestra como notification

### Dependencias
- Fase F1, F2, F3
- Fase F5 (reusar logica de checkboxes agrupados; se puede extraer a composable si hay suficiente duplicacion, pero por ahora copiar el patron)

---

## Fase F7: Vista Show (roles/show.vue) [COMPLETADO]

### Objetivo
Crear vista de solo lectura para un rol, mostrando informacion basica y permisos agrupados visualmente. Sin formularios.

### Archivos
- **CREAR:** `resources/js/src/views/admin/roles/show.vue`

### Pasos

**Paso F7.1 - Crear estructura base:**
1. Crear `show.vue` en `resources/js/src/views/admin/roles/`
2. Imports: `useRoute`, `useRouter`, `useRoleStore`, `usePermissions`, `useNotification`, `formatDate`, icons
3. State: `isLoading` (true)
4. Computeds: `roleId`, `currentRole` (de store), `isProtectedRole`, `groupedPermissions`

**Paso F7.2 - Implementar template:**
1. Breadcrumb: Roles > {role.name}
2. Loading state: spinner centrado
3. Not found state: si el rol no existe (misma UI que users/show.vue not-found)
4. Panel de informacion del rol:
   - Header con titulo "Role Details" + botones "Back to List" y "Edit" (Edit solo si no protected y `can('roles.update')`)
   - Campo: Role Name con badge "Protected" si aplica
   - Campo: Created At con `formatDate()`
   - Campo: Updated At con `formatDate()`
5. Panel de permisos:
   - Titulo: "Permissions (N)"
   - Permisos agrupados por modulo (computed `groupedPermissions`): agrupar por la parte antes del '.' en `permission.name`
   - Cada grupo en un card con borde
   - Cada permiso como badge `badge-outline-primary`
   - Usar `formatPermissionName()` para mostrar nombre legible
   - Empty state si no hay permisos: mensaje centrado "No permissions assigned to this role."

**Paso F7.3 - Implementar logica:**
1. `groupedPermissions` computed: iterar `currentRole.permissions`, agrupar por `permission.name.split('.')[0]`, generar array de `{ name, display_name, permissions }`
2. `formatPermissionName(name)`: split por '.', tomar ultima parte, capitalizar
3. `onMounted()`: llamar `roleStore.fetchRole(roleId)`, si no existe redirect a `/admin/roles` con error notification
4. Setear `isLoading = false` en finally

### Validacion
- `npm run build` compila sin errores
- Navegacion a `/admin/roles/{id}` muestra los detalles del rol
- Permisos se agrupan visualmente por modulo
- Badge "Protected" aparece para admin/editor/user
- Boton Edit no aparece para roles protegidos
- Loading spinner durante carga, not-found state si ID invalido

### Dependencias
- Fase F1, F2, F3

---

## Fase F8: Rutas, Sidebar y Wiring Final [COMPLETADO]

### Objetivo
Conectar todas las vistas con el router, agregar el item de menu en el Sidebar, y verificar que todo funciona end-to-end.

### Archivos
- **MODIFICAR:** `resources/js/src/router/index.ts`
- **MODIFICAR:** `resources/js/src/components/layout/Sidebar.vue`

### Pasos

**Paso F8.1 - Agregar rutas en router:**
1. Ubicar las rutas de `/admin/users/*` (lineas ~488-510 aprox)
2. Agregar inmediatamente despues, 4 rutas nuevas:
   - `{ path: '/admin/roles', name: 'admin-roles', component: () => import('../views/admin/roles/list.vue'), meta: { permission: 'roles.view' } }`
   - `{ path: '/admin/roles/create', name: 'admin-roles-create', component: () => import('../views/admin/roles/create.vue'), meta: { permission: 'roles.create' } }`
   - `{ path: '/admin/roles/:id', name: 'admin-roles-show', component: () => import('../views/admin/roles/show.vue'), meta: { permission: 'roles.view' } }`
   - `{ path: '/admin/roles/:id/edit', name: 'admin-roles-edit', component: () => import('../views/admin/roles/edit.vue'), meta: { permission: 'roles.update' } }`
3. IMPORTANTE: La ruta `create` debe ir ANTES de `:id` para evitar que "create" se parsee como un ID
4. Seguir el naming convention existente: `admin-roles`, `admin-roles-create`, etc. (con guion, NO punto como sugiere el spec)

**Paso F8.2 - Agregar menu en Sidebar:**
1. Ubicar la seccion Admin en Sidebar.vue (lineas ~31-51)
2. Cambiar la condicion `v-if="canViewUsers"` a algo mas amplio: `v-if="canViewUsers || canViewRoles"` o usar `v-if="canViewAdmin"` donde `canViewAdmin = computed(() => authStore.hasAnyPermission(['users.view', 'roles.view']))`
3. Agregar computed `canViewRoles = computed(() => authStore.hasPermission('roles.view'))`
4. Dentro del `<ul>` existente bajo Admin, despues del `<li>` de Users, agregar nuevo `<li>` para Roles:
   - `v-if="canViewRoles"` para mostrar solo a usuarios con permiso
   - `router-link` a `/admin/roles`
   - Icono: usar `icon-lock-dots` (ya que `icon-shield` no existe)
   - Texto: `$t('sidebar.roles')`
   - Seguir exactamente el markup del item de Users
5. Importar el componente de icono: `import IconLockDots from '@/components/icon/icon-lock-dots.vue'`
6. Verificar que el icono ya no este importado para evitar duplicados

**Paso F8.3 - Verificar navigation guards:**
- Las rutas usan `meta: { permission: 'roles.view' }` etc.
- El navigation guard existente en el router ya lee `meta.permission` y verifica contra el auth store
- No se necesita codigo adicional para los guards

### Validacion
- `npm run build` compila sin errores
- Menu lateral muestra "Roles" bajo "Admin" para usuarios con permiso `roles.view`
- Menu NO muestra "Roles" para usuarios sin el permiso
- Clic en "Roles" navega a `/admin/roles` y muestra el listado
- Navegacion directa a `/admin/roles/create` funciona (con permiso)
- Navegacion directa a `/admin/roles/1` muestra detalle
- Navegacion directa a `/admin/roles/1/edit` muestra edicion
- Intentar navegar sin permiso redirige a pagina 403 (o muestra error)

### Dependencias
- Fase F4 (list.vue debe existir para que la ruta funcione)
- Fase F5 (create.vue)
- Fase F6 (edit.vue)
- Fase F7 (show.vue)

---

## Fase F9: Verificacion End-to-End y Ajustes [COMPLETADO]

### Objetivo
Validar el flujo completo de gestion de roles, corregir bugs, y verificar que todo sigue los patrones del proyecto.

### Archivos
- Potencialmente cualquier archivo creado/modificado en fases anteriores

### Pasos

**Paso F9.1 - Build verification:**
1. Ejecutar `npm run build` y verificar 0 errores
2. Verificar que no hay warnings de TypeScript relevantes

**Paso F9.2 - Checklist de flujos funcionales:**
1. **Listado:** Navegar a `/admin/roles`, verificar paginacion, busqueda, skeleton, empty state
2. **Crear:** Navegar a `/admin/roles/create`, llenar formulario, verificar validacion frontend, crear rol, verificar redirect
3. **Detalle:** Navegar a `/admin/roles/{id}`, verificar datos y permisos agrupados
4. **Editar:** Navegar a `/admin/roles/{id}/edit`, verificar precarga, editar, guardar
5. **Eliminar:** Desde lista, eliminar un rol no protegido, verificar confirmacion y remocion
6. **Roles protegidos:** Verificar que admin/editor/user tienen badge "Protected", botones disabled, formulario edit deshabilitado
7. **Permisos:** Verificar que usuarios sin `roles.view` no ven el menu ni acceden a las rutas
8. **Mobile:** Verificar responsive en viewport < 768px

**Paso F9.3 - Verificar consistencia de patrones:**
1. Todas las vistas usan `<script setup lang="ts">`
2. Todas las vistas usan `useMeta()` para titulo de pagina
3. Todas las vistas usan traducciones via `$t()`
4. Store sigue exactamente el patron de user.ts
5. Service sigue exactamente el patron de userService.ts
6. Error handling es consistente entre create/edit (backend error sync)
7. Datatable usa `@change` y NO eventos individuales
8. Datatable tiene `:key` con perPage

**Paso F9.4 - Ajustes post-verificacion:**
- Corregir cualquier discrepancia encontrada
- Ajustar estilos si hay inconsistencias visuales
- Verificar que dark mode funciona en todas las vistas nuevas

### Validacion
- Todos los flujos del Paso F9.2 pasan sin errores
- `npm run build` pasa limpio
- No hay regresiones en funcionalidad existente (gestion de usuarios sigue funcionando)

### Dependencias
- TODAS las fases anteriores (F1-F8)

---

## Resumen de Dependencias

```
F1 (tipos + service) -----> F2 (store) -----> F4 (list.vue) ----+
       |                        |                                |
       |                        +----------> F5 (create.vue) ----+
       |                        |                                |
       |                        +----------> F6 (edit.vue) ------+----> F8 (rutas + sidebar) ----> F9 (verificacion)
       |                        |                                |
       |                        +----------> F7 (show.vue) ------+
       |
       +----> F3 (traducciones) ----> F4, F5, F6, F7
```

**Orden de ejecucion recomendado:**
1. F1 (base obligatoria)
2. F2 + F3 (pueden hacerse en paralelo)
3. F4, F5, F6, F7 (pueden hacerse en cualquier orden, pero list.vue primero es lo mas practico para validar el store)
4. F8 (requiere todas las vistas)
5. F9 (verificacion final)

---

## Notas Importantes

### Discrepancias detectadas entre spec y codebase real
1. **`icon-shield.vue` no existe.** El spec lo referencia en list.vue (empty state) y sidebar. Usar `icon-lock-dots` como alternativa.
2. **`roleService.ts` importa `Role` de `@/types/user`, no de `@/types/role`.** Hay que corregir este import en F1.
3. **`useNotification().confirmDelete` tiene firma `(itemName: string): Promise<boolean>`**, pero en `users/list.vue` se usa `confirm()` con 2 argumentos via alias. Para roles, usar `confirmDelete(role.name)` que retorna `Promise<boolean>` directamente (no `SweetAlertResult`), lo cual es mas limpio.
4. **`types/role.ts` tiene `display_name` y `description`** que el backend Spatie no soporta. Hay que eliminarlos en F1.
5. **`CreateRoleData.permissions` es `number[]`** pero el backend espera `string[]` (nombres de permisos). Hay que corregir en F1.
6. **Las rutas de users usan nombre con guion (`admin-users`)**, no con punto (`admin.users.index`). Mantener consistencia con guion para roles.
7. **La seccion Admin del Sidebar usa `v-if="canViewUsers"`** que excluye a usuarios que solo tienen `roles.view`. Hay que ampliar la condicion en F8.
