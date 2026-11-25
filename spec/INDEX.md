# INDEX.md — Mapa de Especificaciones

> Actualizado: 2026-11-24 (Spec 71 creado)
> Leer este archivo antes de implementar cualquier feature para identificar specs relacionados.

## Leyenda de Estado
`HIST` histórico/supersedido · `PEND` pendiente · `PART` parcial · `DONE` implementado

## Leyenda de Dominio
`ARCH` arquitectura · `AUTH` autenticación/seguridad · `CASES` expedientes · `WFLOW` workflows · `CLNT` clientes/acompañantes · `DOCS` documentos/storage · `CAL` calendario · `TIME` timesheet · `UX` interfaz/i18n · `SYS` sistema/infra · `ALRT` alertas/dashboard

---

## Specs Históricos — Planificación Inicial (no implementar directamente)

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 00 | 00_arquitectura_auth_sistema_completo | Arquitectura Base + Auth | ARCH | HIST | 02,03,04 |
| 01 | 01_architectural_analysis | Análisis Arquitectónico | ARCH | HIST | 00,02 |
| 02 | 02_implementation_specs | Specs de Implementación | ARCH | HIST | 00,01,03,04 |
| 03 | 03_backend_implementation_phases | Plan Backend (~88h) | ARCH | HIST | 02 |
| 04 | 04_frontend_implementation_phases | Plan Frontend (~156h) | ARCH | HIST | 02 |
| 05 | 05_socialite_analysis | OAuth Socialite (no implementado) | AUTH | HIST | 00 |

---

## Auth / Seguridad / Sesiones

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 06 | 06_role_permission_management | Roles y Permisos | AUTH | DONE | 06a,06b,08 |
| 06a | 06a_backend_implementation_phases | Fases Backend Roles | AUTH | HIST | 06 |
| 06b | 06b_frontend_implementation_phases | Fases Frontend Roles | AUTH | HIST | 06 |
| 08 | 08_permisos_clientes_acompanantes_roles | Permisos Clientes/Acompañantes | AUTH | DONE | 06 |
| 34 | 34_security_hardening | Security Hardening | AUTH | DONE | 53 |
| 53 | 53_sesion_unica_por_usuario | Sesión Única — Fase 1 | AUTH | DONE | 54 |
| 54 | 54_sesion_unica_fase2_endurecimiento | Sesión Única — Fase 2 | AUTH | DONE | 53 |
| 60 | 60_ircc_credentials_vault | Bóveda Credenciales IRCC | AUTH/CASES | DONE | 07,08,34,53,58 |
| 66 | 66_rbac_granular_workflow_tasks_case_code | RBAC Granular: workflow_tasks + case_code | AUTH | DONE | 06,08,46,50,65,34 |
| 67 | 67_rbac_granular_fase2 | RBAC Granular Fase 2: builder + labels i18n roles | AUTH/UX | DONE | 66,06,08,65 |

---

## Expedientes (Cases)

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 07 | 07_epic_2.1_expedientes_core | Epic 2.1 — Core Expedientes | CASES | PART | 09,12,13,14 |
| 09 | 09_epic_2.2_case_wizard | Epic 2.2 — Wizard Creación | CASES | DONE | 07,10,56 |
| 09a | 09a_backend_implementation_phases | Fases Backend Wizard | CASES | HIST | 09 |
| 09b | 09b_frontend_implementation_phases | Fases Frontend Wizard | CASES | HIST | 09 |
| 10 | 10_companions_en_detalle_expediente | Companions en Expediente | CASES | DONE | 09,39 |
| 10a | 10a_backend_implementation_phases | Fases Backend Companions | CASES | HIST | 10 |
| 10b | 10b_frontend_implementation_phases | Fases Frontend Companions | CASES | HIST | 10 |
| 11 | 11_filtrado_assigned_to_consultor_activo | Filtro assigned_to activo | CASES | PEND | 07 |
| 12 | 12_fechas_importantes_dinamicas | Fechas Importantes v1 | CASES | PART | 07,32,57 |
| 13 | 13_campos_operativos_financieros | Campos Operativos/Financieros | CASES | PEND | 07,15 |
| 14 | 14_ciclo_de_vida_checklist_dinamico | Checklist Dinámico | CASES | DONE | 07,46 |
| 20 | 20_nueva_nemotecnica_codigo_expediente | Nemotecnia Código v1 (ver 55) | CASES | HIST | 55 |
| 55 | 55_generador_codigo_expediente | Generador Código YY-TT-AAAA-NNNN | CASES | PEND | 20 |
| 56 | 56_seleccion_dinamica_directorios_wizard | Directorios en Wizard (Step 5) | CASES | DONE | 09,27,30 |
| 58 | 58_cliente_vs_aplicante_principal | Cliente vs Aplicante Principal | CASES | DONE | 09,55,56,07 |
| 59 | 59_promocion_companion_a_cliente | Promoción Companion → Client | CASES | DONE | 08,10,39,58 |
| 65 | 65_panel_configuracion_codigo_expediente | Panel Config. Código Expediente | ADMIN/CASES | DONE | 55,56,41,30,27 |

---

## Clientes / Acompañantes

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 16 | 16_column_chooser_lista_clientes | Column Chooser Clientes | CLNT | DONE | — |
| 17 | 17_iuc_campo_companions | Campo IUC Companions | CLNT | DONE | 31 |
| 31 | 31_telefono_internacional_enrichment_companions | Teléfono Internacional | CLNT | DONE | 17 |
| 39 | 39_companion_form_unificacion_componente | Form Companions Unificado | CLNT | DONE | 10 |
| 64 | 64_clientes_empresa_patrocinador_beneficiario | Empresa: Patrocinador + Beneficiario + Familia | CLNT/CASES | PART | 07,08,09,56,58,59 |
| 69 | 69_companions_jerarquicos_empresa | Companions Jerárquicos para Empresa | CLNT/CASES | DONE | 64,58,10,09,08,56 |

---

## Workflows / Tareas

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 18 | 18_scrum_board_kanban | Scrum Board Kanban | WFLOW | DONE | 19 |
| 19 | 19_todo_list_integracion_casos | Todo List + Casos | WFLOW | DONE | 18,49 |
| 21 | 21_tareas_tab_expediente | Tab Tareas en Expediente | WFLOW | DONE | 47 |
| 46 | 46_workflows_dinamicos_motor_etapas | Motor Workflows Dinámicos | WFLOW | DONE | 47,48,49,50 |
| 47 | 47_tablero_interactivo_tareas_expediente | Tablero Tareas Expediente | WFLOW | DONE | 46,48 |
| 48 | 48_etapas_personalizadas_por_expediente | Etapas Personalizadas | WFLOW | PART | 46,47 |
| 49 | 49_todos_core_desde_workflow | Todos desde Workflow | WFLOW | DONE | 19,46 |
| 50 | 50_edicion_clonacion_tareas_workflow | Edición/Clonación Tareas | WFLOW | DONE | 46,49 |
| 71 | 71_scrumboard_vista_lista | Scrumboard: Vista de Lista | WFLOW/UX | PART | 18,42,43 |

---

## Documentos / Storage

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 15 | 15_estado_de_cuenta_facturas | Módulo Facturación (análisis) | DOCS | PEND | 13 |
| 27 | 27_file_manager_system | File Manager Sistema | DOCS | DONE | 28,30,56 |
| 28 | 28_multi_tenant_branding_storage | Branding + Storage Multi-tenant | DOCS | DONE | 27,30 |
| 30 | 30_cloud_folder_sync_por_tenant | Cloud Folder Sync | DOCS | DONE | 27,28,33,56 |
| 33 | 33_sharepoint_storage_provider | SharePoint Provider | DOCS | DONE | 28,30 |
| 36 | 36_documentos_legales_y_alertas_vencimiento | Docs Legales + Alertas Vencimiento | DOCS | DONE | 37 |
| 37 | 37_integridad_referencial_y_restricciones_eliminacion | Integridad Referencial | DOCS | DONE | 36 |
| 40 | 40_trash_papelera_reciclaje | Papelera de Reciclaje | DOCS | DONE | 36,37 |

---

## Calendario / Eventos

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 22 | 22_calendario_legal_integrado | Calendario Legal | CAL | DONE | 23,24 |
| 23 | 23_eventos_tab_expediente | Tab Eventos Expediente | CAL | DONE | 22 |
| 24 | 24_calendario_drag_drop_filtros_quicklinks | Calendario v2 Drag & Drop | CAL | DONE | 22,45 |
| 32 | 32_quick_event_from_important_dates | Quick Event desde Fechas | CAL | DONE | 12,22,57 |
| 45 | 45_sincronizacion_calendarios_externos | Sync Calendarios Externos (base — ver 62) | CAL | PART | 22,24,62 |
| 62 | 62_sincronizacion_calendarios_externos | Sync Calendarios — Completar Gaps | CAL | DONE | 22,24,45 |

---

## Timesheet

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 51 | 51_timesheet_control_tiempo | Control de Tiempo | TIME | DONE | 52 |
| 52 | 52_timesheet_gobernanza_cronometros | Gobernanza Cronómetros | TIME | DONE | 51,53 |

---

## UX / i18n

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 29 | 29_avatares_perfil_transicion_iniciales | Avatares de Perfil | UX | DONE | — |
| 35 | 35_ux_i18n_data_robustness | UX + i18n + Robustez | UX | DONE | — |
| 41 | 41_busqueda_global | Búsqueda Global | UX | DONE | — |
| 42 | 42_modal_guard_dirty_state | Modal Guard Dirty State | UX | DONE | — |
| 43 | 43_persistencia_filtros_listas | Persistencia Filtros | UX | DONE | — |
| 44 | 44_preferencia_visualizacion_nombres | Preferencia Nombres | UX | DONE | — |

---

## Sistema / Infraestructura

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 26 | 26_squashing_migraciones | Squashing Migraciones | SYS | DONE | — |
| 38 | 38_backup_total_base_de_datos | Backup por Tenant | SYS | DONE | — |

---

## Alertas / Dashboard

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 25 | 25_dashboard_centro_de_control | Dashboard Centro Control | ALRT | DONE | 36,57 |
| 57 | 57_alertas_fechas_importantes_expedientes | Radar Fechas Importantes | ALRT | DONE | 12,25,32 |
| 61 | 61_dashboard_tareas_paginacion_ordenamiento | Paginación Widget Tareas Dashboard | UX/WFLOW | DONE | 19,25,46,49 |
| 63 | 63_dashboard_proximos_hitos_legales | Widget Próximos Hitos Legales | ALRT/UX | DONE | 57,25,61,12,07 |

---

## Administración / Sistema

| N° | Archivo | Título Corto | Dominio | Estado | Relacionados |
|----|---------|--------------|---------|--------|--------------|
| 70 | 70_crud_case_types_clonacion_cascada | CRUD Case Types + Clonación en Cascada | ADMIN/CASES | PART | 07,46,50,65,66,67 |

---

## Specs Pendientes (próximas implementaciones)

| N° | Título | Prioridad estimada |
|----|--------|--------------------|
| 71 | Scrumboard: Vista de Lista + mostrar/ocultar finalizadas (solo frontend) | MEDIA |
| 70 | Tests Feature PHP: CaseTypeCloneTest, CaseTypeDeleteTest, CaseTypePermissionsTest (Fase 6) | MEDIA |
| 68 | RBAC Granular — Fase 3: mismo refactoring para módulos cases, documents, calendar, clients, dashboard | MEDIA |
| 13 | Campos Operativos/Financieros | MEDIA |
| 11 | Filtro assigned_to consultor activo | BAJA |
| 15 | Módulo Facturación | BAJA |

---

## Cómo usar este índice

1. Identifica el dominio del feature a implementar
2. Lee el spec principal del número más alto del dominio (el más reciente)
3. Lee también los specs en columna "Relacionados" que sean `DONE` — dan contexto de contratos ya existentes
4. Los specs `HIST` solo leer si el spec activo hace referencia explícita a ellos
5. Al completar un feature: cambiar `PEND` → `DONE` o `PART` → `DONE` en este archivo
