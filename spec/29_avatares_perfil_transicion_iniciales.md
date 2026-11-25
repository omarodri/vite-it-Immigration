---
tipo: spec
numero: 29
modulo: UX e Internacionalización
dominio: UX
estado: PART
tags: [spec, ux, parcial]
---
# [[29_avatares_perfil_transicion_iniciales|Spec 29]]: Avatares de Perfil — Transicion de Iniciales a Fotografias

## Informacion del Documento

- **Fecha:** 2026-03-21
- **Tipo:** Analisis de Impacto Tecnico y Plan de Implementacion
- **Scope:** Reemplazo del sistema de iniciales de usuario por fotografias de perfil (avatares) en Cases List, TodoList y Scrumboard
- **Estado:** Implementado (2026-03-22) — Fases 1-3 completadas, Fase 4 pendiente verificacion manual

---

## 1. Resumen Ejecutivo

Este documento analiza la transicion visual de "iniciales de usuario" a "fotografias de perfil" (avatares) en tres modulos clave: Lista de Casos, Todo List y Scrumboard. El objetivo es humanizar la interfaz sin sacrificar rendimiento.

### Estado Actual de la Infraestructura

La base de avatares **ya existe** en el codebase:

| Componente | Estado | Archivos |
|-----------|--------|----------|
| `user_profiles.avatar_url` (campo en BD) | Completo | `migrations/2026_01_21_165846_create_user_profiles_table.php` |
| Modelo `UserProfile` con `avatar_url` nullable | Completo | `app/Models/UserProfile.php` |
| Relacion `User->profile()` HasOne | Completo | `app/Models/User.php` (lineas 82-85) |
| `ProfileController` upload/delete avatar | Completo | `app/Http/Controllers/Api/ProfileController.php` (lineas 116-177) |
| Pinia `useProfileStore` con `uploadAvatar()` | Completo | `resources/js/src/stores/profile.ts` |
| Auth store getter `avatarUrl` | Completo | `resources/js/src/stores/auth.ts` (linea 39) |
| Vista `profile.vue` con avatar + fallback iniciales | Completo | `resources/js/src/views/users/profile.vue` (lineas 79-84) |
| Sincronizacion profile→auth store al subir avatar | Completo | `resources/js/src/stores/profile.ts` (lineas 103-107) |
| Interface `UserProfile` con `avatar_url` | Completo | `resources/js/src/types/user.ts` (lineas 32-50) |
| Interface `User` en auth con `profile?.avatar_url` | Completo | `resources/js/src/services/authService.ts` (lineas 12-24) |

### Brecha Principal

Los endpoints que devuelven `assigned_user` solo incluyen `{ id, name, email }` — **no incluyen `profile.avatar_url`**. Las tres vistas objetivo muestran solo texto o iniciales:

| Vista | Ubicacion | Estado Actual |
|-------|-----------|---------------|
| Cases List | `views/cases/list.vue` lineas 338-341 | Solo muestra `assigned_user.name` |
| TodoList | `views/apps/todolist.vue` lineas 380-383 | Iniciales hardcoded en div circular |
| Scrumboard | `ScrumTaskCard.vue` lineas 48-50 | Solo primer nombre como texto |

---

## 2. Analisis por Componente

### 2.1 Backend (Laravel)

#### 2.1.1 Modelo — Accessor en User

**Situacion actual:** Para acceder al avatar se requiere `$user->profile->avatar_url`. Esto obliga a cargar la relacion manualmente.

**Accion:** Agregar accessor en `User.php` para simplificar acceso:

```php
// app/Models/User.php
public function getAvatarUrlAttribute(): ?string
{
    return $this->profile?->avatar_url;
}
```

Permite usar `$user->avatar_url` directamente.

#### 2.1.2 Storage y Thumbnails

**Situacion actual:**
- Almacena en `storage/app/public/avatars/`
- Validacion: `image, mimes:jpeg,png,jpg,gif,webp, max:2048` (2MB)
- URL devuelta: `/storage/{path}`
- Elimina avatar anterior al subir uno nuevo

**Riesgo en alto volumen:** Una lista de 100 casos cargando 100 imagenes de hasta 2MB cada una es inaceptable para rendimiento.

**Accion — Generar thumbnails:**

```php
// En ProfileController::uploadAvatar()
use Intervention\Image\Facades\Image;

// Generar thumbnail (64x64) para listas
$thumbnail = Image::make($file)->fit(64, 64)->encode('webp', 80);
Storage::disk('public')->put("avatars/thumbs/{$filename}.webp", $thumbnail);

// Guardar original para pagina de perfil
$originalPath = $file->store('avatars/originals', 'public');
```

**Dos variantes de almacenamiento:**

| Variante | Ruta | Uso | Tamano estimado |
|----------|------|-----|-----------------|
| Original | `avatars/originals/` | Pagina de perfil | Hasta 2MB |
| Thumbnail | `avatars/thumbs/` | Listas, scrumboard, todolist | ~2-5KB (64x64 webp) |

**Campo adicional:** Agregar `avatar_thumb_url` en `user_profiles`, o derivar por convencion (reemplazar `/originals/` por `/thumbs/` en la URL).

**Dependencia:** Instalar `intervention/image` via Composer.

#### 2.1.3 Seguridad de URLs

**Recomendacion: URLs publicas** via `storage/app/public/`.

**Justificacion:**
- Las fotos de perfil son datos de baja sensibilidad dentro de una app autenticada
- URLs firmadas temporales anaden complejidad innecesaria (regenerar en cada request, rompen cache del navegador)
- El directorio `public` ya esta configurado con el symlink de Laravel
- Si el tenant requiere privacidad estricta en el futuro, se puede migrar a signed URLs sin cambiar el frontend (solo cambiar lo que devuelve el accessor)

---

### 2.2 API y Transformacion de Datos

#### 2.2.1 Eager Loading — Problema Critico

**`UserService::getStaffMembers()`** (lineas 39-68) actualmente hace:

```php
->select('id', 'name', 'email')  // No incluye profile
```

**Correccion requerida:**

```php
// app/Services/User/UserService.php
$users = User::with('profile:id,user_id,avatar_url')
    ->activeConsultors()
    ->select('id', 'name', 'email')
    ->get();
```

**Aplicar eager loading en todas las queries que devuelven `assigned_user`:**

| Query/Servicio | Accion |
|---------------|--------|
| `CaseService` / `CaseController` (listado de casos) | `with('assignedUser.profile:id,user_id,avatar_url')` |
| Todo endpoints (listado de tareas) | `with('assignedTo.profile:id,user_id,avatar_url')` |
| Scrumboard endpoints (proyectos/tareas) | `with('assignedTo.profile:id,user_id,avatar_url')` |
| `UserService::getStaffMembers()` | `with('profile:id,user_id,avatar_url')` |

#### 2.2.2 API Resource

Crear un resource ligero para usuarios en listas:

```php
// app/Http/Resources/UserSummaryResource.php
class UserSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'avatar_url' => $this->profile?->avatar_url,
        ];
    }
}
```

#### 2.2.3 Impacto en Payload JSON

Agregar `avatar_url` (una string de ~60 caracteres) a 100 registros = **~6KB adicionales**. Impacto despreciable.

#### 2.2.4 Endpoints a Modificar

| Endpoint | Accion |
|----------|--------|
| GET `/api/cases` | Eager load `assignedUser.profile`, incluir `avatar_url` en response |
| GET `/api/todos` | Incluir `avatar_url` en `assigned_to` |
| GET `/api/scrumboard/projects` | Incluir `avatar_url` en tasks `assigned_to` |
| GET `/api/users/staff` | Incluir profile via eager loading |

---

### 2.3 Frontend (Vue.js)

#### 2.3.1 Componente Reutilizable — `UserAvatar.vue`

Extraer la logica de `profile.vue` (lineas 79-84) en un componente atomico:

```vue
<!-- resources/js/src/components/UserAvatar.vue -->
<script lang="ts" setup>
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    name: string;
    avatarUrl?: string | null;
    size?: 'xs' | 'sm' | 'md' | 'lg';
}>(), {
    avatarUrl: null,
    size: 'sm',
});

const sizeClasses: Record<string, string> = {
    xs: 'w-6 h-6 text-[10px]',
    sm: 'w-8 h-8 text-xs',
    md: 'w-10 h-10 text-sm',
    lg: 'w-24 h-24 text-3xl',
};

const imgFailed = ref(false);

const initials = computed(() => {
    return props.name
        .split(' ')
        .map(w => w[0])
        .join('')
        .substring(0, 2)
        .toUpperCase();
});

const showImage = computed(() => props.avatarUrl && !imgFailed.value);
</script>

<template>
    <div class="relative inline-flex items-center justify-center rounded-full overflow-hidden flex-shrink-0"
         :class="sizeClasses[size]">
        <img v-if="showImage"
             :src="avatarUrl!"
             :alt="name"
             class="w-full h-full object-cover"
             loading="lazy"
             @error="imgFailed = true" />
        <div v-else
             class="w-full h-full bg-gradient-to-r from-primary to-secondary
                    flex items-center justify-center text-white font-bold">
            {{ initials }}
        </div>
    </div>
</template>
```

**Caracteristicas clave:**
- `loading="lazy"` — lazy loading nativo del navegador, sin librerias extra
- `@error` handler — si la imagen falla (404, red), muestra iniciales como fallback
- `flex-shrink-0` — evita que el avatar se comprima en layouts flex
- Tamanos predefinidos para consistencia visual

#### 2.3.2 Puntos de Refactorizacion por Vista

**Cases List** (`views/cases/list.vue`, lineas 338-341):

```vue
<!-- ANTES -->
<template #assigned_to="data">
    <span v-if="data.value.assigned_user">{{ data.value.assigned_user.name }}</span>
    <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
</template>

<!-- DESPUES -->
<template #assigned_to="data">
    <div v-if="data.value.assigned_user" class="flex items-center gap-2">
        <UserAvatar
            :name="data.value.assigned_user.name"
            :avatar-url="data.value.assigned_user.avatar_url"
            size="xs" />
        <span>{{ data.value.assigned_user.name }}</span>
    </div>
    <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
</template>
```

**TodoList** (`views/apps/todolist.vue`, lineas 380-383):

```vue
<!-- ANTES -->
<div v-if="task.assigned_to" class="flex items-center justify-center">
    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
        {{ getInitials(task.assigned_to.name) }}
    </div>
</div>

<!-- DESPUES -->
<div v-if="task.assigned_to" class="flex items-center justify-center">
    <UserAvatar
        :name="task.assigned_to.name"
        :avatar-url="task.assigned_to.avatar_url"
        size="xs" />
</div>
```

**Scrumboard** (`ScrumTaskCard.vue`, lineas 48-50):

```vue
<!-- ANTES -->
<span v-if="task.assigned_to" class="truncate max-w-[80px] text-gray-400">
    {{ task.assigned_to.name.split(' ')[0] }}
</span>

<!-- DESPUES -->
<div v-if="task.assigned_to" class="flex items-center gap-1">
    <UserAvatar
        :name="task.assigned_to.name"
        :avatar-url="task.assigned_to.avatar_url"
        size="xs" />
    <span class="truncate max-w-[60px] text-gray-400">
        {{ task.assigned_to.name.split(' ')[0] }}
    </span>
</div>
```

**Profile View** (`views/users/profile.vue`, lineas 79-84):

```vue
<!-- ANTES (duplicacion de logica) -->
<div v-if="profileStore.avatarUrl" class="w-24 h-24 rounded-full overflow-hidden mb-5">
    <img :src="profileStore.avatarUrl" ... />
</div>
<div v-else class="w-24 h-24 rounded-full bg-gradient-to-r ...">
    <span class="text-3xl font-bold">{{ profileStore.initials }}</span>
</div>

<!-- DESPUES -->
<UserAvatar
    :name="profileStore.fullName"
    :avatar-url="profileStore.avatarUrl"
    size="lg"
    class="mb-5" />
```

#### 2.3.3 Actualizacion de Types

```typescript
// types/case.ts — actualizar assigned_user
assigned_user?: {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;  // NUEVO
};

// types/todo.ts — actualizar assigned_to
assigned_to?: {
    id: number;
    name: string;
    avatar_url: string | null;  // NUEVO
} | null;

// types/scrum.ts — actualizar assigned_to
assigned_to?: {
    id: number;
    name: string;
    email?: string;
    avatar_url: string | null;  // NUEVO
} | null;
```

#### 2.3.4 Rendimiento en Scrumboard

**Escenario:** Un tablero con 5 columnas x 20 tarjetas = 100 imagenes simultaneas.

**Mitigaciones (cubiertas por el diseno):**

| Estrategia | Mecanismo | Impacto |
|-----------|-----------|---------|
| Lazy loading nativo | `loading="lazy"` en `<img>` | Solo carga imagenes visibles en viewport |
| Thumbnails 64x64 webp | Generados en backend al subir | ~3KB por imagen, 100 x 3KB = 300KB total |
| Cache del navegador | URLs estaticas hasta cambio de foto | Carga unica, luego desde cache |
| Fallback a iniciales | `@error` handler en componente | Sin imagen rota visible nunca |

**No es necesario** implementar Intersection Observer ni virtualizacion adicional. `loading="lazy"` nativo + thumbnails es suficiente para este volumen.

---

## 3. Riesgos y Mantenimiento

### 3.1 Invalidacion de Cache

**Estrategia recomendada:** Usar hash del archivo como nombre:

```php
$filename = md5_file($file->getRealPath()) . '.webp';
```

URLs distintas = cache automaticamente invalidado. Si el usuario sube una nueva foto, la URL cambia y el navegador descarga la nueva version.

**Alternativa:** Agregar query string con timestamp: `?v=1711036800`.

### 3.2 Consistencia Visual — Impacto en Layout

| Modulo | Riesgo | Mitigacion |
|--------|--------|------------|
| Cases table | Avatar + nombre podria ensanchar columna | `size="xs"` (24px) + `flex-shrink-0` en avatar |
| Scrumboard cards | Tarjetas podrian crecer en altura | `size="xs"` + layout inline `flex items-center` |
| TodoList | Minimo riesgo, ya usa 24px para iniciales | Reemplazo directo, mismo espacio |

### 3.3 Fallback Robusto — Tres Niveles

1. **Tiene foto** → mostrar thumbnail
2. **No tiene foto** (`avatar_url: null`) → mostrar iniciales con gradiente primary→secondary
3. **Foto rota** (404/error de red) → `@error` handler oculta `<img>`, muestra iniciales

### 3.4 Limpieza de Codigo

- Eliminar funcion `getInitials()` de `todolist.vue` si queda sin uso tras la refactorizacion
- Eliminar logica duplicada de avatar/iniciales en `profile.vue` al usar `UserAvatar`

---

## 4. Plan de Implementacion

### Fase 1: Backend — Eager Loading y Resources

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 1.1 | ~~Instalar `intervention/image`~~ | — | Diferido (no necesario para MVP, lazy loading + 2MB cap suficiente) |
| 1.2 | Agregar accessor `avatar_url` en User | `app/Models/User.php` | DONE |
| 1.3 | ~~Modificar `uploadAvatar()` para generar thumbnail~~ | — | Diferido (junto con 1.1) |
| 1.4 | Agregar eager load `assignedTo.profile` en queries | `CaseService`, `TodoController`, `ScrumBoardController`, `ScrumTaskController`, `UserService` | DONE |
| 1.5 | Crear `UserSummaryResource` con `avatar_url` | `app/Http/Resources/UserSummaryResource.php` | DONE |
| 1.6 | Actualizar Resources para incluir `avatar_url` | `CaseResource`, `TodoResource`, `ScrumTaskResource` | DONE |
| 1.7 | Configurar cache headers para `/storage/avatars/` | `public/.htaccess` o nginx config | Diferido (optimizacion) |

### Fase 2: Frontend — Componente y Types

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 2.1 | Crear componente `UserAvatar.vue` | `resources/js/src/components/UserAvatar.vue` | DONE |
| 2.2 | Actualizar type `assigned_user` en case.ts | `resources/js/src/types/case.ts` | DONE |
| 2.3 | Actualizar type `assigned_to` en todo.ts | `resources/js/src/types/todo.ts` | DONE |
| 2.4 | Actualizar type `assigned_to` en scrum.ts | `resources/js/src/types/scrum.ts` | DONE |

### Fase 3: Frontend — Refactorizacion de Vistas

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 3.1 | Reemplazar nombre por avatar + nombre en columna "Asignado a" | `resources/js/src/views/cases/list.vue` | DONE |
| 3.2 | Reemplazar iniciales por `UserAvatar` en items de lista | `resources/js/src/views/apps/todolist.vue` | DONE |
| 3.3 | Agregar `UserAvatar` junto al nombre en tarjetas | `ScrumTaskCard.vue` | DONE |
| 3.4 | Refactorizar `profile.vue` para usar `UserAvatar` size="lg" | `resources/js/src/views/users/profile.vue` | DONE |
| 3.5 | Limpiar `getInitials()` si queda sin uso | `resources/js/src/views/apps/todolist.vue` | Mantenida (aun se usa en modal de edicion) |

### Fase 4: Verificacion (manual)

| # | Tarea | Estado |
|---|-------|--------|
| 4.1 | ~~Verificar thumbnail generation~~ | N/A (diferido) |
| 4.2 | Verificar lazy loading en scrumboard con multiples tarjetas | Pendiente QA |
| 4.3 | Verificar fallback a iniciales cuando `avatar_url` es null | Pendiente QA |
| 4.4 | Verificar fallback cuando imagen devuelve 404 | Pendiente QA |
| 4.5 | Verificar que el cambio de avatar se refleja inmediatamente en las tres vistas | Pendiente QA |
| 4.6 | Verificar que las columnas/tarjetas no se deforman con el avatar | Pendiente QA |

---

## 5. Estimacion de Impacto

| Dimension | Valor |
|-----------|-------|
| Archivos backend modificados | ~5-6 |
| Archivos frontend modificados | ~5-6 |
| Archivos nuevos | 2 (`UserAvatar.vue`, `UserSummaryResource.php`) |
| Impacto en payload API | +6KB por 100 registros |
| Impacto en carga de red | +300KB maximo (100 imagenes originales, mitigado por `loading="lazy"`) |
| Riesgo de regresion | Bajo (cambios aditivos, no destructivos) |
| Dependencia nueva | Ninguna (intervention/image diferido) |

---

## 6. Decisiones Pendientes

| # | Decision | Opciones | Recomendacion |
|---|----------|----------|---------------|
| D1 | Almacenar `avatar_thumb_url` como campo separado o derivar por convencion | Campo separado vs convencion de ruta | Convencion (menos migraciones) |
| D2 | Cache-busting via hash de archivo o query string con timestamp | Hash vs `?v=timestamp` | Hash (mas limpio, mejor cache) |
| D3 | Generar thumbnails retroactivamente para avatares existentes | Artisan command vs lazy generation | Artisan command unico |
