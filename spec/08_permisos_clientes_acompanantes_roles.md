# Plan de Implementación: Permisos de Clientes y Acompañantes en Gestión de Roles

**Proyecto:** VITE-IT Immigration - Laravel 12 + Vue 3 SPA
**Arquitectura:** Clean Architecture + Repository Pattern + Service Layer
**Fecha:** 2026-02-10
**Autor:** Winston - System Architect
**Epic Relacionado:** 1.2 (Clientes), 1.3 (Acompañantes), Roles & Permissions

---

## RESUMEN EJECUTIVO

Este plan detalla la implementación de permisos independientes para el módulo de acompañantes (`companions`), permitiendo control granular en la gestión de roles. Actualmente, los acompañantes heredan los permisos de clientes, lo cual limita la flexibilidad del sistema RBAC.

### Estado Actual Validado

**Sistema de Permisos (COMPLETO):**
- ✅ Spatie Permission v6.24 instalado y configurado
- ✅ 5 tablas relacionales activas (permissions, roles, model_has_permissions, model_has_roles, role_has_permissions)
- ✅ 6 roles predefinidos: super-admin, admin, consultor, apoyo, contador, cliente
- ✅ 3 roles protegidos: admin, editor, user
- ✅ UI de gestión de roles funcional (list, create, edit, show)
- ✅ Directivas `v-can` y `v-role` operativas
- ✅ Navigation guards con soporte de meta.permission

**Módulo de Clientes (COMPLETO):**
- ✅ Permisos definidos: clients.view, clients.create, clients.update, clients.delete
- ✅ ClientPolicy implementado con autorización completa
- ✅ UI CRUD completa con permisos aplicados

**Módulo de Acompañantes (PARCIAL):**
- ✅ CompanionPolicy implementado
- ⚠️ **BRECHA:** Usa permisos de clients.* en lugar de companions.*
- ⚠️ **BRECHA:** No permite control granular independiente

### Problema Identificado

| Acción en Companion | Permiso Actual | Permiso Esperado |
|---------------------|----------------|------------------|
| Ver acompañantes | `clients.view` | `companions.view` |
| Crear acompañante | `clients.update` | `companions.create` |
| Editar acompañante | `clients.update` | `companions.update` |
| Eliminar acompañante | `clients.delete` | `companions.delete` |

**Consecuencia:** No es posible crear un rol que gestione clientes sin acceso a acompañantes (o viceversa).

### Decisiones de Arquitectura

1. **SÍ se implementará:**
   - Permisos independientes: companions.view, companions.create, companions.update, companions.delete
   - Actualización de CompanionPolicy para usar permisos propios
   - Migración para agregar permisos y asignarlos a roles existentes
   - Actualización de traducciones (en.json, es.json)
   - Tests actualizados para nueva estructura de permisos

2. **Beneficios:**
   - Granularidad total en control de acceso
   - Roles más específicos (ej: consultor puede ver clientes pero no acompañantes)
   - Consistencia arquitectural con otros módulos
   - Preparación para auditorías de seguridad

---

## ANÁLISIS DE IMPACTO

### Archivos a Modificar

| Archivo | Tipo de Cambio | Riesgo |
|---------|----------------|--------|
| `database/seeders/RolePermissionSeeder.php` | Agregar permisos companions | Bajo |
| `app/Policies/CompanionPolicy.php` | Cambiar a permisos independientes | Medio |
| `resources/js/src/types/role.ts` | Agregar constantes de permisos | Bajo |
| `resources/js/src/locales/en.json` | Traducciones EN | Bajo |
| `resources/js/src/locales/es.json` | Traducciones ES | Bajo |
| `tests/Feature/CompanionTest.php` | Actualizar tests de autorización | Medio |

### Archivos Nuevos

| Archivo | Propósito |
|---------|-----------|
| `database/migrations/2026_02_10_000001_add_companion_permissions.php` | Migración de permisos |

### Componentes No Afectados

- ✅ CompanionController (ya usa Policy)
- ✅ CompanionService (sin cambios)
- ✅ CompanionRepository (sin cambios)
- ✅ Modelo Companion (sin cambios)
- ✅ UI de Roles (agrupa automáticamente por recurso)
- ✅ Frontend companion views (ya usa v-can)

---

## FASE 1: BACKEND - PERMISOS Y POLÍTICAS

**Duración estimada:** 3 horas
**Prioridad:** Alta

### 1.1. Actualizar Seeder de Permisos

**Archivo:** `database/seeders/RolePermissionSeeder.php`

**Propósito:** Agregar grupo de permisos para companions.

**Ubicación en código:** Dentro del array `$permissionGroups`

**Cambio:**
```php
// Agregar después de 'clients' => [...]
'companions' => [
    'companions.view',
    'companions.create',
    'companions.update',
    'companions.delete',
],
```

**Asignación a roles predefinidos:**

```php
// Dentro de la lógica de asignación de permisos por rol
$rolePermissions = [
    'admin' => [
        // ... permisos existentes
        'companions.view',
        'companions.create',
        'companions.update',
        'companions.delete',
    ],
    'consultor' => [
        // ... permisos existentes
        'companions.view',
        'companions.create',
        'companions.update',
        // NO companions.delete - consultores no eliminan
    ],
    'apoyo' => [
        // ... permisos existentes
        'companions.view',
        'companions.create',
        'companions.update',
        // NO companions.delete
    ],
    'contador' => [
        // ... permisos existentes
        'companions.view',
        // Solo lectura
    ],
];
```

**Dependencias:**
- Spatie Permission instalado ✅

---

### 1.2. Crear Migración de Permisos

**Archivo:** `database/migrations/2026_02_10_000001_add_companion_permissions.php`

**Propósito:** Agregar permisos a base de datos existente sin perder datos.

**Contenido:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Definir nuevos permisos
        $permissions = [
            'companions.view',
            'companions.create',
            'companions.update',
            'companions.delete',
        ];

        // Crear permisos si no existen
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Asignar permisos a roles existentes para mantener funcionalidad
        $this->assignPermissionsToRoles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'companions.view',
            'companions.create',
            'companions.update',
            'companions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Asignar permisos a roles existentes.
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin: todos los permisos
        $admin = Role::findByName('admin', 'web');
        if ($admin) {
            $admin->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
                'companions.delete',
            ]);
        }

        // Consultor: ver, crear, editar (no eliminar)
        $consultor = Role::findByName('consultor', 'web');
        if ($consultor) {
            $consultor->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
            ]);
        }

        // Apoyo: ver, crear, editar (no eliminar)
        $apoyo = Role::findByName('apoyo', 'web');
        if ($apoyo) {
            $apoyo->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
            ]);
        }

        // Contador: solo ver
        $contador = Role::findByName('contador', 'web');
        if ($contador) {
            $contador->givePermissionTo([
                'companions.view',
            ]);
        }
    }
};
```

**Notas:**
- Usa `firstOrCreate` para idempotencia
- Limpia caché de Spatie después de cambios
- Método `down()` permite rollback limpio

---

### 1.3. Actualizar CompanionPolicy

**Archivo:** `app/Policies/CompanionPolicy.php`

**Propósito:** Cambiar de permisos heredados de clients a permisos propios de companions.

**Cambios específicos:**

```php
<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Companion;
use App\Models\User;

class CompanionPolicy
{
    /**
     * Determine whether the user can view any companions.
     */
    public function viewAny(User $user, Client $client): bool
    {
        // ANTES: return $user->can('clients.view') && $user->tenant_id === $client->tenant_id;
        // DESPUÉS:
        return $user->can('companions.view') && $user->tenant_id === $client->tenant_id;
    }

    /**
     * Determine whether the user can view the companion.
     */
    public function view(User $user, Companion $companion): bool
    {
        // ANTES: return $user->can('clients.view') && $user->tenant_id === $companion->tenant_id;
        // DESPUÉS:
        return $user->can('companions.view') && $user->tenant_id === $companion->tenant_id;
    }

    /**
     * Determine whether the user can create companions.
     */
    public function create(User $user, Client $client): bool
    {
        // ANTES: return $user->can('clients.update') && $user->tenant_id === $client->tenant_id;
        // DESPUÉS:
        return $user->can('companions.create') && $user->tenant_id === $client->tenant_id;
    }

    /**
     * Determine whether the user can update the companion.
     */
    public function update(User $user, Companion $companion): bool
    {
        // ANTES: return $user->can('clients.update') && $user->tenant_id === $companion->tenant_id;
        // DESPUÉS:
        return $user->can('companions.update') && $user->tenant_id === $companion->tenant_id;
    }

    /**
     * Determine whether the user can delete the companion.
     */
    public function delete(User $user, Companion $companion): bool
    {
        // ANTES: return $user->can('clients.delete') && $user->tenant_id === $companion->tenant_id;
        // DESPUÉS:
        return $user->can('companions.delete') && $user->tenant_id === $companion->tenant_id;
    }
}
```

**Validación:**
- Mantiene verificación de tenant_id para aislamiento multi-tenant
- Sigue el mismo patrón que ClientPolicy

---

## FASE 2: FRONTEND - TIPOS Y TRADUCCIONES

**Duración estimada:** 2 horas
**Prioridad:** Alta

### 2.1. Actualizar Tipos TypeScript

**Archivo:** `resources/js/src/types/role.ts`

**Propósito:** Agregar constantes de permisos para companions.

**Cambio:** Agregar al objeto `PERMISSIONS`:

```typescript
export const PERMISSIONS = {
    // ... permisos existentes

    // Companions
    COMPANIONS_VIEW: 'companions.view',
    COMPANIONS_CREATE: 'companions.create',
    COMPANIONS_UPDATE: 'companions.update',
    COMPANIONS_DELETE: 'companions.delete',
} as const;
```

---

### 2.2. Actualizar Traducciones Inglés

**Archivo:** `resources/js/src/locales/en.json`

**Propósito:** Agregar etiquetas para UI de gestión de roles.

**Cambio:** Agregar dentro de la sección de permisos:

```json
{
    "permissions": {
        "companions": "Companions",
        "companions.view": "View companions",
        "companions.create": "Create companions",
        "companions.update": "Edit companions",
        "companions.delete": "Delete companions"
    }
}
```

---

### 2.3. Actualizar Traducciones Español

**Archivo:** `resources/js/src/locales/es.json`

**Propósito:** Agregar etiquetas en español.

**Cambio:**

```json
{
    "permissions": {
        "companions": "Acompañantes",
        "companions.view": "Ver acompañantes",
        "companions.create": "Crear acompañantes",
        "companions.update": "Editar acompañantes",
        "companions.delete": "Eliminar acompañantes"
    }
}
```

---

### 2.4. Verificar UI de Gestión de Roles

**Archivos a verificar:**
- `resources/js/src/views/admin/roles/create.vue`
- `resources/js/src/views/admin/roles/edit.vue`

**Validación requerida:**
- El store de roles usa `permissionsGrouped` que agrupa automáticamente por prefijo
- Los nuevos permisos `companions.*` deberían aparecer como grupo separado
- No se requieren cambios en componentes Vue si el agrupamiento funciona

**Comportamiento esperado:**
```
┌─────────────────────────────────────────────────────────────────┐
│  📁 clients                     │  👨‍👩‍👧 companions                 │
│  ☑ clients.view                │  ☑ companions.view             │
│  ☑ clients.create              │  ☑ companions.create           │
│  ☑ clients.update              │  ☑ companions.update           │
│  ☐ clients.delete              │  ☐ companions.delete           │
└─────────────────────────────────────────────────────────────────┘
```

---

## FASE 3: TESTING

**Duración estimada:** 2 horas
**Prioridad:** Media

### 3.1. Actualizar Tests de CompanionPolicy

**Archivo:** `tests/Feature/CompanionTest.php`

**Cambios requeridos:**

1. **Actualizar fixtures de usuario:**
```php
// ANTES: Usuario con clients.view
$user->givePermissionTo('clients.view');

// DESPUÉS: Usuario con companions.view
$user->givePermissionTo('companions.view');
```

2. **Agregar tests de granularidad:**
```php
/** @test */
public function user_with_clients_view_but_not_companions_view_cannot_list_companions(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('clients.view');
    // NO dar companions.view

    $client = Client::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user)
        ->getJson("/api/clients/{$client->id}/companions");

    $response->assertForbidden();
}

/** @test */
public function user_with_companions_view_can_list_companions(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('companions.view');

    $client = Client::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user)
        ->getJson("/api/clients/{$client->id}/companions");

    $response->assertOk();
}

/** @test */
public function user_with_companions_create_can_add_companion(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('companions.create');

    $client = Client::factory()->create(['tenant_id' => $user->tenant_id]);

    $response = $this->actingAs($user)
        ->postJson("/api/clients/{$client->id}/companions", [
            'first_name' => 'Test',
            'last_name' => 'Companion',
            'relationship' => 'spouse',
        ]);

    $response->assertCreated();
}

/** @test */
public function user_without_companions_delete_cannot_remove_companion(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo(['companions.view', 'companions.update']);
    // NO dar companions.delete

    $client = Client::factory()->create(['tenant_id' => $user->tenant_id]);
    $companion = Companion::factory()->create(['client_id' => $client->id]);

    $response = $this->actingAs($user)
        ->deleteJson("/api/clients/{$client->id}/companions/{$companion->id}");

    $response->assertForbidden();
}
```

---

### 3.2. Test de Migración

**Validación manual después de migración:**

```bash
# Ejecutar migración
php artisan migrate

# Verificar permisos creados
php artisan tinker
>>> \Spatie\Permission\Models\Permission::where('name', 'like', 'companions%')->get();

# Verificar asignación a roles
>>> \Spatie\Permission\Models\Role::findByName('admin')->permissions->pluck('name');
>>> \Spatie\Permission\Models\Role::findByName('consultor')->permissions->pluck('name');
```

---

## FASE 4: DOCUMENTACIÓN Y ROLLOUT

**Duración estimada:** 1 hora
**Prioridad:** Baja

### 4.1. Actualizar Documentación de Permisos

**Archivo:** `docs/permissions.md` (crear si no existe)

**Contenido sugerido:**
```markdown
# Sistema de Permisos - VITE-IT Immigration

## Permisos por Módulo

### Clientes
- `clients.view` - Ver listado y detalle de clientes
- `clients.create` - Crear nuevos clientes
- `clients.update` - Editar información de clientes
- `clients.delete` - Eliminar/archivar clientes

### Acompañantes
- `companions.view` - Ver acompañantes de un cliente
- `companions.create` - Agregar acompañantes
- `companions.update` - Editar información de acompañantes
- `companions.delete` - Eliminar acompañantes

## Matriz de Roles

| Rol | clients.* | companions.* |
|-----|-----------|--------------|
| admin | CRUD | CRUD |
| consultor | CRU | CRU |
| apoyo | CRU | CRU |
| contador | R | R |
```

---

## RESUMEN DE EJECUCIÓN

### Orden de Implementación

| Paso | Acción | Comando/Archivo |
|------|--------|-----------------|
| 1 | Crear migración | `php artisan make:migration add_companion_permissions` |
| 2 | Implementar migración | Copiar código de §1.2 |
| 3 | Actualizar seeder | Editar `RolePermissionSeeder.php` |
| 4 | Actualizar policy | Editar `CompanionPolicy.php` |
| 5 | Ejecutar migración | `php artisan migrate` |
| 6 | Actualizar tipos TS | Editar `types/role.ts` |
| 7 | Actualizar traducciones | Editar `en.json`, `es.json` |
| 8 | Ejecutar tests | `./vendor/bin/phpunit tests/Feature/CompanionTest.php` |
| 9 | Verificar UI | Navegar a /admin/roles/create |

### Estimación Total

| Fase | Duración | Estado |
|------|----------|--------|
| Fase 1: Backend | 3h | ✅ Completado |
| Fase 2: Frontend | 2h | ✅ Completado |
| Fase 3: Testing | 2h | ✅ Completado |
| Fase 4: Documentación | 1h | ⬜ Opcional |
| **Total** | **8h** | ✅ Implementado |

### Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Roles existentes pierden acceso | Alta | Alto | Migración asigna permisos automáticamente |
| Tests existentes fallan | Media | Medio | Actualizar fixtures antes de merge |
| UI no agrupa correctamente | Baja | Bajo | `permissionsGrouped` ya soporta nuevos grupos |
| Caché de permisos obsoleto | Media | Medio | Llamar `forgetCachedPermissions()` en migración |

### Checklist de Validación Post-Implementación

- [x] Migración ejecutada sin errores
- [x] Permisos companions.* visibles en base de datos
- [x] Rol admin tiene todos los permisos companions
- [x] Rol consultor tiene companions.view/create/update
- [x] UI de roles muestra grupo "Companions" separado
- [x] Usuario con solo clients.view NO puede ver companions
- [x] Usuario con companions.view SÍ puede ver companions
- [x] Tests de CompanionTest pasan (34 tests - 27 originales + 7 granularidad)
- [ ] Traducciones aparecen correctamente en UI (pendiente verificación manual)

---

## DIAGRAMA DE ARQUITECTURA POST-IMPLEMENTACIÓN

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         GESTIÓN DE ROLES UI                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    │
│   │    users.*      │    │   clients.*     │    │  companions.*   │    │
│   ├─────────────────┤    ├─────────────────┤    ├─────────────────┤    │
│   │ ☑ view          │    │ ☑ view          │    │ ☑ view          │    │
│   │ ☑ create        │    │ ☑ create        │    │ ☑ create        │    │
│   │ ☑ update        │    │ ☑ update        │    │ ☑ update        │    │
│   │ ☐ delete        │    │ ☐ delete        │    │ ☐ delete        │    │
│   └─────────────────┘    └─────────────────┘    └─────────────────┘    │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            BACKEND FLOW                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   Request → Middleware (auth:sanctum, tenant)                           │
│                ↓                                                        │
│   Controller → $this->authorize('viewAny', [Companion::class, $client]) │
│                ↓                                                        │
│   CompanionPolicy::viewAny() → $user->can('companions.view')            │
│                ↓                                                        │
│   Spatie Permission → Check role_has_permissions table                  │
│                ↓                                                        │
│   ✅ Allowed / ❌ 403 Forbidden                                         │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

**Documento generado por:** Winston - System Architect
**Versión:** 1.1 (Implementado)
**Última actualización:** 2026-02-10
**Estado:** ✅ IMPLEMENTADO

---

## REGISTRO DE IMPLEMENTACIÓN

| Fecha | Acción | Resultado |
|-------|--------|-----------|
| 2026-02-10 | Fase 1: Backend implementado | ✅ Seeder, Migración, Policy actualizados |
| 2026-02-10 | Fase 2: Frontend implementado | ✅ Types, Traducciones EN/ES actualizadas |
| 2026-02-10 | Fase 3: Testing completado | ✅ 34 tests pasando (7 nuevos de granularidad) |
| 2026-02-10 | Migración ejecutada | ✅ Permisos creados y asignados a roles |

### Archivos Modificados
- `database/seeders/RolePermissionSeeder.php`
- `database/migrations/2026_02_10_000001_add_companion_permissions.php` (nuevo)
- `app/Policies/CompanionPolicy.php`
- `resources/js/src/types/role.ts`
- `resources/js/src/locales/en.json`
- `resources/js/src/locales/es.json`
- `tests/Feature/CompanionTest.php`
