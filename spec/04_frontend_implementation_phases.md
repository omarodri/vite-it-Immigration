# Plan de Implementacion Frontend: Vristo POC

## Metadata
- **Fecha:** 2026-01-20
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Tiempo Total Estimado:** ~156-198 horas

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad | Prerequisito Backend |
|------|--------|--------|-----------|---------------------|
| 1 | Infraestructura Base y Tipos TypeScript | 8h | CRITICO | - |
| 2 | Interceptor HTTP y Manejo de Errores | 6h | CRITICO | Fase 1 Backend |
| 3 | Verificacion de Email UI | 8h | MEDIO | Fase 3 Backend |
| 4 | Sistema de Roles y Permisos Frontend | 12h | ALTO | Fase 2 Backend |
| 5 | Gestion de Usuarios - Lista y Filtros | 14h | ALTO | Fase 2 Backend |
| 6 | Gestion de Usuarios - Crear y Editar | 12h | ALTO | Fase 5 Frontend |
| 7 | Gestion de Usuarios - Eliminar y Acciones Masivas | 8h | MEDIO | Fase 6 Frontend |
| 8 | Gestion de Perfil de Usuario | 14h | MEDIO | Fase 4 Backend |
| 9 | Two-Factor Authentication UI | 16h | MEDIO | Fase 5 Backend |
| 10 | Mejoras de UX y Optimizaciones | 12h | BAJO | Fases 1-9 |
| 11 | Testing - Setup y Tests Unitarios | 16h | ALTO | - |
| 12 | Testing - Tests de Componentes | 18h | MEDIO | Fase 11 |
| 13 | Documentacion y Guias de Usuario | 12h | BAJO | Todas |

---

## FASE 1: Infraestructura Base y Tipos TypeScript

### Objetivo
Establecer la estructura base de tipos TypeScript y servicios que seran usados en todo el frontend.

### Prerequisitos Backend
- Ninguno (puede iniciar en paralelo)

### Tareas

#### 1.1 Estructura de Tipos (3h)
- [ ] Crear directorio `resources/js/src/types/`
- [ ] Crear `types/user.ts` con interfaces User, UserProfile, CreateUserData, UpdateUserData
- [ ] Crear `types/auth.ts` con interfaces LoginCredentials, RegisterData, AuthResponse
- [ ] Crear `types/pagination.ts` con interfaces PaginationParams, PaginatedResponse, Meta, Links
- [ ] Crear `types/role.ts` con interfaces Role, Permission
- [ ] Crear `types/api.ts` con interfaces ApiError, ValidationError
- [ ] Crear `types/index.ts` para re-exportar todos los tipos

#### 1.2 Configuracion TypeScript (2h)
- [ ] Actualizar `tsconfig.json` con paths aliases
- [ ] Configurar strict mode si no esta habilitado
- [ ] Agregar tipos globales en `types/global.d.ts`
- [ ] Configurar tipos para variables de entorno

#### 1.3 Utilidades Base (3h)
- [ ] Crear `utils/formatters.ts` para formateo de fechas, numeros
- [ ] Crear `utils/validators.ts` para validaciones comunes
- [ ] Crear `utils/storage.ts` para localStorage/sessionStorage
- [ ] Crear `utils/permissions.ts` para helpers de permisos

### Archivos Afectados

**Nuevos:**
```
resources/js/src/types/
├── index.ts
├── user.ts
├── auth.ts
├── pagination.ts
├── role.ts
├── api.ts
└── global.d.ts

resources/js/src/utils/
├── formatters.ts
├── validators.ts
├── storage.ts
└── permissions.ts
```

**Modificados:**
```
tsconfig.json
```

### Criterios de Aceptacion
- [ ] Tipos exportables desde `@/types`
- [ ] No errores de TypeScript en build
- [ ] Autocompletado funcionando en IDE
- [ ] Utilidades importables desde `@/utils`

### Dependencias para Siguiente Fase
- Tipos base definidos
- Utilidades disponibles

---

## FASE 2: Interceptor HTTP y Manejo de Errores

### Objetivo
Mejorar el servicio de API con interceptores robustos y manejo centralizado de errores.

### Prerequisitos Backend
- Fase 1 Backend (Rate Limiting)

### Tareas

#### 2.1 Mejorar api.ts (3h)
- [ ] Agregar interceptor de request para CSRF automatico
- [ ] Agregar interceptor de response para errores globales
- [ ] Implementar manejo de error 401 (redirect a login)
- [ ] Implementar manejo de error 419 (refresh CSRF)
- [ ] Implementar manejo de error 429 (rate limit)
- [ ] Implementar manejo de error 403 (forbidden)
- [ ] Implementar manejo de error 500 (server error)

#### 2.2 Sistema de Notificaciones (2h)
- [ ] Crear `composables/useNotification.ts`
- [ ] Integrar con SweetAlert2 existente
- [ ] Crear metodos: success, error, warning, info
- [ ] Crear metodo confirm para confirmaciones

#### 2.3 Loading State Global (1h)
- [ ] Agregar loading state al store principal
- [ ] Crear composable `useLoading.ts`
- [ ] Implementar indicador de carga global

### Archivos Afectados

**Nuevos:**
```
resources/js/src/composables/useNotification.ts
resources/js/src/composables/useLoading.ts
```

**Modificados:**
```
resources/js/src/services/api.ts
resources/js/src/stores/index.ts
```

### Criterios de Aceptacion
- [ ] Errores 401 redirigen a login automaticamente
- [ ] Errores 429 muestran mensaje amigable
- [ ] CSRF se refresca automaticamente en 419
- [ ] Notificaciones funcionan globalmente

### Dependencias para Siguiente Fase
- Manejo de errores centralizado
- Notificaciones disponibles

---

## FASE 3: Verificacion de Email UI

### Objetivo
Implementar la interfaz de usuario para verificacion de email.

### Prerequisitos Backend
- Fase 3 Backend (Email Verification endpoints)

### Tareas

#### 3.1 Vista de Aviso de Verificacion (3h)
- [ ] Crear `views/auth/email-verification-notice.vue`
- [ ] Mostrar mensaje de "verifica tu email"
- [ ] Agregar boton de reenviar email
- [ ] Implementar countdown para reenvio (evitar spam)
- [ ] Disenar con Tailwind CSS siguiendo tema existente

#### 3.2 Vista de Verificacion Exitosa (2h)
- [ ] Crear `views/auth/verify-email.vue`
- [ ] Parsear token de URL
- [ ] Llamar API de verificacion
- [ ] Mostrar estado de exito/error
- [ ] Redirect a dashboard despues de verificar

#### 3.3 Actualizar Auth Service (1h)
- [ ] Agregar metodo `sendVerificationEmail()` a authService
- [ ] Agregar metodo `verifyEmail(token)` a authService
- [ ] Agregar campo `email_verified_at` a tipo User

#### 3.4 Navigation Guards (2h)
- [ ] Actualizar router guards para verificar email_verified_at
- [ ] Crear lista de rutas que requieren verificacion
- [ ] Redirect a verification-notice si no verificado
- [ ] Permitir acceso a rutas de verificacion sin auth

### Archivos Afectados

**Nuevos:**
```
resources/js/src/views/auth/email-verification-notice.vue
resources/js/src/views/auth/verify-email.vue
```

**Modificados:**
```
resources/js/src/services/authService.ts
resources/js/src/router/index.ts
resources/js/src/types/user.ts
```

### Criterios de Aceptacion
- [ ] Usuario no verificado ve aviso de verificacion
- [ ] Boton de reenvio funciona con rate limit
- [ ] Link de verificacion funciona desde email
- [ ] Usuario verificado accede normalmente

### Dependencias para Siguiente Fase
- Sistema de verificacion completo
- Guards actualizados

---

## FASE 4: Sistema de Roles y Permisos Frontend

### Objetivo
Implementar directivas y composables para control de acceso basado en roles y permisos.

### Prerequisitos Backend
- Fase 2 Backend (Roles y Permisos API)

### Tareas

#### 4.1 Actualizar Auth Store (3h)
- [ ] Agregar `roles: string[]` al state
- [ ] Agregar `permissions: string[]` al state
- [ ] Crear getter `hasRole(role: string): boolean`
- [ ] Crear getter `hasPermission(permission: string): boolean`
- [ ] Crear getter `hasAnyPermission(permissions: string[]): boolean`
- [ ] Actualizar fetchUser para obtener roles y permisos

#### 4.2 Directivas de Permisos (4h)
- [ ] Crear directiva `v-can="'permission'"`
- [ ] Crear directiva `v-role="'role'"`
- [ ] Registrar directivas globalmente en main.ts
- [ ] Soportar arrays: `v-can="['perm1', 'perm2']"`

#### 4.3 Composable de Permisos (2h)
- [ ] Crear `composables/usePermissions.ts`
- [ ] Exportar funciones: can, hasRole, isAdmin
- [ ] Integrar con auth store

#### 4.4 Guards de Rutas con Permisos (3h)
- [ ] Actualizar router guards para verificar permisos
- [ ] Agregar meta `permission` a rutas admin
- [ ] Agregar meta `role` a rutas especificas
- [ ] Crear pagina 403 Forbidden

### Archivos Afectados

**Nuevos:**
```
resources/js/src/directives/can.ts
resources/js/src/directives/role.ts
resources/js/src/composables/usePermissions.ts
resources/js/src/views/pages/error403.vue
```

**Modificados:**
```
resources/js/src/stores/auth.ts
resources/js/src/router/index.ts
resources/js/src/main.ts
```

### Criterios de Aceptacion
- [ ] `v-can="'users.view'"` oculta elementos sin permiso
- [ ] `v-role="'admin'"` funciona correctamente
- [ ] Rutas protegidas por permiso funcionan
- [ ] Usuario sin permiso ve error 403

### Dependencias para Siguiente Fase
- Directivas de permisos funcionando
- Guards actualizados

---

## FASE 5: Gestion de Usuarios - Lista y Filtros

### Objetivo
Implementar la vista de lista de usuarios con paginacion, busqueda y filtros.

### Prerequisitos Backend
- Fase 2 Backend (User CRUD API)
- Fase 4 Frontend (Permisos)

### Tareas

#### 5.1 User Service (3h)
- [ ] Crear `services/userService.ts`
- [ ] Implementar metodos: getUsers, getUser, createUser, updateUser, deleteUser
- [ ] Tipar todas las respuestas correctamente
- [ ] Manejar paginacion

#### 5.2 User Store (3h)
- [ ] Crear `stores/user.ts` con Pinia
- [ ] State: users, currentUser, pagination, isLoading, error
- [ ] Actions: fetchUsers, createUser, updateUser, deleteUser
- [ ] Getters: getUserById, totalUsers

#### 5.3 Vista de Lista (5h)
- [ ] Crear `views/admin/users/list.vue`
- [ ] Implementar tabla con vue3-datatable
- [ ] Columnas: ID, Nombre, Email, Roles, Estado, Acciones
- [ ] Agregar paginacion
- [ ] Agregar busqueda por nombre/email
- [ ] Agregar filtro por rol
- [ ] Agregar ordenamiento por columna

#### 5.4 Rutas y Menu (3h)
- [ ] Agregar rutas en router: /admin/users
- [ ] Agregar item en Sidebar.vue (solo visible con permiso)
- [ ] Aplicar guard de permiso `users.view`

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/userService.ts
resources/js/src/stores/user.ts
resources/js/src/views/admin/users/list.vue
```

**Modificados:**
```
resources/js/src/router/index.ts
resources/js/src/components/layout/Sidebar.vue
```

### Criterios de Aceptacion
- [ ] Lista muestra usuarios paginados
- [ ] Busqueda filtra en tiempo real
- [ ] Filtro por rol funciona
- [ ] Solo admins ven el menu
- [ ] Paginacion funciona correctamente

### Dependencias para Siguiente Fase
- Lista de usuarios funcionando
- User store creado

---

## FASE 6: Gestion de Usuarios - Crear y Editar

### Objetivo
Implementar formularios para crear y editar usuarios.

### Prerequisitos Backend
- Fase 5 Frontend completada

### Tareas

#### 6.1 Role Service (2h)
- [ ] Crear `services/roleService.ts`
- [ ] Implementar metodos: getRoles, getPermissions
- [ ] Tipar respuestas

#### 6.2 Formulario de Creacion (4h)
- [ ] Crear `views/admin/users/create.vue`
- [ ] Campos: name, email, password, password_confirmation
- [ ] Selector de roles (multiselect)
- [ ] Checkbox "Enviar email de bienvenida"
- [ ] Validacion con Vuelidate
- [ ] Submit y manejo de errores

#### 6.3 Formulario de Edicion (4h)
- [ ] Crear `views/admin/users/edit.vue`
- [ ] Cargar datos existentes
- [ ] Campos editables: name, email, password (opcional)
- [ ] Selector de roles
- [ ] Validacion
- [ ] Submit y manejo de errores

#### 6.4 Integracion (2h)
- [ ] Agregar rutas: /admin/users/create, /admin/users/:id/edit
- [ ] Botones de navegacion en lista
- [ ] Redirect despues de crear/editar

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/roleService.ts
resources/js/src/views/admin/users/create.vue
resources/js/src/views/admin/users/edit.vue
```

**Modificados:**
```
resources/js/src/router/index.ts
resources/js/src/views/admin/users/list.vue
resources/js/src/stores/user.ts
```

### Criterios de Aceptacion
- [ ] Crear usuario funciona con validacion
- [ ] Editar usuario carga datos existentes
- [ ] Roles asignables correctamente
- [ ] Errores de validacion se muestran
- [ ] Redirect correcto despues de guardar

### Dependencias para Siguiente Fase
- CRUD de usuarios funcionando
- Role service creado

---

## FASE 7: Gestion de Usuarios - Eliminar y Acciones Masivas

### Objetivo
Implementar eliminacion de usuarios y acciones masivas (bulk actions).

### Prerequisitos Backend
- Fase 6 Frontend completada

### Tareas

#### 7.1 Confirmacion de Eliminacion (2h)
- [ ] Implementar modal de confirmacion con SweetAlert2
- [ ] Mostrar nombre del usuario a eliminar
- [ ] Prevenir eliminacion del ultimo admin
- [ ] Manejar errores de eliminacion

#### 7.2 Seleccion Multiple (3h)
- [ ] Agregar checkboxes a filas de tabla
- [ ] Checkbox "Seleccionar todos" en header
- [ ] State para items seleccionados
- [ ] Contador de items seleccionados

#### 7.3 Acciones Masivas (3h)
- [ ] Dropdown de acciones masivas
- [ ] Accion: Eliminar seleccionados
- [ ] Accion: Asignar rol a seleccionados (opcional)
- [ ] Confirmacion antes de ejecutar
- [ ] Feedback de progreso

### Archivos Afectados

**Modificados:**
```
resources/js/src/views/admin/users/list.vue
resources/js/src/stores/user.ts
resources/js/src/services/userService.ts
```

### Criterios de Aceptacion
- [ ] Eliminar usuario con confirmacion
- [ ] Seleccion multiple funciona
- [ ] Acciones masivas ejecutan correctamente
- [ ] No se puede eliminar ultimo admin

### Dependencias para Siguiente Fase
- CRUD completo de usuarios

---

## FASE 8: Gestion de Perfil de Usuario

### Objetivo
Implementar pagina de perfil donde el usuario puede ver y editar su informacion.

### Prerequisitos Backend
- Fase 4 Backend (Profile API)

### Tareas

#### 8.1 Profile Service (2h)
- [ ] Crear `services/profileService.ts`
- [ ] Metodos: getProfile, updateProfile, uploadAvatar
- [ ] Tipar respuestas

#### 8.2 Profile Store (2h)
- [ ] Crear `stores/profile.ts`
- [ ] State: profile, isLoading, error
- [ ] Actions: fetchProfile, updateProfile, uploadAvatar

#### 8.3 Vista de Perfil (6h)
- [ ] Refactorizar `views/users/profile.vue`
- [ ] Seccion: Informacion basica (nombre, email)
- [ ] Seccion: Informacion de contacto (telefono, direccion)
- [ ] Seccion: Avatar con upload
- [ ] Seccion: Bio y preferencias
- [ ] Formulario de edicion con validacion

#### 8.4 Cambio de Password (2h)
- [ ] Seccion de cambio de password en profile
- [ ] Campos: current_password, new_password, confirm_password
- [ ] Validacion de strength
- [ ] Llamada a API de cambio

#### 8.5 Configuracion de Cuenta (2h)
- [ ] Actualizar `views/users/user-account-settings.vue`
- [ ] Tabs: General, Seguridad, Notificaciones
- [ ] Integrar con profile store
- [ ] Preferencias de idioma y timezone

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/profileService.ts
resources/js/src/stores/profile.ts
```

**Modificados:**
```
resources/js/src/views/users/profile.vue
resources/js/src/views/users/user-account-settings.vue
```

### Criterios de Aceptacion
- [ ] Usuario puede ver su perfil
- [ ] Usuario puede editar informacion
- [ ] Avatar upload funciona
- [ ] Cambio de password funciona
- [ ] Preferencias se guardan

### Dependencias para Siguiente Fase
- Profile management completo

---

## FASE 9: Two-Factor Authentication UI

### Objetivo
Implementar la interfaz completa para configuracion y uso de 2FA.

### Prerequisitos Backend
- Fase 5 Backend (2FA API)

### Tareas

#### 9.1 Two Factor Service (2h)
- [ ] Crear `services/twoFactorService.ts`
- [ ] Metodos: enable, confirm, disable, getRecoveryCodes
- [ ] Tipar respuestas

#### 9.2 Configuracion 2FA en Settings (6h)
- [ ] Crear seccion 2FA en `user-account-settings.vue`
- [ ] Estado: habilitado/deshabilitado
- [ ] Boton habilitar que muestra QR
- [ ] Modal con QR code para escanear
- [ ] Input para confirmar codigo
- [ ] Mostrar recovery codes
- [ ] Boton para regenerar recovery codes
- [ ] Boton para deshabilitar 2FA

#### 9.3 Vista de Challenge 2FA (4h)
- [ ] Crear `views/auth/two-factor-challenge.vue`
- [ ] Input para codigo de 6 digitos
- [ ] Link para usar recovery code
- [ ] Input alternativo para recovery code
- [ ] Manejo de errores
- [ ] Auto-submit cuando se ingresan 6 digitos

#### 9.4 Actualizar Auth Flow (4h)
- [ ] Actualizar authService para detectar two_factor_required
- [ ] Actualizar auth store para manejar estado 2FA pending
- [ ] Actualizar login flow para redirect a challenge
- [ ] Actualizar router guards

### Archivos Afectados

**Nuevos:**
```
resources/js/src/services/twoFactorService.ts
resources/js/src/views/auth/two-factor-challenge.vue
```

**Modificados:**
```
resources/js/src/views/users/user-account-settings.vue
resources/js/src/services/authService.ts
resources/js/src/stores/auth.ts
resources/js/src/router/index.ts
```

### Criterios de Aceptacion
- [ ] Usuario puede habilitar 2FA con QR
- [ ] Login muestra challenge cuando 2FA activo
- [ ] Recovery codes funcionan
- [ ] Usuario puede deshabilitar 2FA

### Dependencias para Siguiente Fase
- 2FA funcionando end-to-end

---

## FASE 10: Mejoras de UX y Optimizaciones

### Objetivo
Mejorar la experiencia de usuario y optimizar rendimiento.

### Prerequisitos Backend
- Fases 1-9 completadas

### Tareas

#### 10.1 Loading States (3h)
- [ ] Agregar skeletons a listas mientras cargan
- [ ] Agregar spinners a botones durante submit
- [ ] Implementar optimistic updates donde apropiado
- [ ] Agregar shimmer effects a cards

#### 10.2 Empty States (2h)
- [ ] Disenar empty state para lista de usuarios vacia
- [ ] Empty state para busqueda sin resultados
- [ ] Ilustraciones o iconos apropiados

#### 10.3 Responsive Design (3h)
- [ ] Revisar vistas admin en mobile
- [ ] Ajustar tabla de usuarios para mobile (cards o scroll)
- [ ] Revisar formularios en pantallas pequenas
- [ ] Testing en diferentes viewports

#### 10.4 Accessibilidad (2h)
- [ ] Agregar aria-labels donde falten
- [ ] Verificar contraste de colores
- [ ] Keyboard navigation en modales
- [ ] Focus management correcto

#### 10.5 Performance (2h)
- [ ] Lazy loading de componentes pesados
- [ ] Debounce en busquedas
- [ ] Memoization donde apropiado
- [ ] Revisar bundle size

### Archivos Afectados

**Modificados:**
```
Multiples archivos de vistas
resources/js/src/assets/css/*.css
```

### Criterios de Aceptacion
- [ ] No hay estados de carga "vacios"
- [ ] Funciona bien en mobile
- [ ] Lighthouse accessibility > 90
- [ ] Bundle size razonable

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
