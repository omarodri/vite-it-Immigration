# Análisis del Prototipo VITE-IT Immigration System

**Documento:** Análisis de Interfaces, Componentes y Entidades
**Fuente:** Prototipo Axure - https://dev.vite-it.com/
**Fecha de Análisis:** 2026-02-07
**Versión del Prototipo:** 1.1.0
**Analista:** Mary (Business Analyst Agent)

---

## 1. Resumen Ejecutivo

El prototipo VITE-IT representa un **Sistema de Gestión de Casos de Inmigración** diseñado para firmas de consultoría migratoria en Canadá. El sistema implementa una arquitectura de **dos portales**:

1. **Portal Staff/Administración** - Para consultores y personal administrativo
2. **Portal Cliente** - Para solicitantes de servicios migratorios

El enfoque principal del sistema está en la gestión de casos de **asilo, refugio y procesos de residencia**, con herramientas especializadas para el cálculo de plazos legales y seguimiento de comunicaciones.

---

## 2. Arquitectura de Pantallas

### 2.1 Mapa de Navegación

```
VITE-IT Immigration System
│
├── 📁 Portal Staff
│   ├── 1. Acceso a usuarios (Login)
│   ├── 2. Panel de Control (Dashboard)
│   ├── 3. Clientes
│   │   ├── 3.1 Perfil Cliente
│   │   ├── 3.2 Editar Cliente
│   │   └── 3.3 Agregar Cliente
│   ├── 4. Expedientes
│   │   ├── 4.1 Expedientes Usuario
│   │   ├── 4.2 Agregar Expediente (Wizard)
│   │   │   └── 4.2.1 Consultar Nuevo Expediente
│   │   ├── 4.3 Consultar Expediente
│   │   └── 4.4 Modificar Expediente
│   ├── 5. Agenda
│   ├── 6. Seguimiento
│   ├── 7. Tareas
│   └── 📁 Herramientas
│       ├── 8. Calculadora de Plazos
│       └── 9. Reporte de Tiempos
│
└── 📁 Portal Cliente
    ├── 1. Acceso a usuarios (Login)
    ├── 2. Listado de Tareas
    ├── 3. Tarea 1 - Formulario
    └── 4. Tarea 2 - Contrato
```

### 2.2 Inventario de Pantallas

#### Portal Staff (13 pantallas)

| ID | Pantalla | Archivo | Descripción |
|----|----------|---------|-------------|
| S1 | Login Staff | `1__acceso_a_usuarios.html` | Autenticación de empleados |
| S2 | Dashboard | `2__panel_de_control.html` | Panel de control con KPIs y accesos rápidos |
| S3 | Lista Clientes | `3__clientes.html` | Listado y gestión de clientes |
| S3.1 | Perfil Cliente | `3_1_perfil_cliente.html` | Vista detallada del cliente |
| S3.2 | Editar Cliente | `3_2__editar_cliente.html` | Formulario de edición de cliente |
| S3.3 | Agregar Cliente | `3_3__agregar_cliente.html` | Formulario de alta de cliente |
| S4 | Lista Expedientes | `4__expedientes.html` | Listado y gestión de casos |
| S4.1 | Expedientes Usuario | `4_1_expedientes_usuario.html` | Casos de un cliente específico |
| S4.2 | Agregar Expediente | `4_2__agregar_expediente.html` | Wizard de creación de caso |
| S4.3 | Consultar Expediente | `4_3__consultar_expediente.html` | Vista detallada del caso |
| S4.4 | Modificar Expediente | `4_4__modificarexpediente.html` | Formulario de edición de caso |
| S5 | Agenda | `5__agenda.html` | Calendario de eventos |
| S6 | Seguimiento | `6__seguimiento.html` | Log de comunicaciones |
| S7 | Tareas | `7__tareas.html` | Gestión de tareas |
| S8 | Calculadora Plazos | `8__calculadora_de_plazos.html` | Herramienta de cálculo de fechas |
| S9 | Reporte Tiempos | `9__reporte_de_tiempos.html` | Tracking de horas trabajadas |

#### Portal Cliente (4 pantallas)

| ID | Pantalla | Archivo | Descripción |
|----|----------|---------|-------------|
| C1 | Login Cliente | `1__acceso_a_usuarios_1.html` | Autenticación de clientes |
| C2 | Lista Tareas | `2__listado_de_tareas.html` | Tareas pendientes del cliente |
| C3 | Formulario | `3__tarea_1_-_formulario.html` | Completar formulario asignado |
| C4 | Contrato | `4__tarea_2_-_contrato.html` | Revisar y firmar contrato |

---

## 3. Modelo de Entidades

### 3.1 Diagrama de Entidades

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│     Usuario     │       │     Cliente     │       │   Expediente    │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │       │ id              │
│ nombre          │       │ nombres         │       │ numero          │
│ email           │       │ apellidos       │       │ tipo            │
│ password        │       │ fecha_nacimiento│◄──────│ cliente_id      │
│ rol             │       │ genero          │       │ responsable_id  │
│ avatar          │       │ pasaporte       │       │ estado          │
│ activo          │       │ nacionalidad    │       │ prioridad       │
└────────┬────────┘       │ profesion       │       │ progreso        │
         │                │ estado_civil    │       │ idioma          │
         │                │ idioma          │       │ fecha_creacion  │
         │                │ email           │       │ fecha_audiencia │
         │                │ telefono        │       │ fecha_cierre    │
         │                │ direccion       │       │ comentario      │
         │                │ estatus_canada  │       └────────┬────────┘
         │                │ punto_acceso    │                │
         │                │ fecha_llegada   │                │
         │                │ iuc             │                │
         │                │ permiso_trabajo │                │
         │                │ usuario_id      │                │
         │                │ activo          │                │
         │                └────────┬────────┘                │
         │                         │                         │
         │                         ▼                         │
         │                ┌─────────────────┐                │
         │                │   Acompañante   │                │
         │                ├─────────────────┤                │
         │                │ id              │                │
         │                │ cliente_id      │                │
         │                │ nombres         │                │
         │                │ apellidos       │                │
         │                │ relacion        │                │
         │                │ fecha_nacimiento│                │
         │                └─────────────────┘                │
         │                                                   │
         ▼                                                   ▼
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│     Evento      │       │     Tarea       │       │   Documento     │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │       │ id              │
│ titulo          │       │ asunto          │       │ expediente_id   │
│ fecha_inicio    │       │ expediente_id   │       │ nombre          │
│ fecha_fin       │       │ solicitante_id  │       │ tipo            │
│ participantes   │       │ responsable_id  │       │ categoria       │
│ categoria       │       │ tipo            │       │ fecha_carga     │
│ creado_por      │       │ prioridad       │       │ ruta            │
└─────────────────┘       │ estado          │       └─────────────────┘
                          │ fecha_creacion  │
                          │ fecha_limite    │                │
                          │ tiempo_horas    │                │
                          └─────────────────┘                │
                                                             │
┌─────────────────┐       ┌─────────────────┐       ┌────────▼────────┐
│  Seguimiento    │       │ Estado Cuenta   │       │ Credencial IRCC │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │       │ id              │
│ cliente_id      │       │ expediente_id   │       │ expediente_id   │
│ expediente_id   │       │ monto_facturado │       │ email           │
│ responsable_id  │       │ monto_pagado    │       │ pregunta_seg    │
│ canal           │       │ saldo           │       │ respuesta_seg   │
│ fecha           │       │ fecha           │       │ numero_solicitud│
│ duracion        │       └─────────────────┘       └─────────────────┘
│ comentario      │
│ tipo            │
└─────────────────┘
```

### 3.2 Definición Detallada de Entidades

#### 3.2.1 Usuario (User)

Representa a los usuarios del sistema, tanto staff como clientes.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| nombre | String | Sí | Nombre completo |
| email | String | Sí | Correo electrónico (único) |
| password | String | Sí | Contraseña encriptada |
| rol | Enum | Sí | admin, staff, cliente |
| avatar | String | No | URL o iniciales |
| activo | Boolean | Sí | Estado de la cuenta |
| created_at | DateTime | Sí | Fecha de creación |
| updated_at | DateTime | Sí | Última modificación |

#### 3.2.2 Cliente (Client)

Información completa del solicitante de servicios migratorios.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| nombres | String | Sí | Nombres del cliente |
| apellidos | String | Sí | Apellidos del cliente |
| fecha_nacimiento | Date | Sí | Fecha de nacimiento |
| genero | Enum | Sí | masculino, femenino, otro |
| pasaporte | String | Sí | Número de pasaporte |
| nacionalidad | String | Sí | País de nacionalidad |
| otra_nacionalidad | String | No | Segunda nacionalidad |
| profesion | String | No | Profesión u ocupación |
| estado_civil | Enum | Sí | soltero, casado, viudo, separado, union_libre |
| idioma | Enum | Sí | español, frances, ingles, otro |
| otro_idioma | String | No | Especificar si es otro |
| email | String | Sí | Correo electrónico |
| telefono | String | Sí | Teléfono principal |
| telefono_secundario | String | No | Teléfono alternativo |
| direccion_residencial | String | No | Dirección de residencia |
| direccion_correspondencia | String | No | Dirección para correo |
| ciudad | String | No | Ciudad |
| provincia | String | No | Provincia/Estado |
| pais | String | No | País de residencia |
| estatus_canada | Enum | Sí | solicitante_asilo, refugiado, residente_temporal, residente_permanente |
| punto_acceso | Enum | No | aeropuerto, frontera_terrestre, corredor_verde |
| fecha_llegada_canada | Date | No | Fecha de llegada a Canadá |
| iuc | String | No | Identificador único cliente IRCC |
| permiso_trabajo | Date | No | Fecha vencimiento permiso trabajo |
| usuario_id | FK | No | Referencia a cuenta de usuario |
| solicitante_principal | Boolean | Sí | Si es el aplicante principal |
| activo | Boolean | Sí | Cliente activo |
| activo_desde | Date | No | Fecha de inicio como cliente |
| created_at | DateTime | Sí | Fecha de creación |
| updated_at | DateTime | Sí | Última modificación |

#### 3.2.3 Acompañante (Companion)

Dependientes o familiares asociados al cliente principal.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| cliente_id | FK | Sí | Cliente principal |
| nombres | String | Sí | Nombres |
| apellidos | String | Sí | Apellidos |
| relacion | Enum | Sí | conyuge, hijo, padre, otro |
| fecha_nacimiento | Date | No | Fecha de nacimiento |
| pasaporte | String | No | Número de pasaporte |
| nacionalidad | String | No | Nacionalidad |

#### 3.2.4 Conflicto de Interés (Conflict of Interest)

Registro de posibles conflictos con otros clientes.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| cliente_id | FK | Sí | Cliente que registra |
| persona_nombre | String | Sí | Nombre de la persona |
| persona_apellido | String | Sí | Apellido de la persona |
| relacion | String | No | Tipo de relación/conflicto |
| notas | Text | No | Observaciones |

#### 3.2.5 Expediente (Case/File)

Caso migratorio de un cliente.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| numero | String | Sí | Número de expediente (ej: AS0004) |
| tipo | Enum | Sí | Ver tabla de tipos |
| cliente_id | FK | Sí | Cliente asociado |
| responsable_id | FK | Sí | Usuario responsable |
| estado | Enum | Sí | activo, inactivo, papelera, archivado, cerrado |
| prioridad | Enum | Sí | urgente, alta, media, baja |
| progreso | Int | No | Porcentaje 0-100 |
| idioma | Enum | Sí | español, frances, ingles |
| fecha_creacion | Date | Sí | Fecha de creación |
| fecha_hoja_marron | Date | No | Fecha hoja marrón (asilo) |
| fecha_audiencia | Date | No | Fecha de audiencia |
| plazo_pruebas | Date | No | Plazo envío documentos de prueba |
| plazo_fda | Date | No | Plazo depósito FDA |
| fecha_cierre | Date | No | Fecha de cierre del caso |
| numero_caja | String | No | Número de caja física |
| comentario | Text | No | Observaciones |
| created_at | DateTime | Sí | Fecha de creación |
| updated_at | DateTime | Sí | Última modificación |

**Tipos de Expediente:**

| Categoría | Tipos |
|-----------|-------|
| Residencia Temporal | turista, estudiante, trabajo, emit |
| Residencia Permanente | express_entry, arrima, peq, programa_piloto, trabajador_calificado |
| Humanitario | refugio_asilo, demanda_asilo, apelacion, corte_federal, erar, patrocinio |

#### 3.2.6 Documento (Document)

Archivos asociados a expedientes.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| expediente_id | FK | Sí | Expediente asociado |
| nombre | String | Sí | Nombre del archivo |
| tipo | String | No | Tipo MIME |
| categoria | Enum | Sí | admision, historia, pruebas, audiencia, contrato |
| ruta | String | Sí | Ruta del archivo |
| fecha_carga | DateTime | Sí | Fecha de subida |
| cargado_por | FK | Sí | Usuario que subió |

#### 3.2.7 Formulario Expediente (Case Form)

Formularios IRCC asociados a un expediente.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| expediente_id | FK | Sí | Expediente asociado |
| formulario_id | FK | Sí | Tipo de formulario |
| estado | Enum | Sí | pendiente, en_proceso, completado |
| fecha_limite | Date | No | Fecha límite |

#### 3.2.8 Tarea (Task)

Actividades asignadas a usuarios.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| asunto | String | Sí | Descripción de la tarea |
| expediente_id | FK | No | Expediente asociado |
| solicitante_id | FK | Sí | Usuario que solicita |
| responsable_id | FK | No | Usuario asignado |
| tipo | Enum | Sí | traduccion, creacion_expediente, contabilidad, archivo, otro |
| prioridad | Enum | Sí | urgente, alta, media, baja |
| estado | Enum | Sí | nuevo, asignado, en_proceso, rechazado, resuelto, cerrado |
| fecha_creacion | DateTime | Sí | Fecha de creación |
| fecha_actualizacion | DateTime | No | Última actualización |
| fecha_limite | DateTime | No | Fecha límite |
| tiempo_estimado | Decimal | No | Horas estimadas |
| tiempo_real | Decimal | No | Horas reales |

#### 3.2.9 Evento (Event)

Citas y reuniones en el calendario.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| titulo | String | Sí | Título del evento |
| fecha_inicio | DateTime | Sí | Inicio del evento |
| fecha_fin | DateTime | Sí | Fin del evento |
| categoria | String | No | Categoría (general, audiencia, etc.) |
| creado_por | FK | Sí | Usuario creador |
| cliente_id | FK | No | Cliente asociado |
| expediente_id | FK | No | Expediente asociado |
| notas | Text | No | Notas adicionales |

#### 3.2.10 Participante Evento (Event Participant)

Relación muchos a muchos entre eventos y usuarios.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| evento_id | FK | Sí | Evento |
| usuario_id | FK | Sí | Usuario participante |
| confirmado | Boolean | No | Si confirmó asistencia |

#### 3.2.11 Seguimiento (Follow-up)

Registro de comunicaciones con clientes.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| cliente_id | FK | Sí | Cliente asociado |
| expediente_id | FK | No | Expediente asociado |
| responsable_id | FK | Sí | Usuario que registra |
| canal | Enum | Sí | llamada, correo, cita_reunion |
| tipo | Enum | Sí | tarea, seguimiento |
| fecha | DateTime | Sí | Fecha y hora |
| duracion_horas | Decimal | No | Duración en horas |
| comentario | Text | No | Descripción |
| categoria | String | No | Categoría (ej: Delai FDA) |

#### 3.2.12 Estado de Cuenta (Account Statement)

Facturación por expediente.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| expediente_id | FK | Sí | Expediente asociado |
| concepto | String | Sí | Descripción del cargo |
| monto | Decimal | Sí | Monto en CAD |
| fecha | Date | Sí | Fecha del movimiento |
| tipo | Enum | Sí | factura, pago, ajuste |

#### 3.2.13 Credencial IRCC (IRCC Credential)

Almacenamiento de credenciales de acceso a IRCC del cliente.

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| id | UUID/Int | Sí | Identificador único |
| expediente_id | FK | Sí | Expediente asociado |
| email_cuenta | String | No | Email de la cuenta IRCC |
| pregunta_seguridad | String | No | Pregunta de seguridad |
| respuesta_seguridad | String | No | Respuesta (encriptada) |
| numero_solicitud | String | No | Número de solicitud IRCC |

---

## 4. Componentes de Interfaz

### 4.1 Componentes de Navegación

#### Sidebar Principal (Staff)

```
┌────────────────────────────────┐
│  🏠 Inicio                     │
│  👥 Clientes                   │
│  📁 Expedientes                │
│  📅 Agenda                     │
│  ✅ Tareas                     │
│  📞 Seguimiento                │
│  🔧 Herramientas ▼             │
│     ├── Plantillas             │
│     ├── IRCC                   │
│     ├── CISR                   │
│     ├── Médico designado       │
│     ├── Calculadora de plazos  │
│     └── Reporte de tiempos     │
│  ⚙️ Panel Admin ▼              │
│     ├── Formularios            │
│     ├── Respuestas rápidas     │
│     ├── Plantillas documentos  │
│     ├── Plantilla de Correos   │
│     ├── Parámetros config      │
│     ├── Idiomas                │
│     └── Gestión de usuarios    │
└────────────────────────────────┘
```

#### Header Usuario

```
┌─────────────────────────────────────────────────────────┐
│                                    [CR] Carlos Ruiz ▼  │
│                                    ├── Mi perfil       │
│                                    └── Salir           │
└─────────────────────────────────────────────────────────┘
```

### 4.2 Componentes de Formulario

#### Campos de Texto

| Componente | Variantes | Uso |
|------------|-----------|-----|
| TextInput | simple, con icono, con validación | Campos de texto general |
| EmailInput | con validación email | Correos electrónicos |
| PasswordInput | con toggle visibilidad | Contraseñas |
| PhoneInput | con máscara | Números telefónicos |
| TextArea | simple, con contador | Comentarios, descripciones |

#### Campos de Selección

| Componente | Variantes | Uso |
|------------|-----------|-----|
| Select/Dropdown | simple, con búsqueda | Listas de opciones |
| MultiSelect | con chips | Selección múltiple |
| DatePicker | simple, rango | Fechas |
| DateTimePicker | con hora | Fechas y horas |
| Toggle/Switch | on/off | Booleanos |
| Checkbox | simple, grupo | Opciones múltiples |
| RadioGroup | vertical, horizontal | Opción única |

#### Campos Especiales

| Componente | Uso |
|------------|-----|
| FileUpload | Carga de documentos |
| AvatarPicker | Selección de imagen de perfil |
| CurrencyInput | Montos en CAD |
| PercentageBar | Progreso del expediente |

### 4.3 Componentes de Datos

#### Cards

| Componente | Contenido | Acciones |
|------------|-----------|----------|
| ClientCard | Avatar, nombre, país, contacto, tabs | Editar, Archivar, Papelera |
| CaseCard | ID, tipo, cliente, progreso, responsable, prioridad, fechas | Consultar, Editar, Archivar, Borrar |
| TaskCard | ID, asunto, responsable, prioridad, estado, tiempo | Editar, Ver |
| EventCard | Hora, título, participantes | Editar, Eliminar |

#### Tablas

| Componente | Características |
|------------|-----------------|
| DataTable | Columnas configurables, ordenamiento, paginación |
| Pagination | Anterior, números, Siguiente |
| FilterBar | Dropdowns de filtros, búsqueda |

#### Indicadores

| Componente | Representación |
|------------|----------------|
| ProgressBar | Barra horizontal 0-100% |
| StatusBadge | Colores por estado (verde=activo, gris=cerrado) |
| PriorityBadge | Colores por nivel (rojo=urgente, naranja=alta) |
| AvatarInitials | Círculo con iniciales del nombre |

### 4.4 Componentes de Acción

#### Botones

| Tipo | Uso |
|------|-----|
| Primary | Acción principal (Guardar, Crear) |
| Secondary | Acción secundaria (Cancelar, Regresar) |
| Danger | Acciones destructivas (Borrar) |
| Icon Button | Acciones en filas de tabla |
| Dropdown Button | Múltiples acciones (Guardar ▼) |

#### Grupos de Acciones

```
[ Guardar ] [ Guardar y cerrar ] [ Cancelar ]
```

```
[ Regresar ] [ Continuar ]
```

```
[ Anterior ] [1] [2] [3] [ Siguiente ]
```

---

## 5. Funcionalidades del Sistema

### 5.1 Módulo de Autenticación

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Login Staff | Autenticación de empleados | S1 |
| Login Cliente | Autenticación de clientes | C1 |
| Recordar sesión | Checkbox "Recordarme" | S1, C1 |
| Recuperar contraseña | Link "Olvidé mi contraseña" | S1, C1 |
| Cambio forzado contraseña | Toggle en perfil cliente | S3.2 |

### 5.2 Módulo de Clientes

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Listar clientes | Vista de tarjetas con filtros | S3 |
| Filtrar por estado | Activo, Inactivo, Papelera | S3 |
| Buscar cliente | Por nombre, email, país | S3 |
| Ver perfil | Información completa del cliente | S3.1 |
| Ver timeline | Historial de actividades | S3.1 |
| Ver expedientes | Casos asociados al cliente | S3.1 |
| Editar cliente | Modificar todos los campos | S3.2 |
| Agregar cliente | Alta de nuevo cliente | S3.3 |
| Gestionar acompañantes | Agregar/editar dependientes | S3.2, S3.3 |
| Registrar conflictos | Conflictos de interés | S3.2, S3.3 |
| Crear acceso portal | Credenciales para cliente | S3.2, S3.3 |
| Archivar cliente | Cambiar a inactivo | S3 |
| Enviar a papelera | Eliminación lógica | S3 |

### 5.3 Módulo de Expedientes

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Listar expedientes | Vista de tarjetas con filtros | S4 |
| Filtrar múltiple | Estado, tipo, prioridad, responsable | S4 |
| Ordenar | Por fecha creación, modificación | S4 |
| Ver expedientes cliente | Casos de un cliente específico | S4.1 |
| Crear expediente (Wizard) | Proceso guiado de 6 pasos | S4.2 |
| - Paso 1: Tipo | Seleccionar categoría de caso | S4.2 |
| - Paso 2: Calculadora | Calcular fechas automáticamente | S4.2 |
| - Paso 3: Conflictos | Verificar conflictos de interés | S4.2 |
| - Paso 4: Cliente | Seleccionar o crear cliente | S4.2 |
| - Paso 5: Configuración | Responsable, prioridad, formularios | S4.2 |
| - Paso 6: Validación | Revisar y generar | S4.2 |
| Consultar expediente | Vista detallada con tabs | S4.3 |
| - Tab Estructura | Organización documental | S4.3 |
| - Tab Historia | Timeline del caso | S4.3 |
| - Tab Documentos | Archivos adjuntos | S4.3 |
| - Tab Tareas | Tareas del expediente | S4.3 |
| - Tab Estado Cuenta | Facturación y pagos | S4.3 |
| - Tab Info IRCC | Credenciales almacenadas | S4.3 |
| Modificar expediente | Editar campos del caso | S4.4 |
| Gestionar documentos | Subir, descargar, categorizar | S4.3 |
| Archivar expediente | Cambiar a archivado | S4 |
| Cerrar expediente | Marcar como cerrado | S4.4 |

### 5.4 Módulo de Agenda

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Ver calendario | Lista de eventos por fecha | S5 |
| Filtrar por fecha | Rango de fechas inicial/final | S5 |
| Filtrar por usuario | Ver agenda de colaborador | S5 |
| Crear evento | Título, fecha, participantes | S5 |
| Editar evento | Modificar datos del evento | S5 |
| Eliminar evento | Borrar evento | S5 |
| Ver participantes | Lista de asistentes | S5 |

### 5.5 Módulo de Tareas

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Listar tareas | Tabla con columnas configurables | S7 |
| Filtrar por responsable | Todos, Asignados a mí, Persona | S7 |
| Filtrar por tipo | Traducción, Contabilidad, etc. | S7 |
| Filtrar por prioridad | Urgente, Alta, Media, Baja | S7 |
| Filtrar por estado | Nuevo, Asignado, Resuelto, etc. | S7 |
| Crear tarea | Nueva tarea con asignación | S7 |
| Asignar tarea | Asignar a responsable | S7 |
| Cambiar estado | Workflow de estados | S7 |
| Registrar tiempo | Horas trabajadas | S7 |

### 5.6 Módulo de Seguimiento

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Listar seguimientos | Log de comunicaciones | S6 |
| Filtrar por responsable | Por usuario | S6 |
| Filtrar por canal | Llamada, Correo, Reunión | S6 |
| Filtrar por categoría | Tipos de seguimiento | S6 |
| Filtrar por fecha | Rango de fechas | S6 |
| Crear seguimiento | Registrar comunicación | S6 |
| Registrar duración | Horas de la interacción | S6 |

### 5.7 Herramientas

#### Calculadora de Plazos

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Seleccionar tipo | Asilo, Apelación, Control Judicial | S8 |
| Ingresar fecha base | Fecha de audiencia/decisión | S8 |
| Calcular fechas | Generación automática de plazos | S8 |

**Fórmulas de Cálculo:**

| Tipo Proceso | Fecha Base | Cálculo |
|--------------|------------|---------|
| Asilo | Fecha Hoja Marrón | +15 días = Plazo FDA |
| Asilo | Fecha Hoja Marrón | -10 días = Plazo Pruebas |
| Apelación | Fecha Decisión | +7 días = Reconocimiento |
| Apelación | Fecha Decisión | +15 días = Aviso Apelación |
| Apelación | Aviso Apelación | +15 días = Argumentos |
| Control Judicial | Fecha Radicado | +30 días = Argumentos |
| Control Judicial | Fecha Decisión | +7 días = Reconocimiento |
| Control Judicial | Reconocimiento | +15 días = Depósito |

#### Reporte de Tiempos

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Filtrar por responsable | Por usuario | S9 |
| Filtrar por canal | Tipo de comunicación | S9 |
| Filtrar por tipo | Tarea o Seguimiento | S9 |
| Filtrar por expediente | Por caso específico | S9 |
| Filtrar por fecha | Rango de fechas | S9 |
| Ver resumen | Total de horas | S9 |
| Ver detalle | Listado de entradas | S9 |

### 5.8 Portal Cliente

| Funcionalidad | Descripción | Pantalla |
|---------------|-------------|----------|
| Ver tareas pendientes | Lista de tareas asignadas | C2 |
| Ver estado tarea | Nuevo, En proceso, etc. | C2 |
| Ver prioridad | Urgente, Alta, etc. | C2 |
| Completar formulario | Llenar información solicitada | C3 |
| Revisar contrato | Descargar documento | C4 |
| Firmar contrato | Subir PDF firmado | C4 |
| Enviar tarea | Marcar como completada | C3, C4 |

---

## 6. Filtros y Estados

### 6.1 Estados de Cliente

| Estado | Descripción | Color |
|--------|-------------|-------|
| Activo | Cliente activo en el sistema | Verde |
| Inactivo | Cliente desactivado | Gris |
| Papelera | Eliminación lógica | Rojo |

### 6.2 Estados de Expediente

| Estado | Descripción | Color |
|--------|-------------|-------|
| Activo | Caso en proceso | Verde |
| Inactivo | Caso pausado | Amarillo |
| Papelera | Eliminación lógica | Rojo |
| Archivado | Caso almacenado | Gris |
| Cerrado | Caso finalizado | Azul |

### 6.3 Estados de Tarea

| Estado | Descripción | Transiciones |
|--------|-------------|--------------|
| Nuevo | Recién creada | → Asignado |
| Asignado | Con responsable | → En proceso, Rechazado |
| En proceso | En ejecución | → Resuelto |
| Rechazado | Devuelta | → Asignado |
| Resuelto | Completada | → Cerrado |
| Cerrado | Finalizada | (Estado final) |

### 6.4 Niveles de Prioridad

| Prioridad | Color | Orden |
|-----------|-------|-------|
| Urgente | Rojo | 1 |
| Alta | Naranja | 2 |
| Media | Amarillo | 3 |
| Baja | Verde | 4 |

### 6.5 Tipos de Expediente

#### Residencia Temporal
- Turista
- Estudiante
- Trabajo
- EMIT

#### Residencia Permanente
- Express Entry
- ARRIMA
- PEQ (Programa de Experiencia Quebequense)
- Programas Pilotos
- Trabajador Calificado

#### Humanitario
- Refugio/Asilo
- Demanda de Asilo
- Apelación
- Corte Federal
- ERAR
- Patrocinio

### 6.6 Canales de Comunicación

| Canal | Descripción |
|-------|-------------|
| Llamada | Llamada telefónica al cliente |
| Correo | Email del/hacia el cliente |
| Cita/Reunión | Reunión presencial o virtual |

### 6.7 Tipos de Tarea

| Tipo | Descripción |
|------|-------------|
| Traducción | Traducir documentos |
| Creación de Expediente | Abrir nuevo caso |
| Contabilidad | Tareas financieras |
| Archivo | Gestión documental |
| Otro | Otras tareas |

---

## 7. Roles y Permisos (Inferidos)

### 7.1 Matriz de Permisos

| Módulo | Administrador | Staff | Cliente |
|--------|---------------|-------|---------|
| Dashboard | ✅ Completo | ✅ Completo | ❌ |
| Clientes | ✅ CRUD | ✅ CRUD | ❌ |
| Expedientes | ✅ CRUD | ✅ CRUD | ❌ |
| Agenda | ✅ CRUD | ✅ CRUD | ❌ |
| Tareas | ✅ CRUD | ✅ CRUD | ✅ Solo lectura + completar |
| Seguimiento | ✅ CRUD | ✅ CRUD | ❌ |
| Herramientas | ✅ Todas | ✅ Todas | ❌ |
| Panel Admin | ✅ Completo | ❌ | ❌ |
| Portal Cliente | ❌ | ❌ | ✅ Completo |

### 7.2 Funciones del Panel Admin

| Función | Descripción |
|---------|-------------|
| Formularios | Gestión de formularios IRCC |
| Respuestas rápidas | Textos predefinidos |
| Plantillas documentos | Templates de contratos, cartas |
| Plantilla de Correos | Templates de emails |
| Parámetros configuración | Ajustes del sistema |
| Idiomas | Configuración multilingüe |
| Gestión de usuarios | Alta/baja/edición de usuarios |

---

## 8. Especificaciones Técnicas

### 8.1 Información del Sistema

| Atributo | Valor |
|----------|-------|
| Nombre | VITE - Immigration System Solutions |
| Versión | 1.1.0 |
| Idioma UI | Español |
| Moneda | CAD (Dólares Canadienses) |
| Framework Prototipo | Axure |

### 8.2 Consideraciones de Implementación

#### Campos Sensibles (requieren encriptación)
- Contraseñas de usuarios
- Respuestas de seguridad IRCC
- Números de pasaporte
- IUC (Identificador Único Cliente)

#### Campos con Formato Especial
- Teléfonos: Formato internacional (+1 XXX XXX XXXX)
- Fechas: Formato ISO (YYYY-MM-DD)
- Moneda: 2 decimales, símbolo CAD

#### Validaciones Críticas
- Email único por usuario
- Número de expediente único
- Fechas de plazos coherentes
- Conflictos de interés antes de crear expediente

---

## 9. Anexos

### 9.1 Glosario de Términos

| Término | Descripción |
|---------|-------------|
| IRCC | Immigration, Refugees and Citizenship Canada |
| CISR | Comisión de Inmigración y Estatus de Refugiado |
| FDA | Fondement de la Demande d'Asile (Fundamento de Solicitud de Asilo) |
| IUC | Identificador Único de Cliente |
| ARRIMA | Sistema de declaración de interés de Quebec |
| PEQ | Programa de Experiencia Quebequense |
| Express Entry | Sistema federal de inmigración económica |
| ERAR | Examen de Riesgo Antes de Remoción |
| Hoja Marrón | Formulario de información personal para asilo |

### 9.2 Referencias de Pantallas

| ID Axure | Pantalla |
|----------|----------|
| u93qfc | 1. Acceso a usuarios (Staff) |
| 63k622 | 2. Panel de Control |
| 1e0fbf | 3. Clientes |
| 5mk2hh | 3.1 Perfil Cliente |
| jgg1iu | 3.2 Editar Cliente |
| ri6iyu | 3.3 Agregar Cliente |
| im56t8 | 4. Expedientes |
| 9g4q7l | 4.1 Expedientes Usuario |
| r9zqg9 | 4.2 Agregar Expediente |
| 6ps4ud | 4.2.1 Consultar nuevo Expediente |
| 7x5rju | 4.3 Consultar Expediente |
| rjbbfi | 4.4 Modificar Expediente |
| xhazsv | 5. Agenda |
| vmorra | 6. Seguimiento |
| n4mtg5 | 7. Tareas |
| lpzj53 | 8. Calculadora de plazos |
| 3hkg3p | 9. Reporte de tiempos |
| jb6qor | 1. Acceso a usuarios (Cliente) |
| lz70l8 | 2. Listado de tareas |
| w4ai7v | 3. Tarea 1 - Formulario |
| ozarh3 | 4. Tarea 2 - Contrato |

---

**Fin del Documento**

*Generado por Mary - Business Analyst Agent*
*BMAD Framework v6.0.0-Beta.7*
