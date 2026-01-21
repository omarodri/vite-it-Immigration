# Especificaciones de Implementación: Vristo POC

## Tabla de Contenidos

1. [Database Schema Design](#1-database-schema-design)
2. [API Endpoints Specification](#2-api-endpoints-specification)
3. [Frontend Architecture](#3-frontend-architecture)
4. [Service Layer Contracts](#4-service-layer-contracts)
5. [Security Specifications](#5-security-specifications)
6. [Testing Specifications](#6-testing-specifications)

---

## 1. Database Schema Design

### 1.1 Esquema Completo con Roles y Permisos

```sql
-- ============================================
-- USERS DOMAIN
-- ============================================

-- Tabla principal de usuarios (ya existe)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,

    -- Campos adicionales para 2FA
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,

    -- Soft deletes
    deleted_at TIMESTAMP NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perfiles de usuario (información extendida)
CREATE TABLE user_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,

    -- Información personal
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,

    -- Preferencias
    avatar_url VARCHAR(255) NULL,
    bio TEXT NULL,
    date_of_birth DATE NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',

    -- Metadatos
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_country (country),
    INDEX idx_last_login (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ROLES & PERMISSIONS (Spatie Compatible)
-- ============================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'web',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_name_guard (name, guard_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'web',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY unique_name_guard (name, guard_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (permission_id, model_id, model_type),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_model (model_id, model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (role_id, model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    INDEX idx_model (model_id, model_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (permission_id, role_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT TRAIL
-- ============================================

CREATE TABLE activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_name VARCHAR(255) NULL,
    description TEXT NOT NULL,
    subject_type VARCHAR(255) NULL,
    subject_id BIGINT UNSIGNED NULL,
    causer_type VARCHAR(255) NULL,
    causer_id BIGINT UNSIGNED NULL,
    properties JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_subject (subject_type, subject_id),
    INDEX idx_causer (causer_type, causer_id),
    INDEX idx_log_name (log_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SESSIONS & SECURITY
-- ============================================

-- Login attempts (rate limiting manual)
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    successful BOOLEAN DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email_ip (email, ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions (ya existe, pero con campos adicionales)
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,

    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 Data Seeding

```php
// database/seeders/RolePermissionSeeder.php
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Roles
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Profiles
            'profiles.view',
            'profiles.update',

            // Settings
            'settings.view',
            'settings.update',

            // Activity Logs
            'activity-logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin', 'description' => 'Administrator with full access']);
        $adminRole->givePermissionTo(Permission::all());

        $editorRole = Role::create(['name' => 'editor', 'description' => 'Editor with limited access']);
        $editorRole->givePermissionTo([
            'users.view',
            'profiles.view',
            'profiles.update',
        ]);

        $userRole = Role::create(['name' => 'user', 'description' => 'Regular user']);
        $userRole->givePermissionTo([
            'profiles.view',
            'profiles.update',
        ]);

        // Assign admin role to first user
        $adminUser = User::first();
        if ($adminUser) {
            $adminUser->assignRole('admin');
        }
    }
}
```

---

## 2. API Endpoints Specification

### 2.1 Authentication Endpoints

#### POST /api/v1/register
**Descripción:** Registrar nuevo usuario

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

**Response 201 Created:**
```json
{
    "message": "Registration successful",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": null,
        "created_at": "2026-01-19T10:00:00.000000Z",
        "updated_at": "2026-01-19T10:00:00.000000Z"
    }
}
```

**Errores:**
- 422 Validation Error: Email ya existe, password débil, etc.
- 429 Too Many Requests: Rate limit excedido

---

#### POST /api/v1/login
**Descripción:** Iniciar sesión

**Request:**
```json
{
    "email": "john@example.com",
    "password": "SecurePass123!"
}
```

**Response 200 OK:**
```json
{
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2026-01-19T10:30:00.000000Z",
        "roles": ["user"],
        "permissions": ["profiles.view", "profiles.update"]
    },
    "two_factor_required": false
}
```

**Response 200 OK (2FA Required):**
```json
{
    "message": "Two factor authentication required",
    "two_factor_required": true
}
```

**Errores:**
- 422 Validation Error: Credenciales incorrectas
- 429 Too Many Requests: Múltiples intentos fallidos

---

#### POST /api/v1/two-factor-challenge
**Descripción:** Verificar código 2FA

**Request:**
```json
{
    "code": "123456"
}
```

**Response 200 OK:**
```json
{
    "message": "Login successful",
    "user": { ... }
}
```

---

#### POST /api/v1/logout
**Descripción:** Cerrar sesión
**Auth:** Requerido

**Response 200 OK:**
```json
{
    "message": "Logged out successfully"
}
```

---

### 2.2 User Management Endpoints

#### GET /api/v1/users
**Descripción:** Listar usuarios (paginado)
**Auth:** Requerido (permission: users.view)

**Query Params:**
- `page` (int): Número de página
- `per_page` (int): Elementos por página (default: 15, max: 100)
- `search` (string): Buscar por nombre o email
- `role` (string): Filtrar por rol
- `sort_by` (string): Campo para ordenar (default: created_at)
- `sort_order` (string): asc o desc (default: desc)

**Response 200 OK:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "email_verified_at": "2026-01-19T10:00:00.000000Z",
            "roles": ["admin"],
            "created_at": "2026-01-19T10:00:00.000000Z",
            "updated_at": "2026-01-19T10:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 73
    },
    "links": {
        "first": "http://example.com/api/v1/users?page=1",
        "last": "http://example.com/api/v1/users?page=5",
        "prev": null,
        "next": "http://example.com/api/v1/users?page=2"
    }
}
```

---

#### POST /api/v1/users
**Descripción:** Crear usuario
**Auth:** Requerido (permission: users.create)

**Request:**
```json
{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "roles": ["editor"],
    "send_welcome_email": true
}
```

**Response 201 Created:**
```json
{
    "message": "User created successfully",
    "user": { ... }
}
```

---

#### GET /api/v1/users/{id}
**Descripción:** Obtener usuario específico
**Auth:** Requerido (permission: users.view)

**Response 200 OK:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2026-01-19T10:00:00.000000Z",
    "roles": ["admin"],
    "permissions": ["*"],
    "profile": {
        "phone": "+1234567890",
        "address": "123 Main St",
        "city": "New York",
        "country": "USA",
        "avatar_url": "https://example.com/avatars/1.jpg",
        "bio": "Software developer",
        "timezone": "America/New_York",
        "language": "en"
    },
    "created_at": "2026-01-19T10:00:00.000000Z",
    "updated_at": "2026-01-19T10:00:00.000000Z"
}
```

---

#### PUT /api/v1/users/{id}
**Descripción:** Actualizar usuario
**Auth:** Requerido (permission: users.update)

**Request:**
```json
{
    "name": "John Updated",
    "email": "john.updated@example.com",
    "roles": ["admin", "editor"]
}
```

**Response 200 OK:**
```json
{
    "message": "User updated successfully",
    "user": { ... }
}
```

---

#### DELETE /api/v1/users/{id}
**Descripción:** Eliminar usuario (soft delete)
**Auth:** Requerido (permission: users.delete)

**Response 200 OK:**
```json
{
    "message": "User deleted successfully"
}
```

---

### 2.3 Profile Endpoints

#### GET /api/v1/profile
**Descripción:** Obtener perfil del usuario autenticado
**Auth:** Requerido

**Response 200 OK:**
```json
{
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "email_verified_at": "2026-01-19T10:00:00.000000Z"
    },
    "profile": {
        "phone": "+1234567890",
        "address": "123 Main St",
        "city": "New York",
        "country": "USA",
        "avatar_url": "https://example.com/avatars/1.jpg",
        "bio": "Software developer",
        "date_of_birth": "1990-01-01",
        "timezone": "America/New_York",
        "language": "en"
    }
}
```

---

#### PUT /api/v1/profile
**Descripción:** Actualizar perfil del usuario autenticado
**Auth:** Requerido

**Request:**
```json
{
    "name": "John Updated",
    "phone": "+1234567890",
    "address": "456 New St",
    "city": "Boston",
    "country": "USA",
    "bio": "Senior Software Developer",
    "date_of_birth": "1990-01-01",
    "timezone": "America/New_York",
    "language": "en"
}
```

**Response 200 OK:**
```json
{
    "message": "Profile updated successfully",
    "user": { ... },
    "profile": { ... }
}
```

---

#### POST /api/v1/profile/avatar
**Descripción:** Subir avatar del usuario
**Auth:** Requerido

**Request (multipart/form-data):**
```
avatar: [File] (jpg, png, max 2MB)
```

**Response 200 OK:**
```json
{
    "message": "Avatar uploaded successfully",
    "avatar_url": "https://example.com/avatars/1.jpg"
}
```

---

### 2.4 Roles & Permissions Endpoints

#### GET /api/v1/roles
**Descripción:** Listar todos los roles
**Auth:** Requerido (permission: roles.view)

**Response 200 OK:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "admin",
            "description": "Administrator with full access",
            "permissions": [
                { "id": 1, "name": "users.view" },
                { "id": 2, "name": "users.create" }
            ],
            "users_count": 5,
            "created_at": "2026-01-19T10:00:00.000000Z"
        }
    ]
}
```

---

#### GET /api/v1/permissions
**Descripción:** Listar todos los permisos
**Auth:** Requerido (permission: roles.view)

**Response 200 OK:**
```json
{
    "data": [
        {
            "id": 1,
            "name": "users.view",
            "description": "View users",
            "created_at": "2026-01-19T10:00:00.000000Z"
        }
    ]
}
```

---

### 2.5 Activity Log Endpoints

#### GET /api/v1/activity-logs
**Descripción:** Listar logs de actividad (paginado)
**Auth:** Requerido (permission: activity-logs.view)

**Query Params:**
- `page`, `per_page`
- `user_id` (int): Filtrar por usuario
- `log_name` (string): Filtrar por tipo de log
- `date_from` (date): Fecha inicio
- `date_to` (date): Fecha fin

**Response 200 OK:**
```json
{
    "data": [
        {
            "id": 1,
            "log_name": "user",
            "description": "User logged in",
            "subject_type": "App\\Models\\User",
            "subject_id": 1,
            "causer_type": "App\\Models\\User",
            "causer_id": 1,
            "properties": {
                "ip": "192.168.1.1",
                "user_agent": "Mozilla/5.0..."
            },
            "created_at": "2026-01-19T10:00:00.000000Z"
        }
    ],
    "meta": { ... }
}
```

---

## 3. Frontend Architecture

### 3.1 Service Layer Structure

```typescript
// resources/js/src/services/userService.ts
import api from './api';
import { User, CreateUserData, UpdateUserData, PaginationParams, PaginatedResponse } from '@/types';

export const userService = {
    /**
     * Obtener lista paginada de usuarios
     */
    async getUsers(params: PaginationParams): Promise<PaginatedResponse<User>> {
        const response = await api.get<PaginatedResponse<User>>('/users', { params });
        return response.data;
    },

    /**
     * Obtener usuario por ID
     */
    async getUser(id: number): Promise<User> {
        const response = await api.get<User>(`/users/${id}`);
        return response.data;
    },

    /**
     * Crear nuevo usuario
     */
    async createUser(data: CreateUserData): Promise<User> {
        const response = await api.post<{ user: User }>('/users', data);
        return response.data.user;
    },

    /**
     * Actualizar usuario existente
     */
    async updateUser(id: number, data: UpdateUserData): Promise<User> {
        const response = await api.put<{ user: User }>(`/users/${id}`, data);
        return response.data.user;
    },

    /**
     * Eliminar usuario
     */
    async deleteUser(id: number): Promise<void> {
        await api.delete(`/users/${id}`);
    },

    /**
     * Asignar roles a usuario
     */
    async assignRoles(userId: number, roleIds: number[]): Promise<void> {
        await api.post(`/users/${userId}/roles`, { roles: roleIds });
    },
};

export default userService;
```

### 3.2 Pinia Store Structure

```typescript
// resources/js/src/stores/user.ts
import { defineStore } from 'pinia';
import userService from '@/services/userService';
import { User, PaginationParams } from '@/types';

interface UserState {
    users: User[];
    currentUser: User | null;
    pagination: {
        currentPage: number;
        lastPage: number;
        total: number;
        perPage: number;
    };
    isLoading: boolean;
    error: string | null;
}

export const useUserStore = defineStore('user', {
    state: (): UserState => ({
        users: [],
        currentUser: null,
        pagination: {
            currentPage: 1,
            lastPage: 1,
            total: 0,
            perPage: 15,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getUserById: (state) => (id: number) => {
            return state.users.find(user => user.id === id);
        },
        totalUsers: (state) => state.pagination.total,
    },

    actions: {
        async fetchUsers(params: PaginationParams = {}) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await userService.getUsers(params);
                this.users = response.data;
                this.pagination = {
                    currentPage: response.meta.current_page,
                    lastPage: response.meta.last_page,
                    total: response.meta.total,
                    perPage: response.meta.per_page,
                };
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch users';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createUser(data: CreateUserData) {
            this.isLoading = true;
            this.error = null;
            try {
                const user = await userService.createUser(data);
                this.users.unshift(user);
                return user;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateUser(id: number, data: UpdateUserData) {
            this.isLoading = true;
            this.error = null;
            try {
                const user = await userService.updateUser(id, data);
                const index = this.users.findIndex(u => u.id === id);
                if (index !== -1) {
                    this.users[index] = user;
                }
                return user;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteUser(id: number) {
            this.isLoading = true;
            this.error = null;
            try {
                await userService.deleteUser(id);
                this.users = this.users.filter(u => u.id !== id);
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        clearError() {
            this.error = null;
        },
    },
});
```

### 3.3 TypeScript Types

```typescript
// resources/js/src/types/user.ts
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: string[];
    permissions: string[];
    profile?: UserProfile;
    created_at: string;
    updated_at: string;
}

export interface UserProfile {
    id: number;
    user_id: number;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    postal_code: string | null;
    avatar_url: string | null;
    bio: string | null;
    date_of_birth: string | null;
    timezone: string;
    language: string;
    last_login_at: string | null;
    last_login_ip: string | null;
    created_at: string;
    updated_at: string;
}

export interface CreateUserData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles?: string[];
    send_welcome_email?: boolean;
}

export interface UpdateUserData {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    roles?: string[];
}

export interface PaginationParams {
    page?: number;
    per_page?: number;
    search?: string;
    role?: string;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}

export interface Role {
    id: number;
    name: string;
    description: string | null;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Permission {
    id: number;
    name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
}
```

---

## 4. Service Layer Contracts (Backend)

### 4.1 Repository Pattern

```php
// app/Repositories/Contracts/UserRepositoryInterface.php
<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /**
     * Obtener usuario por ID
     */
    public function findById(int $id): ?User;

    /**
     * Obtener usuario por email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Crear nuevo usuario
     */
    public function create(array $data): User;

    /**
     * Actualizar usuario
     */
    public function update(int $id, array $data): bool;

    /**
     * Eliminar usuario (soft delete)
     */
    public function delete(int $id): bool;

    /**
     * Obtener usuarios paginados
     */
    public function paginate(
        int $perPage = 15,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): LengthAwarePaginator;

    /**
     * Buscar usuarios por término
     */
    public function search(string $term): Collection;

    /**
     * Obtener usuarios con roles
     */
    public function withRoles(int $id): User;

    /**
     * Verificar si email existe
     */
    public function emailExists(string $email): bool;
}
```

```php
// app/Repositories/Eloquent/UserRepository.php
<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return User::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $user = $this->findById($id);

        if (!$user) {
            return false;
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $user->update($data);
    }

    public function delete(int $id): bool
    {
        $user = $this->findById($id);

        if (!$user) {
            return false;
        }

        return $user->delete();
    }

    public function paginate(
        int $perPage = 15,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): LengthAwarePaginator {
        $query = User::query()->with('roles');

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function search(string $term): Collection
    {
        return User::where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->limit(10)
            ->get();
    }

    public function withRoles(int $id): User
    {
        return User::with('roles.permissions')->findOrFail($id);
    }

    public function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}
```

### 4.2 Service Layer

```php
// app/Services/User/UserService.php
<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Events\UserCreated;
use Spatie\Activitylog\Models\Activity;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Crear usuario con roles y enviar email de bienvenida
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Crear usuario
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Asignar roles
            if (!empty($data['roles'])) {
                $user->assignRole($data['roles']);
            } else {
                $user->assignRole('user'); // Rol por defecto
            }

            // Crear perfil vacío
            $user->profile()->create([
                'timezone' => $data['timezone'] ?? 'UTC',
                'language' => $data['language'] ?? 'en',
            ]);

            // Enviar email de bienvenida
            if ($data['send_welcome_email'] ?? false) {
                Mail::to($user->email)->send(new WelcomeEmail($user));
            }

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->log('User created');

            // Disparar evento
            event(new UserCreated($user));

            return $user->load('roles', 'profile');
        });
    }

    /**
     * Actualizar usuario y sus roles
     */
    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user = $this->userRepository->findById($id);

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Actualizar datos básicos
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['password'])) $updateData['password'] = $data['password'];

            $this->userRepository->update($id, $updateData);

            // Actualizar roles
            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            // Log activity
            activity()
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->withProperties(['changes' => $data])
                ->log('User updated');

            return $user->fresh(['roles', 'profile']);
        });
    }

    /**
     * Obtener usuarios con filtros y paginación
     */
    public function getUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $this->userRepository->paginate($perPage, $filters, $sortBy, $sortOrder);
    }

    /**
     * Eliminar usuario
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        // Prevenir eliminación de admin principal
        if ($user->hasRole('admin') && User::role('admin')->count() === 1) {
            throw new \Exception('Cannot delete the last admin user');
        }

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log('User deleted');

        return $this->userRepository->delete($id);
    }
}
```

---

## 5. Security Specifications

### 5.1 Rate Limiting Configuration

```php
// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting(): void
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('login', function (Request $request) {
        return [
            Limit::perMinute(5)->by($request->email.$request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ];
    });

    RateLimiter::for('password-reset', function (Request $request) {
        return Limit::perHour(3)->by($request->email.$request->ip());
    });
}
```

### 5.2 Middleware para Permisos

```php
// app/Http/Middleware/CheckPermission.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!$request->user()->can($permission)) {
            return response()->json([
                'message' => 'Unauthorized. Missing permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}
```

**Uso en rutas:**
```php
Route::middleware(['auth:sanctum', 'permission:users.create'])
    ->post('/users', [UserController::class, 'store']);
```

---

## 6. Testing Specifications

### 6.1 Unit Tests (Backend)

```php
// tests/Unit/Services/UserServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\User\UserService;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Models\User;
use Mockery;

class UserServiceTest extends TestCase
{
    protected $userRepository;
    protected $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->userService = new UserService($this->userRepository);
    }

    public function test_can_create_user_with_default_role()
    {
        // Arrange
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ];

        $user = new User($userData);
        $user->id = 1;

        $this->userRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset($userData))
            ->andReturn($user);

        // Act
        $result = $this->userService->createUser($userData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('test@example.com', $result->email);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

### 6.2 Feature Tests (Backend)

```php
// tests/Feature/Api/UserControllerTest.php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_admin_can_list_users()
    {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        User::factory()->count(5)->create();

        // Act
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles']
                ],
                'meta',
                'links'
            ]);
    }

    public function test_non_admin_cannot_list_users()
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole('user');

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/v1/users');

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_can_create_user()
    {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'roles' => ['user'],
        ];

        // Act
        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', $userData);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure(['message', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com'
        ]);
    }
}
```

### 6.3 Component Tests (Frontend)

```typescript
// tests/unit/components/UserList.spec.ts
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { setActivePinia, createPinia } from 'pinia';
import UserList from '@/views/admin/users/list.vue';
import { useUserStore } from '@/stores/user';

describe('UserList.vue', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('renders users table', async () => {
        const wrapper = mount(UserList);
        expect(wrapper.find('table').exists()).toBe(true);
    });

    it('calls fetchUsers on mount', async () => {
        const userStore = useUserStore();
        const fetchUsersSpy = vi.spyOn(userStore, 'fetchUsers');

        mount(UserList);

        expect(fetchUsersSpy).toHaveBeenCalled();
    });

    it('displays user data correctly', async () => {
        const userStore = useUserStore();
        userStore.users = [
            { id: 1, name: 'John Doe', email: 'john@example.com', roles: ['admin'] }
        ];

        const wrapper = mount(UserList);

        expect(wrapper.text()).toContain('John Doe');
        expect(wrapper.text()).toContain('john@example.com');
    });
});
```

---

**Fin del Documento de Especificaciones de Implementación**
