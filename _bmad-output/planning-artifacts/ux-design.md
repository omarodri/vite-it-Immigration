# VITE-IT Immigration - UX Design Specification

**Version:** 1.0
**Date:** 2026-02-08
**Author:** UX Design Team
**Status:** Ready for Implementation

---

## Table of Contents

1. [Design System Foundation](#1-design-system-foundation)
2. [Navigation Architecture](#2-navigation-architecture)
3. [Screen Specifications by Module](#3-screen-specifications-by-module)
4. [Common Patterns](#4-common-patterns)
5. [Responsive Behavior](#5-responsive-behavior)
6. [Accessibility](#6-accessibility)
7. [Component Mapping Matrix](#7-component-mapping-matrix)

---

## 1. Design System Foundation

### 1.1 Color Palette

The design system leverages Tailwind CSS colors from `tailwind.config.cjs`:

| Color | HEX Value | Usage | CSS Class |
|-------|-----------|-------|-----------|
| **Primary** | `#4361ee` | Actions, links, active states | `text-primary`, `bg-primary`, `border-primary` |
| **Primary Light** | `#eaf1ff` | Subtle backgrounds | `bg-primary-light` |
| **Secondary** | `#805dca` | Alternative emphasis | `text-secondary`, `bg-secondary` |
| **Success** | `#00ab55` | Positive states, completions | `text-success`, `bg-success` |
| **Danger** | `#e7515a` | Errors, deletions, urgente | `text-danger`, `bg-danger` |
| **Warning** | `#e2a03f` | Cautions, alta priority | `text-warning`, `bg-warning` |
| **Info** | `#2196f3` | Informational, media priority | `text-info`, `bg-info` |
| **Dark** | `#3b3f5c` | Text, dark elements | `text-dark`, `bg-dark` |
| **Black** | `#0e1726` | Deep backgrounds (dark mode) | `bg-black` |

#### Status Color Mapping (per Prototype)

| Estado | Color | Class |
|--------|-------|-------|
| Activo | Green | `bg-success` / `text-success` |
| Inactivo | Yellow | `bg-warning` / `text-warning` |
| Papelera | Red | `bg-danger` / `text-danger` |
| Archivado | Gray | `bg-dark` / `text-dark` |
| Cerrado | Blue | `bg-info` / `text-info` |

#### Priority Color Mapping

| Prioridad | Color | Class |
|-----------|-------|-------|
| Urgente | Red | `bg-danger` / `text-danger` |
| Alta | Orange | `bg-warning` / `text-warning` |
| Media | Blue | `bg-info` / `text-info` |
| Baja | Green | `bg-success` / `text-success` |

### 1.2 Typography Scale

Font family: **Nunito** (defined in `tailwind.config.cjs`)

| Element | Size | Class | Usage |
|---------|------|-------|-------|
| H1 | 40px | `text-4xl` | Page titles |
| H2 | 32px | `text-3xl` | Section headers |
| H3 | 28px | `text-2xl` | Card titles |
| H4 | 24px | `text-xl` | Subsection headers |
| H5 | 20px | `text-lg` | Widget titles |
| H6 | 16px | `text-base` | Small headers |
| Body | 14px | `text-sm` | Default text |
| Caption | 12px | `text-xs` | Labels, timestamps |

### 1.3 Component Library Reference

The Vristo template provides pre-built components in these locations:

| Category | Path | Key Components |
|----------|------|----------------|
| **Layout** | `resources/js/src/components/layout/` | Header.vue, Sidebar.vue, Footer.vue |
| **Icons** | `resources/js/src/components/icon/` | 150+ SVG icon components |
| **Forms** | `resources/js/src/views/forms/` | Input patterns, validation, wizards |
| **DataTables** | `resources/js/src/views/datatables/` | Basic, advanced, filtering variants |
| **Components** | `resources/js/src/views/components/` | Tabs, modals, cards, timeline |
| **Elements** | `resources/js/src/views/elements/` | Buttons, badges, alerts, avatars |
| **Apps** | `resources/js/src/views/apps/` | Calendar, contacts, todolist patterns |

### 1.4 Icon Library

Icons are implemented as Vue components. Reference pattern from Sidebar.vue:

```vue
<icon-users class="group-hover:!text-primary shrink-0" />
<icon-lock-dots class="group-hover:!text-primary shrink-0" />
<icon-menu-dashboard class="group-hover:!text-primary shrink-0" />
```

**Key Icons for VITE-IT:**

| Purpose | Component | Usage |
|---------|-----------|-------|
| Clientes | `<icon-users>` | Menu, cards |
| Expedientes | `<icon-folder>` | Menu, cards |
| Tareas | `<icon-menu-todo>` | Menu, dashboard |
| Agenda | `<icon-menu-calendar>` | Menu, events |
| Seguimiento | `<icon-phone>` | Menu, timeline |
| Documentos | `<icon-file>` | Menu, lists |
| Search | `<icon-search>` | Filter bars |
| Add | `<icon-plus>` | Create buttons |
| Edit | `<icon-pencil>` | Action buttons |
| Delete | `<icon-trash>` | Action buttons |

---

## 2. Navigation Architecture

### 2.1 Sidebar Menu Structure

Based on prototype analysis, mapping to Vristo sidebar pattern from `Sidebar.vue`:

```
+----------------------------------+
|  [Logo] VITE-IT Immigration      |
+----------------------------------+
|  ADMINISTRACION (if admin)       |
|    > Usuarios                    |
|    > Roles y Permisos            |
+----------------------------------+
|  GESTION                         |
|    > Inicio (Dashboard)          |
|    > Clientes                    |
|    > Expedientes                 |
|    > Tareas                      |
|    > Seguimiento                 |
|    > Agenda                      |
+----------------------------------+
|  HERRAMIENTAS                    |
|    > Calculadora de Plazos       |
|    > Reporte de Tiempos          |
+----------------------------------+
|  CONFIGURACION (if admin)        |
|    > Formularios IRCC            |
|    > Plantillas Documentos       |
|    > Plantilla Correos           |
|    > Parametros                  |
+----------------------------------+
```

**Implementation Pattern** (from `Sidebar.vue`):

```vue
<li class="nav-item">
    <router-link to="/clientes" class="group" @click="toggleMobileMenu">
        <div class="flex items-center">
            <icon-users class="group-hover:!text-primary shrink-0" />
            <span class="ltr:pl-3 rtl:pr-3 text-black dark:text-[#506690] dark:group-hover:text-white-dark">
                {{ $t('sidebar.clientes') }}
            </span>
        </div>
    </router-link>
</li>
```

### 2.2 Breadcrumb Pattern

Standard pattern from template views:

```vue
<ul class="flex space-x-2 rtl:space-x-reverse">
    <li>
        <a href="javascript:;" class="text-primary hover:underline">Clientes</a>
    </li>
    <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
        <span>Perfil Cliente</span>
    </li>
</ul>
```

### 2.3 Header Actions

Reference `Header.vue` for user dropdown pattern:

```
+-------------------------------------------------------------------+
|  [Search]                    [Notifications] [User Avatar] [Name] |
|                                              +------------------+  |
|                                              | Mi Perfil        |  |
|                                              | Configuracion    |  |
|                                              | Cerrar Sesion    |  |
|                                              +------------------+  |
+-------------------------------------------------------------------+
```

---

## 3. Screen Specifications by Module

### 3.1 Dashboard (S2)

**Purpose:** Personalized home view showing daily priorities and case status.

**Reference File:** `resources/js/src/views/index.vue` (modify for CRM)

**Layout Grid:**

```
+-------------------------------------------------------------------+
|                         Dashboard                                  |
+-------------------------------------------------------------------+
|  [Tareas Hoy: 5] [Vencidas: 2] [Expedientes: 12] [Clientes: 45]   |
+-------------------------------------------------------------------+
|                                                                    |
|  +--------------------+  +--------------------+                    |
|  |  TAREAS URGENTES   |  |  CASOS RECIENTES   |                    |
|  |  ----------------  |  |  ----------------  |                    |
|  |  [ ] Llamar a...   |  |  EXP-2026-001 ...  |                    |
|  |  [ ] Enviar doc... |  |  EXP-2026-002 ...  |                    |
|  |  [ ] Preparar...   |  |  EXP-2026-003 ...  |                    |
|  +--------------------+  +--------------------+                    |
|                                                                    |
|  +-------------------------------------------------------------+  |
|  |  EVENTOS HOY                                                 |  |
|  |  09:00 - Cita con cliente Hassan                            |  |
|  |  14:00 - Audiencia caso Garcia                              |  |
|  +-------------------------------------------------------------+  |
|                                                                    |
+-------------------------------------------------------------------+
```

**Components:**

| Widget | Template Reference | Data |
|--------|-------------------|------|
| Stats Cards | `views/index.vue` (stat cards pattern) | Tasks due, overdue, cases, clients |
| Tasks Urgentes | `views/apps/todolist.vue` pattern | Top 5 urgent tasks |
| Casos Recientes | Card list pattern | Last 5 cases accessed |
| Eventos Hoy | Timeline pattern from `views/components/timeline.vue` | Today's calendar events |

**Stats Card Pattern:**

```html
<div class="panel bg-gradient-to-r from-primary to-primary-light">
    <div class="flex justify-between">
        <div class="text-3xl font-bold">5</div>
        <icon-menu-todo class="w-12 h-12 opacity-50" />
    </div>
    <div class="text-white-light mt-2">Tareas para Hoy</div>
</div>
```

---

### 3.2 Clientes Module (S3, S3.1, S3.2, S3.3)

#### 3.2.1 Lista de Clientes (S3)

**Reference File:** `resources/js/src/views/apps/contacts.vue`

**Layout:**

```
+-------------------------------------------------------------------+
|  Clientes                                 [+ Agregar Cliente]      |
+-------------------------------------------------------------------+
|  [Buscar...]  [Estado: Todos v]  [Grid] [Lista]                    |
+-------------------------------------------------------------------+
|                                                                    |
|  +----------------+  +----------------+  +----------------+        |
|  | [Avatar]       |  | [Avatar]       |  | [Avatar]       |        |
|  | Juan Garcia    |  | Maria Lopez    |  | Ahmed Hassan   |        |
|  | Colombia       |  | Venezuela      |  | Siria          |        |
|  | Activo         |  | Prospecto      |  | Activo         |        |
|  | 2 Expedientes  |  | 0 Expedientes  |  | 3 Expedientes  |        |
|  |                |  |                |  |                |        |
|  | [Ver] [Editar] |  | [Ver] [Editar] |  | [Ver] [Editar] |        |
|  +----------------+  +----------------+  +----------------+        |
|                                                                    |
+-------------------------------------------------------------------+
|  [< Anterior]  1  2  3  4  5  [Siguiente >]                        |
+-------------------------------------------------------------------+
```

**Card Component Structure:**

```html
<div class="bg-white dark:bg-[#1c232f] rounded-md overflow-hidden text-center shadow relative">
    <!-- Avatar Header -->
    <div class="bg-primary/10 p-6 pb-0">
        <div class="w-20 h-20 mx-auto rounded-full bg-primary text-white flex items-center justify-center text-2xl font-bold">
            {{ getInitials(cliente.nombre) }}
        </div>
    </div>

    <!-- Content -->
    <div class="px-6 py-4">
        <div class="text-xl font-semibold">{{ cliente.nombre }} {{ cliente.apellidos }}</div>
        <div class="text-white-dark">{{ cliente.nacionalidad }}</div>

        <!-- Status Badge -->
        <span class="badge" :class="getStatusClass(cliente.estado)">
            {{ cliente.estado }}
        </span>

        <!-- Stats -->
        <div class="flex justify-around mt-4 text-sm">
            <div>
                <div class="text-info font-bold">{{ cliente.expedientes_count }}</div>
                <div class="text-white-dark">Expedientes</div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-4 p-4 border-t border-white-light dark:border-[#191e3a]">
        <button class="btn btn-outline-primary w-1/2">Ver</button>
        <button class="btn btn-outline-info w-1/2">Editar</button>
    </div>
</div>
```

#### 3.2.2 Perfil Cliente (S3.1)

**Layout with Tabs:**

```
+-------------------------------------------------------------------+
|  < Clientes / Perfil Cliente                                       |
+-------------------------------------------------------------------+
|  +------------------+  +--------------------------------------+    |
|  | [Avatar Grande]  |  | Juan Garcia Perez                    |    |
|  | JG               |  | Colombia | Activo desde: 2024-01-15  |    |
|  |                  |  | juan@email.com | +1 555-0123         |    |
|  | [Editar Perfil]  |  +--------------------------------------+    |
|  +------------------+                                              |
+-------------------------------------------------------------------+
|  [Informacion] [Acompanantes] [Expedientes] [Timeline]             |
+-------------------------------------------------------------------+
|                                                                    |
|  Tab: Informacion                                                  |
|  +------------------------------+  +---------------------------+   |
|  | DATOS PERSONALES             |  | DATOS EN CANADA           |   |
|  | Nombre: Juan Garcia          |  | Estatus: Solicitante Asilo|   |
|  | Nacionalidad: Colombia       |  | Fecha Llegada: 2024-01-10 |   |
|  | Pasaporte: AB123456          |  | IUC: 12345678             |   |
|  | Profesion: Ingeniero         |  | Permiso Trabajo: 2025-01  |   |
|  +------------------------------+  +---------------------------+   |
|                                                                    |
|  Tab: Acompanantes                                                 |
|  +-------------------------------------------------------------+   |
|  | Nombre          | Relacion | Fecha Nac.  | Acciones         |   |
|  | Maria Garcia    | Esposa   | 1985-05-20  | [Editar][Borrar] |   |
|  | Pedro Garcia    | Hijo     | 2015-08-10  | [Editar][Borrar] |   |
|  +-------------------------------------------------------------+   |
|  | [+ Agregar Acompanante]                                      |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  Tab: Expedientes                                                  |
|  +-------------------+  +-------------------+                      |
|  | EXP-2026-001      |  | EXP-2026-002      |                      |
|  | Asilo             |  | Trabajo Familiar  |                      |
|  | Progreso: 65%     |  | Progreso: 30%     |                      |
|  | [======----]      |  | [===-------]      |                      |
|  +-------------------+  +-------------------+                      |
|                                                                    |
|  Tab: Timeline                                                     |
|  (ver patron de timeline.vue)                                      |
|                                                                    |
+-------------------------------------------------------------------+
```

**Tabs Implementation** (reference `views/components/tabs.vue`):

```vue
<TabGroup>
    <TabList class="flex flex-wrap border-b border-white-light dark:border-[#191e3a]">
        <Tab v-slot="{ selected }">
            <a :class="{ 'text-primary border-b-primary': selected }">
                Informacion
            </a>
        </Tab>
        <Tab v-slot="{ selected }">
            <a :class="{ 'text-primary border-b-primary': selected }">
                Acompanantes
            </a>
        </Tab>
        <!-- ... more tabs -->
    </TabList>
    <TabPanels>
        <TabPanel><!-- Content --></TabPanel>
    </TabPanels>
</TabGroup>
```

#### 3.2.3 Formulario Cliente (S3.2, S3.3)

**Reference File:** `resources/js/src/views/forms/layouts.vue`

**Form Layout:**

```
+-------------------------------------------------------------------+
|  Agregar Cliente                              [Cancelar] [Guardar] |
+-------------------------------------------------------------------+
|                                                                    |
|  DATOS PERSONALES                                                  |
|  +---------------------------+  +---------------------------+      |
|  | Nombres *                 |  | Apellidos *               |      |
|  | [_____________________]   |  | [_____________________]   |      |
|  +---------------------------+  +---------------------------+      |
|  +---------------------------+  +---------------------------+      |
|  | Fecha Nacimiento *        |  | Genero *                  |      |
|  | [__/__/____]              |  | [Seleccionar v]           |      |
|  +---------------------------+  +---------------------------+      |
|  +---------------------------+  +---------------------------+      |
|  | Nacionalidad *            |  | Pasaporte                 |      |
|  | [Seleccionar v]           |  | [_____________________]   |      |
|  +---------------------------+  +---------------------------+      |
|                                                                    |
|  CONTACTO                                                          |
|  +---------------------------+  +---------------------------+      |
|  | Email *                   |  | Telefono *                |      |
|  | [_____________________]   |  | [_____________________]   |      |
|  +---------------------------+  +---------------------------+      |
|                                                                    |
|  DATOS EN CANADA                                                   |
|  +---------------------------+  +---------------------------+      |
|  | Estatus en Canada *       |  | Punto de Acceso           |      |
|  | [Seleccionar v]           |  | [Seleccionar v]           |      |
|  +---------------------------+  +---------------------------+      |
|                                                                    |
|  ACCESO AL PORTAL (opcional)                                       |
|  +---------------------------+                                     |
|  | [ ] Crear acceso portal   |                                     |
|  | Email para acceso:        |                                     |
|  | [_____________________]   |                                     |
|  +---------------------------+                                     |
|                                                                    |
|                                       [Cancelar] [Guardar Cliente] |
+-------------------------------------------------------------------+
```

---

### 3.3 Expedientes Module (S4, S4.1, S4.2, S4.3, S4.4)

#### 3.3.1 Lista de Expedientes (S4)

**Layout:**

```
+-------------------------------------------------------------------+
|  Expedientes                               [+ Nuevo Expediente]    |
+-------------------------------------------------------------------+
|  [Buscar...]  [Estado: v] [Tipo: v] [Prioridad: v] [Responsable: v]|
+-------------------------------------------------------------------+
|                                                                    |
|  +-------------------------------------------------------------+   |
|  | EXP-2026-001      | Asilo         | Juan Garcia   | Activo  |   |
|  | Maria Consultora  | Urgente [!]   | 65% [======---]         |   |
|  | Audiencia: 2026-03-15                                       |   |
|  |                               [Ver Detalle] [Editar]        |   |
|  +-------------------------------------------------------------+   |
|  | EXP-2026-002      | Express Entry | Ahmed Hassan  | Activo  |   |
|  | Carlos Staff      | Alta [!]      | 40% [====-----]         |   |
|  | Objetivo: 2026-06-01                                        |   |
|  |                               [Ver Detalle] [Editar]        |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
+-------------------------------------------------------------------+
```

**Expediente Card Structure:**

```html
<div class="panel p-4 mb-4">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <!-- Left: Code & Type -->
        <div>
            <h4 class="text-lg font-bold text-primary">EXP-2026-001</h4>
            <div class="text-sm text-white-dark">Asilo - Refugio</div>
        </div>

        <!-- Center: Client & Priority -->
        <div class="flex-1">
            <div class="font-semibold">Juan Garcia Perez</div>
            <span class="badge badge-outline-danger">Urgente</span>
            <span class="badge badge-outline-success ml-2">Activo</span>
        </div>

        <!-- Right: Progress & Dates -->
        <div class="text-right">
            <div class="text-sm mb-2">Progreso: 65%</div>
            <div class="w-40 h-2 bg-gray-200 rounded-full">
                <div class="h-2 bg-primary rounded-full" style="width: 65%"></div>
            </div>
            <div class="text-xs text-white-dark mt-2">Audiencia: 2026-03-15</div>
        </div>
    </div>

    <!-- Footer: Responsable & Actions -->
    <div class="flex justify-between items-center mt-4 pt-4 border-t border-white-light">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full bg-info flex items-center justify-center text-white text-xs">MC</div>
            <span class="ml-2 text-sm">Maria Consultora</span>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-sm btn-outline-primary">Ver Detalle</button>
            <button class="btn btn-sm btn-outline-info">Editar</button>
        </div>
    </div>
</div>
```

#### 3.3.2 Wizard de Creacion (S4.2)

**Reference File:** `resources/js/src/views/forms/wizards.vue`

**6-Step Wizard Layout:**

```
+-------------------------------------------------------------------+
|  Nuevo Expediente                                                  |
+-------------------------------------------------------------------+
|                                                                    |
|  [1. Tipo] --> [2. Calculadora] --> [3. Conflictos] -->           |
|             [4. Cliente] --> [5. Configuracion] --> [6. Validar]   |
|                                                                    |
+===================================================================+
|                                                                    |
|  PASO 1: SELECCIONAR TIPO DE CASO                                  |
|                                                                    |
|  Residencia Temporal                                               |
|  +----------+  +----------+  +----------+  +----------+            |
|  | Turista  |  |Estudiante|  | Trabajo  |  |  EMIT    |            |
|  +----------+  +----------+  +----------+  +----------+            |
|                                                                    |
|  Residencia Permanente                                             |
|  +----------+  +----------+  +----------+  +----------+            |
|  | Express  |  |  ARRIMA  |  |   PEQ    |  | Piloto   |            |
|  | Entry    |  |          |  |          |  |          |            |
|  +----------+  +----------+  +----------+  +----------+            |
|                                                                    |
|  Humanitario                                                       |
|  +----------+  +----------+  +----------+  +----------+            |
|  | Refugio  |  | Demanda  |  | Apelacion|  | Corte    |            |
|  | Asilo    |  | Asilo    |  |          |  | Federal  |            |
|  +----------+  +----------+  +----------+  +----------+            |
|                                                                    |
+-------------------------------------------------------------------+
|                                  [Cancelar]      [Siguiente -->]   |
+-------------------------------------------------------------------+
```

**Wizard Component Pattern:**

```vue
<form-wizard shape="circle" color="#4361ee" class="circle">
    <tab-content title="Tipo" :customIcon="iconFolder">
        <!-- Step 1: Tipo de Caso -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div
                v-for="tipo in tiposCaso"
                :key="tipo.codigo"
                @click="selectTipo(tipo)"
                class="p-4 border rounded-lg cursor-pointer hover:border-primary"
                :class="{ 'border-primary bg-primary-light': selectedTipo === tipo.codigo }"
            >
                <div class="text-center">
                    <icon-folder class="w-8 h-8 mx-auto mb-2" />
                    <div class="font-semibold">{{ tipo.nombre }}</div>
                </div>
            </div>
        </div>
    </tab-content>

    <tab-content title="Calculadora" :customIcon="iconCalculator">
        <!-- Step 2: Calculadora de Plazos -->
    </tab-content>

    <tab-content title="Conflictos" :customIcon="iconWarning">
        <!-- Step 3: Verificar Conflictos -->
    </tab-content>

    <tab-content title="Cliente" :customIcon="iconUser">
        <!-- Step 4: Seleccionar/Crear Cliente -->
    </tab-content>

    <tab-content title="Configuracion" :customIcon="iconSettings">
        <!-- Step 5: Responsable, Prioridad, etc. -->
    </tab-content>

    <tab-content title="Validar" :customIcon="iconCheck">
        <!-- Step 6: Resumen y Confirmar -->
    </tab-content>
</form-wizard>
```

#### 3.3.3 Detalle de Expediente (S4.3)

**Layout with Tabs:**

```
+-------------------------------------------------------------------+
|  < Expedientes / EXP-2026-001                     [Editar] [...]   |
+-------------------------------------------------------------------+
|  +------------------+  +--------------------------------------+    |
|  | EXP-2026-001     |  | Juan Garcia Perez                    |    |
|  | Asilo - Refugio  |  | Responsable: Maria Consultora        |    |
|  | Activo | Urgente |  | Progreso: 65% [==========----]       |    |
|  +------------------+  +--------------------------------------+    |
+-------------------------------------------------------------------+
|  [Estructura] [Historia] [Documentos] [Tareas] [Estado Cuenta] [IRCC]|
+-------------------------------------------------------------------+
|                                                                    |
|  Tab: Estructura (Folder Tree)                                     |
|  +-------------------------------------------------------------+   |
|  | > Documentos Personales                                      |   |
|  |   - Pasaporte.pdf                                           |   |
|  |   - Foto Carnet.jpg                                         |   |
|  | > Formularios FDA                                           |   |
|  |   - FDA Completo.pdf                                        |   |
|  | > Pruebas                                                   |   |
|  |   (empty)                                                   |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  Tab: Historia (Timeline)                                          |
|  +-------------------------------------------------------------+   |
|  | 2026-02-01 | Maria Consultora | Actualizo estado a Activo   |   |
|  | 2026-01-28 | Sistema          | Expediente creado           |   |
|  | 2026-01-25 | Carlos Staff     | Agregado documento FDA      |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  Tab: Documentos                                                   |
|  +------------------------+  +------------------------+            |
|  | Pasaporte.pdf          |  | FDA Completo.pdf       |            |
|  | PDF | 2.3 MB           |  | PDF | 1.1 MB           |            |
|  | [Ver] [Descargar]      |  | [Ver] [Descargar]      |            |
|  +------------------------+  +------------------------+            |
|  [+ Vincular Documento desde OneDrive/Google Drive]                |
|                                                                    |
|  Tab: Tareas                                                       |
|  +-------------------------------------------------------------+   |
|  | [ ] Obtener certificado policial | Urgente | Carlos Staff   |   |
|  | [x] Completar formulario FDA     | Alta    | Maria Consultora   |
|  | [ ] Preparar carpeta audiencia   | Media   | Sin asignar    |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  Tab: Estado de Cuenta                                             |
|  +-------------------------------------------------------------+   |
|  | Concepto              | Monto   | Fecha       | Tipo        |   |
|  | Honorarios iniciales  | $2,000  | 2026-01-15  | Factura     |   |
|  | Pago parcial          | -$1,000 | 2026-01-20  | Pago        |   |
|  | ---------------------------------------------------         |   |
|  | SALDO PENDIENTE       | $1,000  |             |             |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  Tab: Info IRCC                                                    |
|  +-------------------------------------------------------------+   |
|  | Email cuenta IRCC: juan.garcia@email.com                    |   |
|  | Pregunta seguridad: ******                                  |   |
|  | Numero solicitud: 2024-12345678                             |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
+-------------------------------------------------------------------+
```

---

### 3.4 Tareas Module (S7)

**Reference Files:**
- `resources/js/src/views/apps/todolist.vue`
- `resources/js/src/views/datatables/basic.vue`

**Layout:**

```
+-------------------------------------------------------------------+
|  Tareas                                        [+ Nueva Tarea]     |
+-------------------------------------------------------------------+
|  [Mis Tareas] [Todas] [Asignadas por mi]                           |
+-------------------------------------------------------------------+
|  [Buscar...]  [Estado: v] [Tipo: v] [Prioridad: v] [Expediente: v] |
+-------------------------------------------------------------------+
|                                                                    |
|  +-------------------------------------------------------------+   |
|  | ID   | Asunto           | Expediente | Responsable | Estado |   |
|  +-------------------------------------------------------------+   |
|  | T-01 | Llamar cliente   | EXP-001    | Carlos      | Nuevo  |   |
|  |      | Urgente [!]      | Garcia     |             |        |   |
|  |      | Vence: 2026-02-10            |             |        |   |
|  +-------------------------------------------------------------+   |
|  | T-02 | Traducir doc...  | EXP-002    | Ana         | Proceso|   |
|  |      | Alta [!]         | Hassan     | 2.5h        |        |   |
|  |      | Vence: 2026-02-15            |             |        |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
+-------------------------------------------------------------------+
```

**Task Card/Row Component:**

```html
<div class="panel p-4 mb-3 hover:shadow-lg transition-shadow">
    <div class="flex items-start gap-4">
        <!-- Checkbox -->
        <label class="checkbox mt-1">
            <input type="checkbox" :checked="tarea.estado === 'completada'" @change="toggleComplete(tarea)" />
            <span></span>
        </label>

        <!-- Content -->
        <div class="flex-1">
            <div class="flex justify-between items-start">
                <div>
                    <h5 class="font-semibold" :class="{ 'line-through text-white-dark': tarea.estado === 'completada' }">
                        {{ tarea.titulo }}
                    </h5>
                    <div class="text-sm text-white-dark">
                        <router-link :to="`/expedientes/${tarea.expediente_id}`" class="text-primary hover:underline">
                            {{ tarea.expediente_codigo }}
                        </router-link>
                        - {{ tarea.cliente_nombre }}
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="badge" :class="getPriorityClass(tarea.prioridad)">{{ tarea.prioridad }}</span>
                    <span class="badge" :class="getStatusClass(tarea.estado)">{{ tarea.estado }}</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-between items-center mt-3 text-sm">
                <div class="flex items-center gap-4">
                    <div class="flex items-center">
                        <icon-user class="w-4 h-4 mr-1" />
                        {{ tarea.responsable_nombre || 'Sin asignar' }}
                    </div>
                    <div class="flex items-center" :class="{ 'text-danger': isOverdue(tarea.fecha_vencimiento) }">
                        <icon-calendar class="w-4 h-4 mr-1" />
                        {{ formatDate(tarea.fecha_vencimiento) }}
                    </div>
                    <div v-if="tarea.tiempo_registrado" class="flex items-center">
                        <icon-clock class="w-4 h-4 mr-1" />
                        {{ formatTime(tarea.tiempo_registrado) }}
                    </div>
                </div>

                <div class="flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" @click="logTime(tarea)">
                        <icon-clock class="w-4 h-4" />
                    </button>
                    <button class="btn btn-sm btn-outline-info" @click="editTask(tarea)">
                        <icon-pencil class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
```

**Time Logging Modal:**

```
+-------------------------------------------+
|  Registrar Tiempo                    [X]  |
+-------------------------------------------+
|                                           |
|  Tarea: Llamar cliente Garcia             |
|                                           |
|  Fecha *                                  |
|  [2026-02-08]                             |
|                                           |
|  Tiempo (minutos) *                       |
|  [30]                                     |
|                                           |
|  Descripcion                              |
|  [________________________]               |
|  [________________________]               |
|                                           |
+-------------------------------------------+
|           [Cancelar]  [Guardar Tiempo]    |
+-------------------------------------------+
```

---

### 3.5 Seguimiento Module (S6)

**Reference File:** `resources/js/src/views/components/timeline.vue`

**Layout:**

```
+-------------------------------------------------------------------+
|  Seguimiento                               [+ Nuevo Seguimiento]   |
+-------------------------------------------------------------------+
|  [Buscar...]  [Canal: v] [Responsable: v] [Fecha: desde - hasta]   |
+-------------------------------------------------------------------+
|                                                                    |
|  2026-02-08 (Hoy)                                                  |
|  +-------------------------------------------------------------+   |
|  | 10:30 | Llamada | Maria Consultora                          |   |
|  | EXP-001 - Juan Garcia                                       |   |
|  | [Entrante] Consulta sobre fecha de audiencia               |   |
|  | Duracion: 15 min                                            |   |
|  +-------------------------------------------------------------+   |
|  | 09:00 | Correo | Carlos Staff                               |   |
|  | EXP-002 - Ahmed Hassan                                      |   |
|  | [Saliente] Solicitud de documentos adicionales             |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
|  2026-02-07                                                        |
|  +-------------------------------------------------------------+   |
|  | 16:00 | Reunion | Maria Consultora                          |   |
|  | EXP-001 - Juan Garcia                                       |   |
|  | [Presencial] Revision de caso y preparacion audiencia      |   |
|  | Duracion: 1h 30min                                          |   |
|  +-------------------------------------------------------------+   |
|                                                                    |
+-------------------------------------------------------------------+
```

**Timeline Entry Component:**

```html
<div class="flex mb-6">
    <!-- Time Column -->
    <div class="text-right min-w-[60px] text-sm text-white-dark pr-4">
        {{ formatTime(entry.fecha) }}
    </div>

    <!-- Timeline Dot -->
    <div class="relative flex-none">
        <div class="w-3 h-3 rounded-full border-2" :class="getChannelColor(entry.canal)"></div>
        <div class="absolute top-3 left-1/2 -translate-x-1/2 w-0.5 h-full bg-white-light dark:bg-[#191e3a]"></div>
    </div>

    <!-- Content -->
    <div class="flex-1 pl-4 pb-6">
        <div class="panel p-4">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <span class="badge" :class="getChannelClass(entry.canal)">{{ entry.canal }}</span>
                    <span class="ml-2 text-sm text-white-dark">{{ entry.responsable_nombre }}</span>
                </div>
                <span class="badge badge-outline-dark">{{ entry.direccion }}</span>
            </div>

            <div class="text-sm mb-2">
                <router-link :to="`/expedientes/${entry.expediente_id}`" class="text-primary hover:underline">
                    {{ entry.expediente_codigo }}
                </router-link>
                - {{ entry.cliente_nombre }}
            </div>

            <p class="text-white-dark">{{ entry.resumen }}</p>

            <div v-if="entry.duracion_horas" class="mt-2 text-xs text-white-dark">
                <icon-clock class="w-3 h-3 inline-block mr-1" />
                Duracion: {{ formatDuration(entry.duracion_horas) }}
            </div>
        </div>
    </div>
</div>
```

---

### 3.6 Agenda Module (S5)

**Reference File:** `resources/js/src/views/apps/calendar.vue`

**Layout:**

```
+-------------------------------------------------------------------+
|  Agenda                                     [+ Crear Evento]       |
+-------------------------------------------------------------------+
|  [Mes] [Semana] [Dia]           < Febrero 2026 >                   |
+-------------------------------------------------------------------+
|  Filtros: [Mi Calendario] [Equipo: v]                              |
+-------------------------------------------------------------------+
|                                                                    |
|  +---------------------------------------------------------------+ |
|  | DOM | LUN | MAR | MIE | JUE | VIE | SAB                       | |
|  +---------------------------------------------------------------+ |
|  |     |     |     |     |     |     |  1  |                     | |
|  +-----+-----+-----+-----+-----+-----+-----+                     | |
|  |  2  |  3  |  4  |  5  |  6  |  7  |  8  |                     | |
|  |     |[Aud]|     |     |     |     |     |                     | |
|  +-----+-----+-----+-----+-----+-----+-----+                     | |
|  |  9  | 10  | 11  | 12  | 13  | 14  | 15  |                     | |
|  |     |     |     |[Cit]|     |     |     |                     | |
|  +-----+-----+-----+-----+-----+-----+-----+                     | |
|  | ... |                                                         | |
|  +---------------------------------------------------------------+ |
|                                                                    |
+-------------------------------------------------------------------+
```

**Event Categories:**

| Categoria | Color | Class |
|-----------|-------|-------|
| Audiencia | Danger | `bg-danger` |
| Cita Cliente | Primary | `bg-primary` |
| Reunion Interna | Info | `bg-info` |
| Personal | Success | `bg-success` |
| Plazo | Warning | `bg-warning` |

**Event Modal (from calendar.vue pattern):**

```vue
<TransitionRoot :show="isAddEventModal">
    <Dialog @close="isAddEventModal = false">
        <DialogPanel class="panel max-w-lg">
            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] p-4">
                {{ params.id ? 'Editar Evento' : 'Nuevo Evento' }}
            </div>

            <form @submit.prevent="saveEvent" class="p-5">
                <div class="mb-5">
                    <label>Titulo *</label>
                    <input type="text" class="form-input" v-model="params.titulo" required />
                </div>

                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label>Fecha Inicio *</label>
                        <input type="datetime-local" class="form-input" v-model="params.fecha_inicio" required />
                    </div>
                    <div>
                        <label>Fecha Fin *</label>
                        <input type="datetime-local" class="form-input" v-model="params.fecha_fin" required />
                    </div>
                </div>

                <div class="mb-5">
                    <label>Expediente (opcional)</label>
                    <select class="form-select" v-model="params.expediente_id">
                        <option value="">Sin expediente</option>
                        <option v-for="exp in expedientes" :value="exp.id">{{ exp.codigo }}</option>
                    </select>
                </div>

                <div class="mb-5">
                    <label>Categoria</label>
                    <div class="flex gap-4 mt-2">
                        <label v-for="cat in categorias" class="inline-flex items-center">
                            <input type="radio" class="form-radio" :class="`text-${cat.color}`"
                                   v-model="params.categoria" :value="cat.value" />
                            <span class="ml-2">{{ cat.label }}</span>
                        </label>
                    </div>
                </div>

                <div class="mb-5">
                    <label>Descripcion</label>
                    <textarea class="form-textarea" v-model="params.descripcion"></textarea>
                </div>

                <div class="flex justify-end gap-4">
                    <button type="button" class="btn btn-outline-danger" @click="isAddEventModal = false">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ params.id ? 'Actualizar' : 'Crear' }} Evento
                    </button>
                </div>
            </form>
        </DialogPanel>
    </Dialog>
</TransitionRoot>
```

---

### 3.7 Documentos Integration

**File Browser Component:**

```
+-------------------------------------------------------------------+
|  Vincular Documento                                          [X]   |
+-------------------------------------------------------------------+
|  [OneDrive] [Google Drive]                                         |
+-------------------------------------------------------------------+
|  < Mis Archivos / Clientes / Garcia                                |
+-------------------------------------------------------------------+
|                                                                    |
|  [Folder] Documentos Personales                                    |
|  [Folder] Formularios                                              |
|  [PDF] Pasaporte_JGarcia.pdf                    2.3 MB   [Vincular]|
|  [PDF] FDA_Completo.pdf                         1.1 MB   [Vincular]|
|  [DOC] Carta_Explicacion.docx                   150 KB   [Vincular]|
|                                                                    |
+-------------------------------------------------------------------+
|  Documentos Seleccionados: 2                                       |
|  - Pasaporte_JGarcia.pdf                                           |
|  - FDA_Completo.pdf                                                |
+-------------------------------------------------------------------+
|                              [Cancelar] [Vincular Seleccionados]   |
+-------------------------------------------------------------------+
```

**Folder Tree Component:**

```html
<div class="panel">
    <div class="flex items-center justify-between mb-4">
        <h5 class="font-semibold">Estructura del Expediente</h5>
        <button class="btn btn-sm btn-primary">
            <icon-plus class="w-4 h-4 mr-1" /> Nueva Carpeta
        </button>
    </div>

    <ul class="space-y-2">
        <li v-for="folder in folders" :key="folder.id">
            <div class="flex items-center gap-2 p-2 hover:bg-white-light/50 rounded cursor-pointer"
                 @click="toggleFolder(folder)">
                <icon-folder-open v-if="folder.isOpen" class="w-5 h-5 text-warning" />
                <icon-folder v-else class="w-5 h-5 text-warning" />
                <span>{{ folder.nombre }}</span>
                <span class="text-xs text-white-dark">({{ folder.documentos_count }})</span>
            </div>

            <!-- Documents in folder -->
            <ul v-show="folder.isOpen" class="ml-6 mt-2 space-y-1">
                <li v-for="doc in folder.documentos" :key="doc.id"
                    class="flex items-center gap-2 p-2 hover:bg-white-light/30 rounded">
                    <icon-file class="w-4 h-4 text-primary" />
                    <span class="flex-1">{{ doc.nombre }}</span>
                    <span class="text-xs text-white-dark">{{ formatFileSize(doc.tamano) }}</span>
                    <a :href="doc.provider_web_url" target="_blank" class="btn btn-xs btn-outline-primary">
                        <icon-eye class="w-3 h-3" />
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</div>
```

---

## 4. Common Patterns

### 4.1 Filter Bars

**Standard Filter Layout:**

```html
<div class="panel mb-5">
    <div class="flex flex-wrap gap-4 items-center">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
            <input type="text" class="form-input ltr:pr-10 rtl:pl-10"
                   placeholder="Buscar..." v-model="filters.search" />
            <div class="absolute ltr:right-3 rtl:left-3 top-1/2 -translate-y-1/2">
                <icon-search class="w-5 h-5 text-white-dark" />
            </div>
        </div>

        <!-- Dropdowns -->
        <select class="form-select w-auto" v-model="filters.estado">
            <option value="">Todos los estados</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
        </select>

        <select class="form-select w-auto" v-model="filters.prioridad">
            <option value="">Todas las prioridades</option>
            <option value="urgente">Urgente</option>
            <option value="alta">Alta</option>
            <option value="media">Media</option>
            <option value="baja">Baja</option>
        </select>

        <!-- Clear Filters -->
        <button class="btn btn-outline-dark" @click="clearFilters">
            <icon-refresh class="w-4 h-4 mr-1" /> Limpiar
        </button>
    </div>

    <!-- Active Filters Chips -->
    <div v-if="hasActiveFilters" class="flex flex-wrap gap-2 mt-4">
        <span v-for="filter in activeFilters" :key="filter.key"
              class="badge bg-primary/20 text-primary">
            {{ filter.label }}: {{ filter.value }}
            <button @click="removeFilter(filter.key)" class="ml-1">
                <icon-x class="w-3 h-3" />
            </button>
        </span>
    </div>
</div>
```

### 4.2 Status & Priority Badges

**Badge Variants:**

```html
<!-- Status Badges -->
<span class="badge bg-success">Activo</span>
<span class="badge bg-warning">Inactivo</span>
<span class="badge bg-danger">Papelera</span>
<span class="badge bg-dark">Archivado</span>
<span class="badge bg-info">Cerrado</span>

<!-- Priority Badges -->
<span class="badge badge-outline-danger">Urgente</span>
<span class="badge badge-outline-warning">Alta</span>
<span class="badge badge-outline-info">Media</span>
<span class="badge badge-outline-success">Baja</span>

<!-- Task Status Badges -->
<span class="badge bg-gray-500">Nuevo</span>
<span class="badge bg-primary">Asignado</span>
<span class="badge bg-info">En Proceso</span>
<span class="badge bg-warning">Rechazado</span>
<span class="badge bg-success">Resuelto</span>
<span class="badge bg-dark">Cerrado</span>
```

**Badge Helper Function:**

```typescript
function getStatusClass(estado: string): string {
    const classes: Record<string, string> = {
        'activo': 'bg-success',
        'inactivo': 'bg-warning',
        'papelera': 'bg-danger',
        'archivado': 'bg-dark',
        'cerrado': 'bg-info',
        'borrador': 'bg-gray-500',
        'pausado': 'bg-warning',
        'completado': 'bg-success',
        'cancelado': 'bg-danger',
    };
    return `badge ${classes[estado] || 'bg-dark'}`;
}

function getPriorityClass(prioridad: string): string {
    const classes: Record<string, string> = {
        'urgente': 'badge-outline-danger',
        'alta': 'badge-outline-warning',
        'media': 'badge-outline-info',
        'baja': 'badge-outline-success',
    };
    return `badge ${classes[prioridad] || 'badge-outline-dark'}`;
}
```

### 4.3 Cards

**Client Card:**

```html
<div class="panel h-full">
    <div class="flex items-center gap-4 mb-4">
        <div class="w-14 h-14 rounded-full bg-primary/20 flex items-center justify-center text-primary text-xl font-bold">
            {{ getInitials(cliente.nombre, cliente.apellidos) }}
        </div>
        <div class="flex-1">
            <h5 class="font-semibold text-lg">{{ cliente.nombre }} {{ cliente.apellidos }}</h5>
            <div class="text-sm text-white-dark">{{ cliente.nacionalidad }}</div>
        </div>
        <span :class="getStatusClass(cliente.estado)">{{ cliente.estado }}</span>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm mb-4">
        <div>
            <div class="text-white-dark">Email</div>
            <div>{{ cliente.email }}</div>
        </div>
        <div>
            <div class="text-white-dark">Telefono</div>
            <div>{{ cliente.telefono }}</div>
        </div>
    </div>

    <div class="flex justify-between items-center pt-4 border-t border-white-light dark:border-[#191e3a]">
        <div class="text-sm">
            <span class="text-info font-bold">{{ cliente.expedientes_count }}</span> expedientes
        </div>
        <div class="flex gap-2">
            <router-link :to="`/clientes/${cliente.id}`" class="btn btn-sm btn-outline-primary">Ver</router-link>
            <router-link :to="`/clientes/${cliente.id}/edit`" class="btn btn-sm btn-outline-info">Editar</router-link>
        </div>
    </div>
</div>
```

**Expediente Card:**

```html
<div class="panel">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h5 class="font-bold text-primary">{{ expediente.codigo }}</h5>
            <div class="text-sm text-white-dark">{{ expediente.tipo_caso }}</div>
        </div>
        <div class="flex gap-2">
            <span :class="getPriorityClass(expediente.prioridad)">{{ expediente.prioridad }}</span>
            <span :class="getStatusClass(expediente.estado)">{{ expediente.estado }}</span>
        </div>
    </div>

    <div class="mb-4">
        <div class="text-sm font-medium">{{ expediente.cliente_nombre }}</div>
    </div>

    <!-- Progress Bar -->
    <div class="mb-4">
        <div class="flex justify-between text-sm mb-1">
            <span>Progreso</span>
            <span>{{ expediente.progreso }}%</span>
        </div>
        <div class="w-full h-2 bg-gray-200 dark:bg-dark rounded-full">
            <div class="h-2 bg-primary rounded-full transition-all"
                 :style="{ width: `${expediente.progreso}%` }"></div>
        </div>
    </div>

    <!-- Dates -->
    <div class="grid grid-cols-2 gap-2 text-sm text-white-dark mb-4">
        <div v-if="expediente.fecha_audiencia">
            <icon-calendar class="w-4 h-4 inline mr-1" />
            Audiencia: {{ formatDate(expediente.fecha_audiencia) }}
        </div>
        <div v-if="expediente.fecha_objetivo">
            <icon-calendar class="w-4 h-4 inline mr-1" />
            Objetivo: {{ formatDate(expediente.fecha_objetivo) }}
        </div>
    </div>

    <!-- Responsable -->
    <div class="flex justify-between items-center pt-4 border-t border-white-light dark:border-[#191e3a]">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-info/20 flex items-center justify-center text-info text-xs font-bold">
                {{ getInitials(expediente.consultor_nombre) }}
            </div>
            <span class="text-sm">{{ expediente.consultor_nombre }}</span>
        </div>
        <router-link :to="`/expedientes/${expediente.id}`" class="btn btn-sm btn-primary">
            Ver Detalle
        </router-link>
    </div>
</div>
```

### 4.4 Forms

**Field Grouping Pattern:**

```html
<div class="panel">
    <h5 class="font-semibold text-lg mb-5">Datos Personales</h5>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
            <label for="nombre">Nombres <span class="text-danger">*</span></label>
            <input id="nombre" type="text" class="form-input" v-model="form.nombre" required />
            <span v-if="errors.nombre" class="text-danger text-sm">{{ errors.nombre }}</span>
        </div>
        <div>
            <label for="apellidos">Apellidos <span class="text-danger">*</span></label>
            <input id="apellidos" type="text" class="form-input" v-model="form.apellidos" required />
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mt-5">
        <div>
            <label for="fecha_nacimiento">Fecha Nacimiento</label>
            <input id="fecha_nacimiento" type="date" class="form-input" v-model="form.fecha_nacimiento" />
        </div>
        <div>
            <label for="genero">Genero</label>
            <select id="genero" class="form-select" v-model="form.genero">
                <option value="">Seleccionar...</option>
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
                <option value="otro">Otro</option>
            </select>
        </div>
        <div>
            <label for="nacionalidad">Nacionalidad</label>
            <select id="nacionalidad" class="form-select" v-model="form.nacionalidad">
                <option value="">Seleccionar...</option>
                <!-- countries options -->
            </select>
        </div>
    </div>
</div>
```

**Validation States:**

```html
<!-- Success -->
<input type="text" class="form-input border-success focus:border-success" />
<span class="text-success text-sm">Campo valido</span>

<!-- Error -->
<input type="text" class="form-input border-danger focus:border-danger" />
<span class="text-danger text-sm">Este campo es requerido</span>
```

**Button Placement:**

```html
<!-- Form Footer -->
<div class="flex justify-end gap-4 mt-8 pt-5 border-t border-white-light dark:border-[#191e3a]">
    <button type="button" class="btn btn-outline-dark" @click="cancel">Cancelar</button>
    <button type="button" class="btn btn-outline-primary" @click="saveDraft">Guardar Borrador</button>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
```

### 4.5 Modals

**Confirmation Dialog:**

```vue
<script setup>
import Swal from 'sweetalert2';

const confirmDelete = async (item: any) => {
    const result = await Swal.fire({
        title: 'Estas seguro?',
        text: `Se eliminara "${item.nombre}" permanentemente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        cancelButtonColor: '#888ea8',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) {
        await deleteItem(item.id);
        showMessage('Eliminado correctamente');
    }
};

const showMessage = (msg: string, type = 'success') => {
    const toast = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
    });
    toast.fire({ icon: type, title: msg });
};
</script>
```

**Form Modal (HeadlessUI Pattern from contacts.vue):**

```vue
<TransitionRoot :show="isModalOpen" as="template">
    <Dialog @close="isModalOpen = false" class="relative z-[51]">
        <TransitionChild
            enter="duration-300 ease-out"
            enter-from="opacity-0"
            enter-to="opacity-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100"
            leave-to="opacity-0"
        >
            <DialogOverlay class="fixed inset-0 bg-[black]/60" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                <TransitionChild
                    enter="duration-300 ease-out"
                    enter-from="opacity-0 scale-95"
                    enter-to="opacity-100 scale-100"
                    leave="duration-200 ease-in"
                    leave-from="opacity-100 scale-100"
                    leave-to="opacity-0 scale-95"
                >
                    <DialogPanel class="panel w-full max-w-lg">
                        <!-- Header -->
                        <div class="flex justify-between items-center p-5 border-b border-white-light dark:border-[#191e3a]">
                            <h5 class="font-semibold text-lg">{{ title }}</h5>
                            <button @click="isModalOpen = false">
                                <icon-x class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="p-5">
                            <slot />
                        </div>

                        <!-- Footer -->
                        <div class="flex justify-end gap-4 p-5 border-t border-white-light dark:border-[#191e3a]">
                            <button class="btn btn-outline-dark" @click="isModalOpen = false">Cancelar</button>
                            <button class="btn btn-primary" @click="$emit('confirm')">Confirmar</button>
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </div>
        </div>
    </Dialog>
</TransitionRoot>
```

---

## 5. Responsive Behavior

### 5.1 Breakpoints

Using Tailwind's default breakpoints:

| Breakpoint | Min Width | Use Case |
|------------|-----------|----------|
| `sm` | 640px | Mobile landscape |
| `md` | 768px | Tablets |
| `lg` | 1024px | Small laptops |
| `xl` | 1280px | Desktops |
| `2xl` | 1536px | Large screens |

### 5.2 Mobile Sidebar Collapse

The sidebar collapses to overlay on mobile (handled by `Sidebar.vue`):

```vue
<!-- Toggle button in Header for mobile -->
<button class="xl:hidden" @click="store.toggleSidebar()">
    <icon-menu class="w-6 h-6" />
</button>

<!-- Sidebar with mobile overlay -->
<div class="sidebar" :class="{ 'hidden': !store.sidebar && isMobile }">
    <!-- ... sidebar content ... -->
</div>
```

### 5.3 Card Grid Responsiveness

```html
<!-- 4 cols on 2xl, 3 on xl, 2 on sm, 1 on mobile -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6">
    <ClienteCard v-for="cliente in clientes" :key="cliente.id" :cliente="cliente" />
</div>
```

### 5.4 Table to Card on Mobile

For DataTables, use responsive mode or hide on mobile with card alternative:

```html
<!-- Table visible on md and up -->
<div class="hidden md:block">
    <table class="table-striped">
        <!-- table content -->
    </table>
</div>

<!-- Cards visible on mobile only -->
<div class="md:hidden space-y-4">
    <div v-for="item in items" class="panel">
        <!-- card layout -->
    </div>
</div>
```

---

## 6. Accessibility

### 6.1 Focus States

All interactive elements have visible focus states:

```css
.form-input:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
    outline: none;
}

.btn:focus {
    outline: 2px solid #4361ee;
    outline-offset: 2px;
}
```

### 6.2 Keyboard Navigation

- All forms navigable with Tab
- Modals trap focus
- Escape closes modals/dropdowns
- Enter submits forms

**Navigation ARIA Labels:**

```html
<nav aria-label="Main navigation">
    <ul role="menubar">
        <li role="none">
            <router-link role="menuitem" aria-current="page">Dashboard</router-link>
        </li>
    </ul>
</nav>
```

### 6.3 Screen Reader Considerations

```html
<!-- Announce dynamic content -->
<div role="status" aria-live="polite">
    {{ loadingMessage }}
</div>

<!-- Label form controls -->
<label for="email">Email <span class="text-danger" aria-label="required">*</span></label>
<input id="email" type="email" aria-required="true" aria-describedby="email-error" />
<span id="email-error" role="alert" class="text-danger">{{ errors.email }}</span>

<!-- Icon buttons need labels -->
<button aria-label="Editar cliente" @click="editCliente">
    <icon-pencil aria-hidden="true" />
</button>
```

### 6.4 Color Contrast

All text meets WCAG AA standards:
- Normal text: 4.5:1 minimum contrast ratio
- Large text: 3:1 minimum contrast ratio
- UI components: 3:1 minimum contrast ratio

---

## 7. Component Mapping Matrix

| Prototype Screen | Prototype ID | Template Component(s) | File Reference |
|-----------------|--------------|----------------------|----------------|
| Login Staff | S1 | Auth Layout | `views/auth/cover-login.vue` |
| Dashboard | S2 | Stats Cards, Timeline | `views/index.vue`, `views/components/timeline.vue` |
| Lista Clientes | S3 | Card Grid | `views/apps/contacts.vue` |
| Perfil Cliente | S3.1 | Tabs, Timeline | `views/components/tabs.vue`, `views/components/timeline.vue` |
| Editar Cliente | S3.2 | Form Layout | `views/forms/layouts.vue` |
| Agregar Cliente | S3.3 | Form Layout | `views/forms/layouts.vue` |
| Lista Expedientes | S4 | Card List | `views/apps/contacts.vue` (card pattern) |
| Expedientes Usuario | S4.1 | Card Grid | `views/apps/contacts.vue` |
| Agregar Expediente | S4.2 | Form Wizard | `views/forms/wizards.vue` |
| Consultar Expediente | S4.3 | Tabs, Timeline, DataTable | `views/components/tabs.vue`, `views/datatables/basic.vue` |
| Modificar Expediente | S4.4 | Form Layout | `views/forms/layouts.vue` |
| Agenda | S5 | FullCalendar, Modal | `views/apps/calendar.vue` |
| Seguimiento | S6 | Timeline | `views/components/timeline.vue` |
| Tareas | S7 | DataTable, Checkbox | `views/apps/todolist.vue`, `views/datatables/checkbox.vue` |
| Calculadora Plazos | S8 | Form, Calculator UI | `views/forms/basic.vue` |
| Reporte Tiempos | S9 | DataTable, Filters | `views/datatables/advanced.vue` |

---

## Appendix A: CSS Class Quick Reference

### Panels & Cards
```css
.panel         /* Main container with shadow, padding, rounded */
.card          /* Basic card container */
```

### Buttons
```css
.btn                    /* Base button */
.btn-primary           /* Primary action */
.btn-secondary         /* Secondary action */
.btn-success           /* Positive action */
.btn-danger            /* Destructive action */
.btn-warning           /* Warning action */
.btn-info              /* Informational action */
.btn-outline-*         /* Outlined variants */
.btn-sm                /* Small button */
.btn-lg                /* Large button */
```

### Forms
```css
.form-input            /* Text input */
.form-select           /* Select dropdown */
.form-textarea         /* Textarea */
.form-checkbox         /* Checkbox */
.form-radio            /* Radio button */
.form-switch           /* Toggle switch */
```

### Badges
```css
.badge                 /* Base badge */
.badge-outline-*       /* Outlined badge */
```

### Tables
```css
.table-striped         /* Striped rows */
.table-hover           /* Row hover effect */
.table-responsive      /* Horizontal scroll on small screens */
```

---

**Document Status:** Ready for Implementation
**Next Steps:**
1. Review with development team
2. Create component prototypes
3. Implement module by module following implementation phases from architecture.md
