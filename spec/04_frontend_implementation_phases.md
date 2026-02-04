# Plan de Implementacion Frontend: Vristo POC

## Metadata
- **Fecha:** 2026-01-20
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Tiempo Total Estimado:** ~156-198 horas

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad | Prerequisito Backend | Estado |
|------|--------|--------|-----------|---------------------|--------|
| 1 | Infraestructura Base y Tipos TypeScript | 8h | CRITICO | - | ✅ COMPLETADA |
| 2 | Interceptor HTTP y Manejo de Errores | 6h | CRITICO | Fase 1 Backend | ✅ COMPLETADA |
| 3 | Verificacion de Email UI | 8h | MEDIO | Fase 3 Backend | ✅ COMPLETADA |
| 4 | Sistema de Roles y Permisos Frontend | 12h | ALTO | Fase 2 Backend | ✅ COMPLETADA |
| 5 | Gestion de Usuarios - Lista y Filtros | 14h | ALTO | Fase 2 Backend | ✅ COMPLETADA |
| 6 | Gestion de Usuarios - Crear y Editar | 12h | ALTO | Fase 5 Frontend | ✅ COMPLETADA |
| 7 | Gestion de Usuarios - Eliminar y Acciones Masivas | 8h | MEDIO | Fase 6 Frontend | ✅ COMPLETADA |
| 8 | Gestion de Perfil de Usuario | 14h | MEDIO | Fase 4 Backend | ✅ COMPLETADA |
| 9 | Two-Factor Authentication UI | 16h | MEDIO | Fase 5 Backend | Pendiente |
| 10 | Mejoras de UX y Optimizaciones | 12h | BAJO | Fases 1-9 | ✅ COMPLETADA |
| 11 | Testing - Setup y Tests Unitarios | 16h | ALTO | - | Pendiente |
| 12 | Testing - Tests de Componentes | 18h | MEDIO | Fase 11 | Pendiente |
| 13 | Documentacion y Guias de Usuario | 12h | BAJO | Todas | Pendiente |

---

## FASE 1: Infraestructura Base y Tipos TypeScript ✅ COMPLETADA

**Fecha de Completado:** 2026-01-21
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Establecer la estructura base de tipos TypeScript y servicios que seran usados en todo el frontend.

### Prerequisitos Backend
- Ninguno (puede iniciar en paralelo)

### Tareas

#### 1.1 Estructura de Tipos (3h) ✅ COMPLETADO
- [x] Crear directorio `resources/js/src/types/`
- [x] Crear `types/user.ts` con interfaces User, UserProfile, CreateUserData, UpdateUserData
- [x] Crear `types/auth.ts` con interfaces LoginCredentials, RegisterData, AuthResponse
- [x] Crear `types/pagination.ts` con interfaces PaginationParams, PaginatedResponse, Meta, Links
- [x] Crear `types/role.ts` con interfaces Role, Permission
- [x] Crear `types/api.ts` con interfaces ApiError, ValidationError
- [x] Crear `types/index.ts` para re-exportar todos los tipos

#### 1.2 Configuracion TypeScript (2h) ✅ COMPLETADO
- [x] Actualizar `tsconfig.json` con paths aliases (resources/js/src/*)
- [x] Configurar strict mode (ya habilitado)
- [x] Agregar tipos globales en `types/global.d.ts`
- [x] Configurar tipos para variables de entorno (ImportMetaEnv)

#### 1.3 Utilidades Base (3h) ✅ COMPLETADO
- [x] Crear `utils/formatters.ts` para formateo de fechas, numeros
- [x] Crear `utils/validators.ts` para validaciones comunes
- [x] Crear `utils/storage.ts` para localStorage/sessionStorage
- [x] Crear `utils/permissions.ts` para helpers de permisos
- [x] Crear `utils/index.ts` para re-exportar todas las utilidades

### Archivos Afectados

**Nuevos:**
```
resources/js/src/types/
├── index.ts           ✅ Creado
├── user.ts            ✅ Creado
├── auth.ts            ✅ Creado
├── pagination.ts      ✅ Creado
├── role.ts            ✅ Creado
├── api.ts             ✅ Creado
└── global.d.ts        ✅ Creado

resources/js/src/utils/
├── index.ts           ✅ Creado
├── formatters.ts      ✅ Creado
├── validators.ts      ✅ Creado
├── storage.ts         ✅ Creado
└── permissions.ts     ✅ Creado
```

**Modificados:**
```
tsconfig.json          ✅ Actualizado paths y includes
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Tipos exportables desde `@/types`
- [x] No errores de TypeScript en build (`npm run build` exitoso)
- [x] Autocompletado funcionando en IDE
- [x] Utilidades importables desde `@/utils`

### Dependencias para Siguiente Fase
- Tipos base definidos
- Utilidades disponibles

---

## FASE 2: Interceptor HTTP y Manejo de Errores ✅ COMPLETADA

**Fecha de Completado:** 2026-01-21
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Mejorar el servicio de API con interceptores robustos y manejo centralizado de errores.

### Prerequisitos Backend
- Fase 1 Backend (Rate Limiting)

### Tareas

#### 2.1 Mejorar api.ts (3h) ✅ COMPLETADO
- [x] Agregar interceptor de request para CSRF automatico
- [x] Agregar interceptor de response para errores globales
- [x] Implementar manejo de error 401 (redirect a login)
- [x] Implementar manejo de error 419 (refresh CSRF)
- [x] Implementar manejo de error 429 (rate limit)
- [x] Implementar manejo de error 403 (forbidden)
- [x] Implementar manejo de error 500 (server error)

#### 2.2 Sistema de Notificaciones (2h) ✅ COMPLETADO
- [x] Crear `composables/useNotification.ts`
- [x] Integrar con SweetAlert2 existente
- [x] Crear metodos: success, error, warning, info
- [x] Crear metodo confirm para confirmaciones

#### 2.3 Loading State Global (1h) ✅ COMPLETADO
- [x] Agregar loading state al store principal
- [x] Crear composable `useLoading.ts`
- [x] Implementar indicador de carga global

### Archivos Afectados

**Nuevos:**
```
resources/js/src/composables/useNotification.ts    ✅ Creado
resources/js/src/composables/useLoading.ts         ✅ Creado
resources/js/src/composables/index.ts              ✅ Creado
```

**Modificados:**
```
resources/js/src/services/api.ts                   ✅ Actualizado
resources/js/src/stores/index.ts                   ✅ Actualizado
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Errores 401 redirigen a login automaticamente
- [x] Errores 429 muestran mensaje amigable
- [x] CSRF se refresca automaticamente en 419
- [x] Notificaciones funcionan globalmente

### Dependencias para Siguiente Fase
- Manejo de errores centralizado
- Notificaciones disponibles

---

## FASE 3: Verificacion de Email UI ✅ COMPLETADA

**Fecha de Completado:** 2026-01-21
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar la interfaz de usuario para verificacion de email.

### Prerequisitos Backend
- Fase 3 Backend (Email Verification endpoints)

### Tareas

#### 3.1 Vista de Aviso de Verificacion (3h) ✅ COMPLETADO
- [x] Crear `views/auth/email-verification-notice.vue`
- [x] Mostrar mensaje de "verifica tu email"
- [x] Agregar boton de reenviar email
- [x] Implementar countdown para reenvio (evitar spam)
- [x] Disenar con Tailwind CSS siguiendo tema existente

#### 3.2 Vista de Verificacion Exitosa (2h) ✅ COMPLETADO
- [x] Crear `views/auth/verify-email.vue`
- [x] Parsear token de URL
- [x] Llamar API de verificacion
- [x] Mostrar estado de exito/error
- [x] Redirect a dashboard despues de verificar

#### 3.3 Actualizar Auth Service (1h) ✅ COMPLETADO
- [x] Agregar metodo `sendVerificationEmail()` a authService
- [x] Agregar metodo `verifyEmail(token)` a authService
- [x] Agregar campo `email_verified_at` a tipo User

#### 3.4 Navigation Guards (2h) ✅ COMPLETADO
- [x] Actualizar router guards para verificar email_verified_at
- [x] Crear lista de rutas que requieren verificacion
- [x] Redirect a verification-notice si no verificado
- [x] Permitir acceso a rutas de verificacion sin auth

### Archivos Afectados

**Nuevos:**
```
resources/js/src/views/auth/email-verification-notice.vue  ✅ Creado
resources/js/src/views/auth/verify-email.vue               ✅ Creado
```

**Modificados:**
```
resources/js/src/services/authService.ts                   ✅ Actualizado
resources/js/src/router/index.ts                           ✅ Actualizado
resources/js/src/stores/auth.ts                            ✅ Actualizado
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Usuario no verificado ve aviso de verificacion
- [x] Boton de reenvio funciona con rate limit
- [x] Link de verificacion funciona desde email
- [x] Usuario verificado accede normalmente

### Dependencias para Siguiente Fase
- Sistema de verificacion completo
- Guards actualizados

---

## FASE 4: Sistema de Roles y Permisos Frontend ✅ COMPLETADA

**Fecha de Completado:** 2026-01-21
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar directivas y composables para control de acceso basado en roles y permisos.

### Prerequisitos Backend
- Fase 2 Backend (Roles y Permisos API)

### Tareas

#### 4.1 Actualizar Auth Store (3h) ✅ COMPLETADO
- [x] Agregar `roles: string[]` al state
- [x] Agregar `permissions: string[]` al state
- [x] Crear getter `hasRole(role: string): boolean`
- [x] Crear getter `hasPermission(permission: string): boolean`
- [x] Crear getter `hasAnyPermission(permissions: string[]): boolean`
- [x] Actualizar fetchUser para obtener roles y permisos

#### 4.2 Directivas de Permisos (4h) ✅ COMPLETADO
- [x] Crear directiva `v-can="'permission'"`
- [x] Crear directiva `v-role="'role'"`
- [x] Registrar directivas globalmente en main.ts
- [x] Soportar arrays: `v-can="['perm1', 'perm2']"`

#### 4.3 Composable de Permisos (2h) ✅ COMPLETADO
- [x] Crear `composables/usePermissions.ts`
- [x] Exportar funciones: can, hasRole, isAdmin
- [x] Integrar con auth store

#### 4.4 Guards de Rutas con Permisos (3h) ✅ COMPLETADO
- [x] Actualizar router guards para verificar permisos
- [x] Agregar meta `permission` a rutas admin
- [x] Agregar meta `role` a rutas especificas
- [x] Crear pagina 403 Forbidden

### Archivos Afectados

**Nuevos:**
```
resources/js/src/directives/can.ts                 ✅ Creado
resources/js/src/directives/role.ts                ✅ Creado
resources/js/src/directives/index.ts               ✅ Creado
resources/js/src/composables/usePermissions.ts     ✅ Creado
resources/js/src/views/pages/error403.vue          ✅ Creado
```

**Modificados:**
```
resources/js/src/stores/auth.ts                    ✅ Actualizado
resources/js/src/router/index.ts                   ✅ Actualizado
resources/js/src/main.ts                           ✅ Actualizado
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] `v-can="'users.view'"` oculta elementos sin permiso
- [x] `v-role="'admin'"` funciona correctamente
- [x] Rutas protegidas por permiso funcionan
- [x] Usuario sin permiso ve error 403

### Dependencias para Siguiente Fase
- Directivas de permisos funcionando
- Guards actualizados

---

## FASE 5: Gestion de Usuarios - Lista y Filtros ✅ COMPLETADA

**Fecha de Completado:** 2026-01-21
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar la vista de lista de usuarios con paginacion, busqueda y filtros.

### Prerequisitos Backend
- Fase 2 Backend (User CRUD API)
- Fase 4 Frontend (Permisos)

### Tareas

#### 5.1 User Service (3h) ✅ COMPLETADO
- [x] Crear `services/userService.ts`
- [x] Implementar metodos: getUsers, getUser, createUser, updateUser, deleteUser
- [x] Tipar todas las respuestas correctamente
- [x] Manejar paginacion

#### 5.2 User Store (3h) ✅ COMPLETADO
- [x] Crear `stores/user.ts` con Pinia
- [x] State: users, currentUser, pagination, isLoading, error
- [x] Actions: fetchUsers, createUser, updateUser, deleteUser
- [x] Getters: getUserById, totalUsers

#### 5.3 Vista de Lista (5h) ✅ COMPLETADO
- [x] Crear `views/admin/users/list.vue`
- [x] Implementar tabla con vue3-datatable
- [x] Columnas: ID, Nombre, Email, Roles, Estado, Acciones
- [x] Agregar paginacion
- [x] Agregar busqueda por nombre/email
- [x] Agregar filtro por rol
- [x] Agregar ordenamiento por columna

#### 5.4 Rutas y Menu (3h) ✅ COMPLETADO
- [x] Agregar rutas en router: /admin/users
- [x] Agregar item en Sidebar.vue (solo visible con permiso)
- [x] Aplicar guard de permiso `users.view`

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/userService.ts       ✅ Creado
resources/js/src/services/roleService.ts       ✅ Creado
resources/js/src/stores/user.ts                ✅ Creado
resources/js/src/views/admin/users/list.vue    ✅ Creado
resources/js/src/views/admin/users/create.vue  ✅ Creado (placeholder)
resources/js/src/views/admin/users/show.vue    ✅ Creado (placeholder)
resources/js/src/views/admin/users/edit.vue    ✅ Creado (placeholder)
```

**Modificados:**
```
resources/js/src/router/index.ts               ✅ Actualizado
resources/js/src/components/layout/Sidebar.vue ✅ Actualizado
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Lista muestra usuarios paginados
- [x] Busqueda filtra en tiempo real
- [x] Filtro por rol funciona
- [x] Solo admins ven el menu
- [x] Paginacion funciona correctamente

### Dependencias para Siguiente Fase
- Lista de usuarios funcionando
- User store creado

---

## FASE 6: Gestion de Usuarios - Crear y Editar ✅ COMPLETADA

**Fecha de Completado:** 2026-01-25
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar formularios para crear y editar usuarios.

### Prerequisitos Backend
- Fase 5 Frontend completada

### Tareas

#### 6.1 Role Service (2h) ✅ COMPLETADO (en Fase 5)
- [x] Crear `services/roleService.ts`
- [x] Implementar metodos: getRoles, getPermissions
- [x] Tipar respuestas

#### 6.2 Formulario de Creacion (4h) ✅ COMPLETADO
- [x] Crear `views/admin/users/create.vue`
- [x] Campos: name, email, password, password_confirmation
- [x] Selector de roles (checkboxes con colores por tipo)
- [x] Checkbox "Enviar email de bienvenida"
- [x] Validacion con Vuelidate
- [x] Submit y manejo de errores

#### 6.3 Formulario de Edicion (4h) ✅ COMPLETADO
- [x] Crear `views/admin/users/edit.vue`
- [x] Cargar datos existentes
- [x] Campos editables: name, email, password (opcional con toggle)
- [x] Selector de roles
- [x] Validacion
- [x] Submit y manejo de errores
- [x] Seccion de informacion del usuario (ID, estado, fechas)

#### 6.4 Vista de Detalle (Adicional) ✅ COMPLETADO
- [x] Crear `views/admin/users/show.vue`
- [x] Mostrar informacion completa del usuario
- [x] Tarjeta con avatar y roles
- [x] Informacion de cuenta detallada
- [x] Lista de permisos
- [x] Acciones rapidas (editar, eliminar, reenviar verificacion)

#### 6.5 Integracion (2h) ✅ COMPLETADO
- [x] Agregar rutas: /admin/users/create, /admin/users/:id/edit, /admin/users/:id
- [x] Botones de navegacion en lista
- [x] Redirect despues de crear/editar

### Archivos Afectados

**Nuevos/Actualizados:**
```
resources/js/src/services/roleService.ts       ✅ Ya existia (Fase 5)
resources/js/src/views/admin/users/create.vue  ✅ Implementado completo
resources/js/src/views/admin/users/edit.vue    ✅ Implementado completo
resources/js/src/views/admin/users/show.vue    ✅ Implementado completo
```

**Modificados:**
```
resources/js/src/router/index.ts               ✅ Ya configurado (Fase 5)
resources/js/src/views/admin/users/list.vue    ✅ Ya funcional (Fase 5)
resources/js/src/stores/user.ts                ✅ Ya funcional (Fase 5)
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Crear usuario funciona con validacion
- [x] Editar usuario carga datos existentes
- [x] Roles asignables correctamente (checkboxes)
- [x] Errores de validacion se muestran
- [x] Redirect correcto despues de guardar
- [x] Vista de detalle muestra toda la informacion

### Dependencias para Siguiente Fase
- CRUD de usuarios funcionando
- Role service creado

---

## FASE 7: Gestion de Usuarios - Eliminar y Acciones Masivas ✅ COMPLETADA

**Fecha de Completado:** 2026-01-25
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar eliminacion de usuarios y acciones masivas (bulk actions).

### Prerequisitos Backend
- Fase 6 Frontend completada

### Tareas

#### 7.1 Confirmacion de Eliminacion (2h) ✅ COMPLETADO
- [x] Implementar modal de confirmacion con SweetAlert2
- [x] Mostrar nombre del usuario a eliminar
- [x] Prevenir eliminacion del ultimo admin (backend)
- [x] Manejar errores de eliminacion
- [x] Prevenir auto-eliminacion (frontend + backend)

#### 7.2 Seleccion Multiple (3h) ✅ COMPLETADO
- [x] Agregar checkboxes a filas de tabla (vue3-datatable hasCheckbox)
- [x] Checkbox "Seleccionar todos" en header (integrado en datatable)
- [x] State para items seleccionados (selectedUsers ref)
- [x] Contador de items seleccionados
- [x] Boton "Clear Selection" para limpiar seleccion

#### 7.3 Acciones Masivas (3h) ✅ COMPLETADO
- [x] Dropdown de acciones masivas (Popper component)
- [x] Accion: Eliminar seleccionados
- [x] Confirmacion antes de ejecutar con advertencia de admins
- [x] Feedback de progreso (notificaciones de exito/error)
- [x] Excluir usuario actual de eliminacion masiva

### Archivos Afectados

**Modificados:**
```
resources/js/src/views/admin/users/list.vue    ✅ Actualizado con seleccion multiple y bulk actions
resources/js/src/stores/user.ts                ✅ Ya tenia bulkDeleteUsers
resources/js/src/services/userService.ts       ✅ Ya tenia bulkDeleteUsers
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Eliminar usuario con confirmacion
- [x] Seleccion multiple funciona
- [x] Acciones masivas ejecutan correctamente
- [x] No se puede eliminar ultimo admin (validacion backend)
- [x] No se puede auto-eliminar (validacion frontend + backend)

### Dependencias para Siguiente Fase
- CRUD completo de usuarios

---

## FASE 8: Gestion de Perfil de Usuario ✅ COMPLETADA

**Fecha de Completado:** 2026-01-25
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Implementar pagina de perfil donde el usuario puede ver y editar su informacion.

### Prerequisitos Backend
- Fase 4 Backend (Profile API)

### Tareas

#### 8.1 Profile Service (2h) ✅ COMPLETADO
- [x] Crear `services/profileService.ts`
- [x] Metodos: getProfile, updateProfile, uploadAvatar, deleteAvatar, changePassword
- [x] Tipar respuestas con interfaces

#### 8.2 Profile Store (2h) ✅ COMPLETADO
- [x] Crear `stores/profile.ts`
- [x] State: user, profile, isLoading, isSaving, isUploadingAvatar, error
- [x] Getters: fullName, email, avatarUrl, hasProfile, initials, location
- [x] Actions: fetchProfile, updateProfile, uploadAvatar, deleteAvatar, changePassword

#### 8.3 Vista de Perfil (6h) ✅ COMPLETADO
- [x] Refactorizar `views/users/profile.vue`
- [x] Seccion: Informacion basica (nombre, email, roles)
- [x] Seccion: Informacion de contacto (telefono, direccion, ubicacion)
- [x] Seccion: Avatar con iniciales fallback
- [x] Seccion: Bio, social links y preferencias
- [x] Quick actions con links a configuracion

#### 8.4 Cambio de Password (2h) ✅ COMPLETADO
- [x] Seccion de cambio de password en account settings (Security tab)
- [x] Campos: current_password, password, password_confirmation
- [x] Validacion con Vuelidate (required, minLength, sameAs)
- [x] Toggle para mostrar/ocultar passwords
- [x] Llamada a API de cambio con feedback

#### 8.5 Configuracion de Cuenta (2h) ✅ COMPLETADO
- [x] Actualizar `views/users/user-account-settings.vue`
- [x] Tabs: General, Security, Preferences, Danger Zone
- [x] Integrar con profile store
- [x] Formulario de perfil completo (info basica, direccion, social links)
- [x] Avatar upload con validacion de tamano y tipo
- [x] Preferencias de idioma y timezone
- [x] Hash navigation para tab Security (#security)

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/profileService.ts     ✅ Creado
resources/js/src/stores/profile.ts              ✅ Creado
```

**Modificados:**
```
resources/js/src/views/users/profile.vue             ✅ Refactorizado completamente
resources/js/src/views/users/user-account-settings.vue ✅ Refactorizado completamente
resources/js/src/types/user.ts                       ✅ Agregado SocialLinks interface
```

### Criterios de Aceptacion ✅ TODOS CUMPLIDOS
- [x] Usuario puede ver su perfil
- [x] Usuario puede editar informacion
- [x] Avatar upload funciona con validacion
- [x] Cambio de password funciona con validacion
- [x] Preferencias se guardan (timezone, language)

### Dependencias para Siguiente Fase
- Profile management completo

---

## FASE 9: Two-Factor Authentication UI ✅ COMPLETADA

**Fecha de Completado:** 2026-01-30
**Build:** npm run build - Sin errores TypeScript

### Objetivo
Implementar la interfaz completa para configuracion y uso de 2FA.

### Prerequisitos Backend
- Fase 5 Backend (2FA API)

### Tareas

#### 9.1 Two Factor Service (2h) ✅ COMPLETADO
- [x] Crear `services/twoFactorService.ts`
- [x] Metodos: enable, confirm, disable, getRecoveryCodes, regenerateRecoveryCodes, challenge
- [x] Tipar respuestas (TwoFactorSetupResponse, RecoveryCodesResponse, TwoFactorChallengeData, etc.)

#### 9.2 Configuracion 2FA en Settings (6h) ✅ COMPLETADO
- [x] Crear seccion 2FA en `user-account-settings.vue` (Security tab)
- [x] Estado: habilitado/deshabilitado con badge
- [x] Boton habilitar que muestra QR inline
- [x] QR code SVG + secret manual para escanear
- [x] Input para confirmar codigo de 6 digitos
- [x] Mostrar recovery codes en SweetAlert al confirmar
- [x] Boton para ver y regenerar recovery codes (requiere password)
- [x] Boton para deshabilitar 2FA (requiere password via SweetAlert)

#### 9.3 Vista de Challenge 2FA (4h) ✅ COMPLETADO
- [x] Crear `views/auth/two-factor-challenge.vue`
- [x] Input para codigo de 6 digitos con inputmode="numeric"
- [x] Link para usar recovery code (toggle entre modos)
- [x] Input alternativo para recovery code
- [x] Manejo de errores con alert
- [x] Auto-submit cuando se ingresan 6 digitos
- [x] Estilo boxed matching boxed-signin.vue

#### 9.4 Actualizar Auth Flow (4h) ✅ COMPLETADO
- [x] Actualizar authService para detectar two_factor_required (user opcional en AuthResponse)
- [x] Actualizar auth store con twoFactorRequired state + verifyTwoFactor + cancelTwoFactor
- [x] Actualizar boxed-signin.vue login flow para redirect a challenge
- [x] Actualizar router guards (redirige a challenge si twoFactorRequired, bloquea acceso sin twoFactorRequired)
- [x] Agregar `two-factor-challenge` a publicRoutes
- [x] Actualizar types/user.ts con two_factor_confirmed_at

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/twoFactorService.ts
resources/js/src/views/auth/two-factor-challenge.vue
```

**Modificados:**
```
resources/js/src/views/users/user-account-settings.vue (2FA section en Security tab)
resources/js/src/views/auth/boxed-signin.vue (2FA redirect en handleSubmit)
resources/js/src/services/authService.ts (AuthResponse con two_factor_required)
resources/js/src/stores/auth.ts (twoFactorRequired state + 2FA actions)
resources/js/src/router/index.ts (ruta + guards 2FA)
resources/js/src/types/user.ts (two_factor_confirmed_at)
```

### Criterios de Aceptacion
- [x] Usuario puede habilitar 2FA con QR
- [x] Login muestra challenge cuando 2FA activo
- [x] Recovery codes funcionan
- [x] Usuario puede deshabilitar 2FA

### Dependencias para Siguiente Fase
- 2FA funcionando end-to-end

---

## FASE 10: Mejoras de UX y Optimizaciones ✅ COMPLETADA

**Fecha de Completado:** 2026-01-31
**Build:** Exitoso - sin errores de TypeScript

### Objetivo
Mejorar la experiencia de usuario y optimizar rendimiento.

### Prerequisitos Backend
- Fases 1-9 completadas

### Tareas

#### 10.1 Loading States (3h) ✅ COMPLETADO
- [x] Agregar skeletons a listas mientras cargan (users list, user show, profile)
- [x] Agregar spinners a botones durante submit (ya existentes)
- [x] Agregar shimmer effects a cards (skeleton con animate-pulse)

#### 10.2 Empty States (2h) ✅ COMPLETADO
- [x] Disenar empty state para lista de usuarios vacia (icono + CTA "Add First User")
- [x] Empty state para busqueda sin resultados (icono busqueda + boton "Clear Filters")
- [x] Ilustraciones o iconos apropiados (IconUsers, IconSearch)

#### 10.3 Responsive Design (3h) ✅ COMPLETADO
- [x] Revisar vistas admin en mobile
- [x] Ajustar tabla de usuarios para mobile (cards con md:hidden / tabla con hidden md:block)
- [x] Paginacion mobile (Previous/Next buttons)
- [x] Revisar formularios en pantallas pequenas

#### 10.4 Accessibilidad (2h) ✅ COMPLETADO
- [x] Agregar aria-labels en botones de accion (View, Edit, Delete)
- [x] aria-invalid + aria-describedby en inputs con validacion (create, edit, account settings)
- [x] role="alert" en mensajes de error
- [x] fieldset + legend en grupos de roles
- [x] aria-expanded en dropdowns del sidebar (9 menus)
- [x] aria-label en nav, toggle sidebar, social links, file upload, 2FA buttons
- [x] aria-label en TabList de account settings
- [x] role="img" + aria-label en avatars, aria-hidden en initials
- [x] role="search" en filtros de lista de usuarios

#### 10.5 Performance (2h) ✅ COMPLETADO
- [x] Composable useDebounce reutilizable con isDebouncing indicator
- [x] Debounce en busquedas (reemplazado setTimeout manual)
- [x] Lazy loading de componentes ya implementado (todas las rutas)
- [x] Bundle size verificado en build

### Archivos Afectados

**Nuevos:**
```
resources/js/src/composables/useDebounce.ts
```

**Modificados:**
```
resources/js/src/composables/index.ts
resources/js/src/views/admin/users/list.vue
resources/js/src/views/admin/users/create.vue
resources/js/src/views/admin/users/edit.vue
resources/js/src/views/admin/users/show.vue
resources/js/src/views/users/profile.vue
resources/js/src/views/users/user-account-settings.vue
resources/js/src/components/layout/Sidebar.vue
```

### Criterios de Aceptacion
- [x] No hay estados de carga "vacios" (skeletons en lugar de spinners)
- [x] Funciona bien en mobile (cards responsive en lista de usuarios)
- [x] Atributos ARIA verificables en DevTools
- [x] Bundle size razonable (build exitoso)

### Dependencias para Siguiente Fase
- UX pulida

---

## FASE 11: Testing - Setup y Tests Unitarios

### Objetivo
Configurar entorno de testing y escribir tests unitarios para stores y servicios.

### Prerequisitos Backend
- Ninguno

### Tareas

#### 11.1 Setup Testing Framework (4h)
- [ ] Instalar Vitest: `npm install -D vitest`
- [ ] Instalar Vue Test Utils: `npm install -D @vue/test-utils`
- [ ] Instalar happy-dom: `npm install -D happy-dom`
- [ ] Crear `vitest.config.ts`
- [ ] Configurar scripts en package.json
- [ ] Crear setup file para mocks globales

#### 11.2 Tests de Auth Store (4h)
- [ ] Crear `tests/unit/stores/auth.spec.ts`
- [ ] Test: login exitoso actualiza state
- [ ] Test: login fallido maneja error
- [ ] Test: logout limpia state
- [ ] Test: fetchUser recupera usuario
- [ ] Test: getters de permisos

#### 11.3 Tests de User Store (4h)
- [ ] Crear `tests/unit/stores/user.spec.ts`
- [ ] Test: fetchUsers actualiza lista
- [ ] Test: createUser agrega a lista
- [ ] Test: updateUser modifica item
- [ ] Test: deleteUser remueve item
- [ ] Test: paginacion funciona

#### 11.4 Tests de Services (4h)
- [ ] Crear `tests/unit/services/authService.spec.ts`
- [ ] Crear `tests/unit/services/userService.spec.ts`
- [ ] Mock de axios
- [ ] Test: llamadas HTTP correctas
- [ ] Test: manejo de errores

### Archivos Afectados

**Nuevos:**
```
vitest.config.ts
tests/setup.ts
tests/unit/stores/auth.spec.ts
tests/unit/stores/user.spec.ts
tests/unit/services/authService.spec.ts
tests/unit/services/userService.spec.ts
```

**Modificados:**
```
package.json
```

### Criterios de Aceptacion
- [ ] `npm run test` ejecuta tests
- [ ] Coverage report disponible
- [ ] Todos los tests pasan
- [ ] Minimo 60% coverage en stores

### Dependencias para Siguiente Fase
- Testing framework configurado

---

## FASE 12: Testing - Tests de Componentes

### Objetivo
Escribir tests de componentes Vue para vistas principales.

### Prerequisitos Backend
- Fase 11 completada

### Tareas

#### 12.1 Tests de Auth Views (5h)
- [ ] Crear `tests/unit/views/auth/boxed-signin.spec.ts`
- [ ] Test: renderiza formulario
- [ ] Test: validacion de campos
- [ ] Test: submit llama a store
- [ ] Test: errores se muestran
- [ ] Repetir para boxed-signup.vue

#### 12.2 Tests de User Admin Views (6h)
- [ ] Crear `tests/unit/views/admin/users/list.spec.ts`
- [ ] Test: renderiza tabla
- [ ] Test: paginacion funciona
- [ ] Test: busqueda filtra
- [ ] Test: botones de accion visibles con permiso
- [ ] Tests para create.vue y edit.vue

#### 12.3 Tests de Profile Views (4h)
- [ ] Crear `tests/unit/views/users/profile.spec.ts`
- [ ] Test: carga datos de perfil
- [ ] Test: formulario de edicion
- [ ] Test: upload de avatar

#### 12.4 Tests de Composables (3h)
- [ ] Crear `tests/unit/composables/usePermissions.spec.ts`
- [ ] Crear `tests/unit/composables/useNotification.spec.ts`
- [ ] Test: funciones retornan valores correctos

### Archivos Afectados

**Nuevos:**
```
tests/unit/views/auth/boxed-signin.spec.ts
tests/unit/views/auth/boxed-signup.spec.ts
tests/unit/views/admin/users/list.spec.ts
tests/unit/views/admin/users/create.spec.ts
tests/unit/views/admin/users/edit.spec.ts
tests/unit/views/users/profile.spec.ts
tests/unit/composables/usePermissions.spec.ts
tests/unit/composables/useNotification.spec.ts
```

### Criterios de Aceptacion
- [ ] Todos los tests pasan
- [ ] Coverage > 60% en vistas principales
- [ ] Tests son mantenibles y claros

### Dependencias para Siguiente Fase
- Test suite completa

---

## FASE 13: Documentacion y Guias de Usuario

### Objetivo
Crear documentacion tecnica y guias para desarrolladores y usuarios.

### Prerequisitos Backend
- Todas las fases anteriores

### Tareas

#### 13.1 Documentacion de Componentes (4h)
- [ ] Documentar props de componentes principales
- [ ] Documentar eventos emitidos
- [ ] Documentar slots disponibles
- [ ] Ejemplos de uso en comentarios

#### 13.2 Documentacion de Stores (3h)
- [ ] Documentar state de cada store
- [ ] Documentar actions disponibles
- [ ] Documentar getters
- [ ] Ejemplos de uso

#### 13.3 Guia de Desarrollo (3h)
- [ ] Crear DEVELOPMENT.md con instrucciones
- [ ] Estructura del proyecto explicada
- [ ] Como agregar nuevas features
- [ ] Convenciones de codigo

#### 13.4 Guia de Usuario Admin (2h)
- [ ] Documentar flujo de gestion de usuarios
- [ ] Documentar configuracion de 2FA
- [ ] Screenshots de interfaces principales

### Archivos Afectados

**Nuevos:**
```
docs/DEVELOPMENT.md
docs/USER_GUIDE.md
docs/COMPONENTS.md
docs/STORES.md
```

### Criterios de Aceptacion
- [ ] Nuevo desarrollador puede entender el proyecto
- [ ] Documentacion actualizada
- [ ] Ejemplos funcionales

### Dependencias para Siguiente Fase
- Proyecto completamente documentado

---

## Diagrama de Dependencias entre Fases

```
FASE 1 (Tipos)
    │
    ▼
FASE 2 (HTTP/Errores)
    │
    ├──────────────────┬──────────────────┐
    ▼                  ▼                  ▼
FASE 3 (Email)    FASE 4 (Permisos)  FASE 11 (Testing)
    │                  │                  │
    │                  ▼                  ▼
    │             FASE 5 (Lista)     FASE 12 (Componentes)
    │                  │
    │                  ▼
    │             FASE 6 (Crear/Editar)
    │                  │
    │                  ▼
    │             FASE 7 (Eliminar)
    │                  │
    └────────┬─────────┘
             │
             ├──────────────────┐
             ▼                  ▼
        FASE 8 (Profile)   FASE 9 (2FA)
             │                  │
             └────────┬─────────┘
                      ▼
                 FASE 10 (UX)
                      │
                      ▼
                 FASE 13 (Docs)
```

---

## Scripts Utiles

```bash
# Desarrollo
npm run dev

# Build
npm run build

# Testing
npm run test           # Ejecutar tests
npm run test:watch     # Watch mode
npm run test:coverage  # Con coverage

# Type checking
npm run type-check

# Linting
npm run lint
npm run lint:fix
```

---

## Convenciones de Codigo Frontend

### Componentes Vue
- Usar `<script lang="ts" setup>`
- Composables empiezan con `use`
- Emits tipados con defineEmits
- Props tipados con defineProps

### TypeScript
- Interfaces en PascalCase
- Types en PascalCase
- Evitar `any`, usar `unknown` si necesario
- Tipar todas las funciones

### Estilos
- Tailwind CSS para styling
- Clases utilitarias preferidas
- Dark mode con `dark:` prefix
- Responsive con breakpoints sm/md/lg/xl

### Estado
- Pinia para estado global
- Composables para logica reutilizable
- Props/events para comunicacion padre-hijo

---

**Fin del Documento de Fases Frontend**

*Generado por Claude Code - Architect Agent*
*Fecha: 2026-01-20*
