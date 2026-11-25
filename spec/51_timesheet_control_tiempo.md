---
tipo: spec
numero: 51
modulo: Timesheet
dominio: TIME
estado: DONE
tags: [spec, timesheet, done]
relacionados:
  - "[[52_timesheet_gobernanza_cronometros|Spec 52]]"
---
# [[51_timesheet_control_tiempo|Spec 51]]: Sistema de Control de Tiempo (Timesheet)

**Versión:** 1.0
**Fecha:** 2026-05-07
**Branch:** `Timesheet`
**Stack:** Laravel 12 + Vue 3.5 + MySQL 8.0 + Pinia + Sanctum SPA

---

## Resumen Ejecutivo

Sistema de seguimiento de tiempo invertido por expediente. El tiempo se acumula a partir de los registros de las tareas (`CaseTask`) y todos (`Todo`) vinculados a un expediente (`ImmigrationCase`). El total aparece en el Header Card del expediente en formato `Xh Ym`.

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Modelo único `TimeLog`** soporta modo manual y timer (start/stop) | Simplicidad; menor superficie de mantenimiento |
| 2 | **`duration_seconds` siempre persistido** | Permite agregación directa sin cálculos en SQL |
| 3 | **Desnormalización: `case_id` siempre presente** en `time_logs` | Agregación directa por caso sin JOINs; header es el endpoint más caliente |
| 4 | **Campo cacheado `total_time_spent_seconds`** en `immigration_cases` + Observer con UPDATE atómico | Header se lee mucho más que se escriben logs; UPDATE `col = col + delta` es atómico en MySQL |
| 5 | **Máximo 1 timer activo por usuario** | Evita doble facturación; simplifica UX |
| 6 | **Almacenar en UTC** + columna `work_date DATE` separada | Estándar Laravel; soporta tenants/usuarios con timezones distintas |
| 7 | **`ON DELETE RESTRICT` para `case_id`**, **`SET NULL` para `case_task_id` y `todo_id`** | Los logs son evidencia legal/facturable; no deben perderse |
| 8 | **Soft deletes** en `time_logs` con recálculo del cache | Auditoría: nunca perder evidencia de tiempo facturable |
| 9 | **Permisos Spatie en migración**: `time_logs.create|view|edit|delete|view_others` | Sigue patrón G4 del proyecto |
| 10 | **Pinia store + servicio TS dedicados** + composable `useActiveTimer` | Consistente con los 15 stores y 20 servicios existentes |

---

## 1. Modelado de Datos

### 1.1 Tabla `time_logs`

```php
Schema::create('time_logs', function (Blueprint $table) {
    $table->id();

    // Multi-tenancy (G1: obligatorio)
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

    // Quién registró el tiempo
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

    // Vinculación: case_id SIEMPRE; task/todo opcionales
    $table->foreignId('case_id')->constrained('immigration_cases')->restrictOnDelete();
    $table->foreignId('case_task_id')->nullable()->constrained('case_tasks')->nullOnDelete();
    $table->foreignId('todo_id')->nullable()->constrained('todos')->nullOnDelete();

    // Datos del log
    $table->date('work_date');
    $table->unsignedInteger('duration_seconds')->default(0);
    $table->text('description')->nullable();

    // Modo timer (NULL si fue entrada manual)
    $table->timestamp('started_at')->nullable();
    $table->timestamp('ended_at')->nullable();

    // Flag de origen
    $table->enum('source', ['manual', 'timer'])->default('manual');

    $table->timestamps();
    $table->softDeletes();

    // Índices
    $table->index(['tenant_id', 'case_id', 'deleted_at'], 'idx_logs_case');
    $table->index(['tenant_id', 'user_id', 'deleted_at'], 'idx_logs_user');
    $table->index(['tenant_id', 'case_id', 'work_date'], 'idx_logs_case_date');
    $table->index(['user_id', 'ended_at'], 'idx_active_timer');
    $table->index(['case_task_id'], 'idx_logs_task');
    $table->index(['todo_id'], 'idx_logs_todo');
});
```

### 1.2 Campo cacheado en `immigration_cases`

```php
$table->unsignedInteger('total_time_spent_seconds')->default(0)->after('status');
```

### 1.3 Estrategia de cálculo

**Opción elegida: Campo cacheado + Observer con UPDATE atómico.**

```sql
UPDATE immigration_cases
SET total_time_spent_seconds = GREATEST(0, total_time_spent_seconds + :delta)
WHERE id = :case_id
```

Esta operación es atómica en InnoDB y no requiere `SELECT ... FOR UPDATE`.

### 1.4 Integridad referencial

| FK | Política | Razón |
|---|---|---|
| `tenant_id` | `CASCADE` | Si se borra el tenant, borrar todo |
| `user_id` | `RESTRICT` | Logs facturables no deben perderse |
| `case_id` | `RESTRICT` | Evidencia ligada al expediente |
| `case_task_id` | `SET NULL` | Si la tarea se borra, el log se conserva como tiempo "del caso" |
| `todo_id` | `SET NULL` | Idem |

---

## 2. Backend Laravel

### 2.1 Modelo `TimeLog`

**Archivo:** `app/Models/TimeLog.php`

- Traits: `BelongsToTenant`, `SoftDeletes`
- Relaciones: `user()`, `immigrationCase()`, `caseTask()`, `todo()`
- Accessors: `duration_formatted`, `is_running`, `elapsed_seconds`
- Scopes: `forCase()`, `forDateRange()`, `byUser()`, `running()`

```php
public function getDurationFormattedAttribute(): string
{
    $s = $this->is_running ? $this->elapsed_seconds : $this->duration_seconds;
    return self::formatSeconds($s);
}

public static function formatSeconds(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    return $h > 0 ? "{$h}h " . str_pad($m, 2, '0', STR_PAD_LEFT) . "m" : "{$m}m";
}
```

### 2.2 Observer `TimeLogObserver`

**Archivo:** `app/Observers/TimeLogObserver.php`

Registrado en `AppServiceProvider::boot()` (G7).

```php
private function applyDelta(int $caseId, int $delta): void
{
    if ($delta === 0) return;
    DB::statement(
        'UPDATE immigration_cases SET total_time_spent_seconds = GREATEST(0, total_time_spent_seconds + ?) WHERE id = ?',
        [$delta, $caseId]
    );
}
```

Eventos manejados: `created`, `updated` (detecta cambio en `duration_seconds`), `deleted`, `restored`.

### 2.3 Service `TimeLogService`

**Archivo:** `app/Services/Timesheet/TimeLogService.php`

Métodos:
- `startTimer(User $user, int $caseId, ?int $taskId, ?int $todoId): TimeLog`
- `stopTimer(int $timeLogId, User $user): TimeLog`
- `logManual(User $user, array $data): TimeLog`
- `getTotalForCase(int $caseId): int`
- `getActiveTimerForUser(User $user): ?TimeLog`
- `getLogsForCase(int $caseId, array $filters): LengthAwarePaginator`

**Constraint de timer único:** `lockForUpdate()` + validación de timer activo dentro de transacción.

### 2.4 Controller `TimeLogController`

**Archivo:** `app/Http/Controllers/Api/TimeLogController.php`

| Método | Endpoint | Permiso |
|---|---|---|
| `index` | `GET /api/cases/{case}/time-logs` | `time_logs.view` |
| `store` | `POST /api/cases/{case}/time-logs` | `time_logs.create` |
| `start` | `POST /api/cases/{case}/time-logs/start` | `time_logs.create` |
| `stop` | `PATCH /api/cases/{case}/time-logs/{log}/stop` | `time_logs.edit` |
| `update` | `PUT /api/cases/{case}/time-logs/{log}` | `time_logs.edit` |
| `destroy` | `DELETE /api/cases/{case}/time-logs/{log}` | `time_logs.delete` |
| `activeTimer` | `GET /api/me/active-timer` | auth |

### 2.5 Permisos (Spatie — G4)

**Migración dedicada:** `add_timesheet_permissions`

```
time_logs.view
time_logs.view_others
time_logs.create
time_logs.edit
time_logs.edit_others
time_logs.delete
```

Asignación:
- `super-admin`, `admin`: todos
- `attorney`: view, view_others, create, edit, delete
- `paralegal`: view, create, edit, delete
- `client`: ninguno

### 2.6 API Resource `TimeLogResource`

Campos: `id`, `case_id`, `case_task_id`, `todo_id`, `user{id,name}`, `work_date`, `duration_seconds`, `duration_formatted`, `description`, `started_at`, `ended_at`, `is_running`, `source`, `task{id,title}`, `todo{id,title}`, `created_at`.

### 2.7 Cambios en `CaseResource`

```php
'total_time_spent_seconds'   => (int) $this->total_time_spent_seconds,
'total_time_spent_formatted' => $this->total_time_spent_formatted,
'active_timer'               => /* timer activo del usuario auth, solo en show */
```

---

## 3. API y Transferencia de Datos

### 3.1 Eficiencia del Header Card

- `total_time_spent_seconds` + `total_time_spent_formatted`: **siempre** en `CaseResource` (O(1) lectura del campo cacheado).
- En `index` de casos: **no** eager-cargar `activeTimerForCurrentUser` (evita N+1).
- En `show` de un caso: eager 1 query adicional para el timer activo del usuario.

### 3.2 Endpoint de auditoría

```
GET /api/cases/{case}/time-logs?per_page=20&user_id=X&date_from=Y&date_to=Z&group_by=day
```

Respuesta agrupada por día con subtotales por grupo y total general en `meta`.

---

## 4. Frontend Vue.js

### 4.1 Componentes nuevos

| Componente | Ruta |
|---|---|
| `TimeDisplay.vue` | `components/timesheet/TimeDisplay.vue` |
| `TimeLogModal.vue` | `components/timesheet/TimeLogModal.vue` |
| `TimeLogList.vue` | `components/timesheet/TimeLogList.vue` |

**`TimeDisplay.vue`**: ícono de reloj + tiempo formateado. Props: `seconds`, `showIcon`, `size`, `isRunning`. Modo "isRunning" → clase `animate-pulse text-success`.

**`TimeLogModal.vue`**: dos tabs — **Manual** (inputs hours/minutes, date, description, task selector) y **Cronómetro** (Play ▶ / Stop ⏹ con cronómetro visual).

### 4.2 Store `useTimesheetStore`

**Archivo:** `resources/js/src/stores/useTimesheetStore.ts`

```typescript
interface State {
  activeTimer: TimeLog | null;
  caseLogs: Record<number, TimeLog[]>;
  caseTotal: Record<number, number>;
  isLoading: boolean;
}
```

Actions: `fetchActiveTimer`, `startTimer`, `stopTimer`, `logManual`, `fetchLogs`, `updateLog`, `deleteLog`.

### 4.3 Composable `useActiveTimer`

**Archivo:** `resources/js/src/composables/useActiveTimer.ts`

`setInterval` de 1s para cronómetro en vivo. `onUnmounted` limpia el interval. Computed `elapsed` reactivo que re-evalúa cada tick.

### 4.4 Utilidad `timeFormat.ts`

**Archivo:** `resources/js/src/utils/timeFormat.ts`

```typescript
formatDuration(seconds: number): string
// 3661 → "1h 01m" | 90 → "1m 30s" | 0 → "0m"

formatDurationDetailed(seconds: number): { hours, minutes, seconds }
```

### 4.5 i18n (FLAT — G6)

```json
{
  "timesheet.total_time": "Total Time",
  "timesheet.log_time": "Log Time",
  "timesheet.start_timer": "Start Timer",
  "timesheet.stop_timer": "Stop Timer",
  "timesheet.manual_entry": "Manual Entry",
  "timesheet.timer_mode": "Timer",
  "timesheet.hours": "Hours",
  "timesheet.minutes": "Minutes",
  "timesheet.description": "Description (optional)",
  "timesheet.date": "Date",
  "timesheet.work_date": "Work Date",
  "timesheet.time_history": "Time History",
  "timesheet.logged_by": "Logged by",
  "timesheet.no_time_logged": "No time logged yet",
  "timesheet.timer_running": "Timer running...",
  "timesheet.task_optional": "Task (optional)",
  "timesheet.error_active_timer_exists": "You already have an active timer. Stop it before starting a new one.",
  "timesheet.error_future_date": "Work date cannot be in the future.",
  "timesheet.confirm_delete": "Delete this time entry?",
  "timesheet.total_for_user": "Total for {user}",
  "timesheet.long_running_warning": "Timer has been running over 8 hours. Did you forget to stop it?"
}
```

---

## 5. Riesgos y Performance

### Concurrencia
- **Cache total**: `UPDATE SET col = col + delta` — atómico en InnoDB.
- **Doble click Start**: `lockForUpdate()` + check de timer activo en transacción.
- **Timer huérfano** (browser cerrado): timer queda activo en DB. Frontend reanuda al cargar. Alerta si > 8h.

### Solapamiento
- Máximo 1 timer activo por usuario (globalmente).
- Validado en `TimeLogService::startTimer()`.

### Precisión
- Almacenar en **segundos** (máxima precisión).
- UI Header: redondear a minutos.
- Reportes: política `round()` al minuto más cercano.

### Zonas horarias
- `started_at` / `ended_at` / `created_at`: UTC.
- `work_date`: DATE puro calculado en frontend según tz del usuario.

---

## 6. Checklist de Implementación

### Fase 1 — Base de Datos
- [x] **M1**: `create_time_logs_table` — tabla completa con índices
- [x] **M2**: `add_total_time_spent_to_immigration_cases` — campo cacheado
- [x] **M3**: `add_timesheet_permissions` — permisos Spatie + asignación a roles
- [x] **M4**: `fix_time_logs_case_task_fk_to_tasks` — corrige FK: `case_task_id` apunta a `tasks` (modelo `Task`), no a `case_tasks` (modelo `CaseTask` que es checklist). `cases/show.vue` expone IDs de `tasks`.

### Fase 2 — Backend Laravel
- [x] **B1**: `app/Models/TimeLog.php`
- [x] **B2**: Actualizar `app/Models/ImmigrationCase.php` — relación, accessor, fillable
- [x] **B3**: Actualizar `app/Models/CaseTask.php` — relación `timeLogs()`
- [x] **B4**: Actualizar `app/Models/Todo.php` — relación `timeLogs()`
- [x] **B5**: `app/Observers/TimeLogObserver.php`
- [x] **B6**: Registrar observer en `AppServiceProvider::boot()`
- [x] **B7**: `app/Services/Timesheet/TimeLogService.php`
- [x] **B8**: `app/Http/Controllers/Api/TimeLogController.php`
- [x] **B9**: `app/Http/Resources/TimeLogResource.php`
- [x] **B10**: Actualizar `app/Http/Resources/CaseResource.php`
- [x] **B11**: `app/Http/Requests/StoreTimeLogRequest.php`
- [x] **B12**: `app/Http/Requests/UpdateTimeLogRequest.php`
- [x] **B13**: Registrar rutas en `routes/api.php`

### Fase 3 — Frontend Vue
- [x] **F1**: `resources/js/src/types/timesheet.ts`
- [x] **F2**: `resources/js/src/utils/timeFormat.ts`
- [x] **F3**: `resources/js/src/services/timesheetService.ts`
- [x] **F4**: `resources/js/src/stores/useTimesheetStore.ts`
- [x] **F5**: `resources/js/src/composables/useActiveTimer.ts`
- [x] **F6**: `resources/js/src/components/timesheet/TimeDisplay.vue`
- [x] **F7**: `resources/js/src/components/timesheet/TimeLogModal.vue`
- [x] **F8**: `resources/js/src/components/timesheet/TimeLogList.vue`
- [x] **F9**: Actualizar `resources/js/src/views/cases/show.vue`
- [x] **F10**: Actualizar modal/edit de [[CaseTask]] — tab "Tiempo"
- [x] **F11**: Actualizar `resources/js/src/views/apps/todolist.vue`
- [x] **F12**: Actualizar `useUserStore` — invocar `timesheetStore.fetchActiveTimer()` post-login
- [x] **F13**: Agregar i18n keys en los 16 locale files

### Fase 4 — Cierre
- [x] **D1**: Actualizar `CLAUDE.md` — agregar `TimeLog` a modelos y `TimeLogObserver` a observers
- [ ] **D2**: Verificar `migrate:fresh --seed` funcional — pendiente de ejecución manual
