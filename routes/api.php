<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CaseInvoiceController;
use App\Http\Controllers\Api\CaseStageController;
use App\Http\Controllers\Api\CaseTaskController;
use App\Http\Controllers\Api\CaseTypeController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanionController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentFolderController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TimeLogController;
use App\Http\Controllers\Api\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Api\Admin\TaskTemplateController as AdminTaskTemplateController;
use App\Http\Controllers\Api\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Api\Admin\WorkflowStageController as AdminWorkflowStageController;
use App\Http\Controllers\Api\OAuthFlowController;
use App\Http\Controllers\Api\TenantOAuthController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\ScrumBoardController;
use App\Http\Controllers\Api\ScrumColumnController;
use App\Http\Controllers\Api\ScrumTaskController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExpirationAlertController;
use App\Http\Controllers\Api\LegalDocumentController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\UserCalendarOAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes with rate limiting
Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge']);
});

Route::middleware('throttle:register')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});

// Password reset routes with rate limiting
Route::middleware('throttle:password-reset')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

Route::get('/verify-token/{token}/{email}', [PasswordResetController::class, 'verifyToken']);

// Email verification (signed URL - no auth required)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:email-verification'])
    ->name('verification.verify');

// Protected routes (authentication required) with API rate limiting and tenant scope
Route::middleware(['auth:sanctum', 'throttle:api', 'tenant'])->group(function () {
    // Global Search
    Route::get('/search', GlobalSearchController::class)->middleware('throttle:60,1');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Email verification routes (auth required)
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:email-verification')
        ->name('verification.send');
    Route::get('/email/verification-status', [EmailVerificationController::class, 'status']);

    // Profile management routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('/profile/password', [ProfileController::class, 'changePassword']);

    // User management routes
    Route::get('/users/staff', [UserController::class, 'staff']);
    Route::apiResource('users', UserController::class);
    Route::delete('/users/bulk', [UserController::class, 'bulkDestroy']);

    // Client management routes
    Route::get('/clients/statistics', [ClientController::class, 'statistics']);
    Route::delete('/clients/bulk', [ClientController::class, 'bulkDestroy']);
    Route::post('/clients/{client}/convert', [ClientController::class, 'convert']);
    Route::apiResource('clients', ClientController::class);

    // Companion management routes (nested under clients)
    Route::apiResource('clients.companions', CompanionController::class);

    // Case Types routes (read-only)
    Route::get('/case-types', [CaseTypeController::class, 'index']);
    Route::get('/case-types/{caseType}', [CaseTypeController::class, 'show']);

    // Case management routes
    Route::get('/cases/statistics', [CaseController::class, 'statistics']);
    Route::get('/cases/workflow-preview/{caseTypeId}', [CaseController::class, 'workflowPreview'])
        ->where('caseTypeId', '[0-9]+');
    Route::apiResource('cases', CaseController::class);
    Route::post('/cases/{case}/assign', [CaseController::class, 'assign']);
    Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline']);
    Route::patch('/cases/{case}/advance-stage', [CaseController::class, 'advanceStage']);

    // Case Tasks routes (legacy case_tasks table)
    Route::put('/cases/{case}/tasks', [CaseTaskController::class, 'bulkUpdate']);
    Route::patch('/cases/{case}/tasks/{task}/toggle', [CaseTaskController::class, 'toggle']);

    // Case Stages routes (per-case workflow stages)
    Route::get   ('/cases/{case}/stages',         [CaseStageController::class, 'index']);
    Route::post  ('/cases/{case}/stages',         [CaseStageController::class, 'store']);
    Route::patch ('/cases/{case}/stages/reorder', [CaseStageController::class, 'reorder']);
    Route::patch ('/cases/{case}/stages/{stage}', [CaseStageController::class, 'update']);
    Route::delete('/cases/{case}/stages/{stage}', [CaseStageController::class, 'destroy']);

    // Workflow Tasks routes (new tasks table)
    Route::get('/cases/{case}/workflow-tasks/trashed', [CaseTaskController::class, 'listTrashedWorkflowTasks']);
    Route::post('/cases/{case}/workflow-tasks', [CaseTaskController::class, 'createWorkflowTask']);
    Route::patch('/cases/{case}/workflow-tasks/reorder', [CaseTaskController::class, 'reorderWorkflowTasks']);
    Route::post('/cases/{case}/workflow-tasks/{workflowTaskId}/restore', [CaseTaskController::class, 'restoreWorkflowTask'])->whereNumber('workflowTaskId');
    Route::patch('/cases/{case}/workflow-tasks/{workflowTask}/toggle', [CaseTaskController::class, 'toggleWorkflowTask']);
    Route::patch('/cases/{case}/workflow-tasks/{workflowTask}/move', [CaseTaskController::class, 'moveWorkflowTask']);
    Route::delete('/cases/{case}/workflow-tasks/{workflowTask}', [CaseTaskController::class, 'deleteWorkflowTask']);
    Route::patch('/cases/{case}/workflow-tasks/{workflowTask}/update-core', [CaseTaskController::class, 'updateWorkflowTaskCore']);
    Route::post('/cases/{case}/workflow-tasks/{workflowTask}/clone', [CaseTaskController::class, 'cloneWorkflowTask']);

    // Case Invoices routes
    Route::put('/cases/{case}/invoices', [CaseInvoiceController::class, 'bulkUpdate']);

    // Case Document Folders routes
    Route::get('/cases/{case}/folders', [DocumentFolderController::class, 'index']);
    Route::post('/cases/{case}/folders', [DocumentFolderController::class, 'store']);
    Route::patch('/cases/{case}/folders/{folder}', [DocumentFolderController::class, 'update']);
    Route::delete('/cases/{case}/folders/{folder}', [DocumentFolderController::class, 'destroy']);
    Route::post('/cases/{case}/folders/initialize', [DocumentFolderController::class, 'initialize']);
    Route::post('/cases/{case}/folders/sync', [DocumentFolderController::class, 'sync']);
    Route::get('/cases/{case}/folders/sync-status', [DocumentFolderController::class, 'syncStatus']);

    // Case Documents routes
    Route::get('/cases/{case}/documents', [DocumentController::class, 'index']);
    Route::post('/cases/{case}/documents/sync-from-cloud', [DocumentController::class, 'syncFromCloud']);
    Route::get('/cases/{case}/documents/{document}', [DocumentController::class, 'show']);
    Route::patch('/cases/{case}/documents/{document}', [DocumentController::class, 'update']);
    Route::delete('/cases/{case}/documents/{document}', [DocumentController::class, 'destroy']);
    Route::post('/cases/{case}/documents/{document}/move', [DocumentController::class, 'move']);
    Route::get('/cases/{case}/documents/{document}/preview', [DocumentController::class, 'preview']);

    // Document upload routes (stricter rate limit: 20/min)
    Route::middleware('throttle:uploads')->group(function () {
        Route::post('/cases/{case}/documents', [DocumentController::class, 'store']);
        Route::post('/cases/{case}/documents/{document}/replace', [DocumentController::class, 'replace']);
    });

    // Document download routes (rate limit: 60/min)
    Route::middleware('throttle:downloads')->group(function () {
        Route::get('/cases/{case}/documents/{document}/download', [DocumentController::class, 'download']);
    });

    // Event Calendar routes
    Route::get('/events/assignees', [EventController::class, 'assignees']);
    Route::patch('/events/{event}/reschedule', [EventController::class, 'reschedule']);
    Route::post('/events/{event}/clone', [EventController::class, 'clone']);
    Route::apiResource('events', EventController::class);

    // Todo List routes
    Route::delete('/todos/bulk', [TodoController::class, 'bulkDestroy']);
    Route::patch('/todos/{todo}/status', [TodoController::class, 'updateStatus']);
    Route::apiResource('todos', TodoController::class);

    // Legal Documents routes
    Route::get('/clients/{client}/documents', [LegalDocumentController::class, 'indexForClient']);
    Route::post('/clients/{client}/documents', [LegalDocumentController::class, 'storeForClient']);
    Route::get('/companions/{companion}/documents', [LegalDocumentController::class, 'indexForCompanion']);
    Route::post('/companions/{companion}/documents', [LegalDocumentController::class, 'storeForCompanion']);
    Route::put('/legal-documents/{document}', [LegalDocumentController::class, 'update']);
    Route::delete('/legal-documents/{document}', [LegalDocumentController::class, 'destroy']);
    Route::post('/legal-documents/{document}/sync-event', [LegalDocumentController::class, 'syncToCalendar']);

    // Expiration Alerts routes
    Route::get('/expiration-alerts', [ExpirationAlertController::class, 'index']);

    // Role management routes
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::get('/permissions', [RoleController::class, 'permissions']);

    // Activity log routes
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/{activity}', [ActivityLogController::class, 'show']);

    // Two-factor authentication management routes
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable']);
    Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);

    // Tenant management routes
    Route::get('/tenant', [TenantController::class, 'show']);
    Route::put('/tenant/settings', [TenantController::class, 'updateSettings']);
    Route::put('/tenant/branding', [TenantController::class, 'updateBranding']);
    Route::post('/tenant/branding/logo', [TenantController::class, 'uploadLogo']);
    Route::delete('/tenant/branding/logo', [TenantController::class, 'deleteLogo']);
    Route::put('/tenant/storage-type', [TenantController::class, 'updateStorageType']);
    Route::put('/tenant/theme', [TenantController::class, 'updateTheme']);
    Route::get('/tenant/sharepoint/sites', [TenantController::class, 'listSharePointSites']);
    Route::get('/tenant/sharepoint/sites/{siteId}/drives', [TenantController::class, 'listSharePointDrives'])->where('siteId', '.*');
    Route::get('/tenant/sharepoint/drives', [TenantController::class, 'listSharePointDrivesByQuery']);
    Route::put('/tenant/sharepoint/config', [TenantController::class, 'saveSharePointConfig']);
    Route::put('/tenant/base-folder', [TenantController::class, 'updateBaseFolder']);

    // Scrum Board routes
    Route::prefix('scrum')->group(function () {
        Route::get('/board', [ScrumBoardController::class, 'index']);
        Route::get('/assignees', [ScrumBoardController::class, 'assignees']);

        Route::post('/columns', [ScrumColumnController::class, 'store']);
        Route::patch('/columns/reorder', [ScrumColumnController::class, 'reorder']);
        Route::patch('/columns/{scrumColumn}', [ScrumColumnController::class, 'update']);
        Route::delete('/columns/{scrumColumn}', [ScrumColumnController::class, 'destroy']);

        Route::post('/tasks', [ScrumTaskController::class, 'store']);
        Route::get('/tasks/{scrumTask}', [ScrumTaskController::class, 'show']);
        Route::put('/tasks/{scrumTask}', [ScrumTaskController::class, 'update']);
        Route::delete('/tasks/{scrumTask}', [ScrumTaskController::class, 'destroy']);
        Route::patch('/tasks/{scrumTask}/move', [ScrumTaskController::class, 'move']);
        Route::patch('/tasks/{scrumTask}/toggle', [ScrumTaskController::class, 'toggle']);
    });

    // Tenant OAuth credentials routes
    Route::get('/tenant/oauth/status', [TenantOAuthController::class, 'status']);
    Route::put('/tenant/oauth/microsoft', [TenantOAuthController::class, 'updateMicrosoft']);
    Route::put('/tenant/oauth/google', [TenantOAuthController::class, 'updateGoogle']);
    Route::delete('/tenant/oauth/microsoft', [TenantOAuthController::class, 'removeMicrosoft']);
    Route::delete('/tenant/oauth/google', [TenantOAuthController::class, 'removeGoogle']);

    // User-level OAuth flow routes (storage)
    Route::get('/oauth/{provider}/redirect', [OAuthFlowController::class, 'redirect'])
        ->where('provider', 'microsoft|google');
    Route::get('/oauth/status', [OAuthFlowController::class, 'status']);
    Route::delete('/oauth/{provider}/disconnect', [OAuthFlowController::class, 'disconnect'])
        ->where('provider', 'microsoft|google');

    // User-level Calendar OAuth routes
    Route::prefix('calendar-oauth')->group(function () {
        Route::get('/status', [UserCalendarOAuthController::class, 'status']);
        Route::post('/pull', [UserCalendarOAuthController::class, 'pull']);
        Route::get('/{provider}/redirect', [UserCalendarOAuthController::class, 'redirect'])
            ->whereIn('provider', ['google', 'microsoft']);
        Route::post('/{provider}/disconnect', [UserCalendarOAuthController::class, 'disconnect'])
            ->whereIn('provider', ['google', 'microsoft']);
    });

    // Timesheet
    Route::prefix('cases/{case}')->group(function () {
        Route::get('time-logs',              [TimeLogController::class, 'index']);
        Route::post('time-logs',             [TimeLogController::class, 'store']);
        Route::post('time-logs/start',       [TimeLogController::class, 'start']);
        Route::patch('time-logs/{log}/stop', [TimeLogController::class, 'stop']);
        Route::put('time-logs/{log}',        [TimeLogController::class, 'update']);
        Route::delete('time-logs/{log}',     [TimeLogController::class, 'destroy']);
    });
    Route::get('me/active-timer', [TimeLogController::class, 'activeTimer']);

    // Trash / Recycle Bin
    Route::prefix('trash')->group(function () {
        Route::get('/', [TrashController::class, 'summary']);
        Route::get('/{type}', [TrashController::class, 'index'])
            ->where('type', 'clients|cases|companions|documents');
        Route::patch('/{type}/{id}/restore-with-parents', [TrashController::class, 'restoreWithParents'])
            ->where('type', 'clients|cases|companions|documents');
        Route::patch('/{type}/{id}/restore', [TrashController::class, 'restore'])
            ->where('type', 'clients|cases|companions|documents');
        Route::delete('/purge', [TrashController::class, 'purge']);
        Route::delete('/{type}/{id}', [TrashController::class, 'forceDelete'])
            ->where('type', 'clients|cases|companions|documents');
    });
});

// Super-admin tenant management routes
Route::prefix('admin/tenants')->middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('/', [AdminTenantController::class, 'index']);
    Route::post('/', [AdminTenantController::class, 'store']);
    Route::get('/stats', [AdminTenantController::class, 'stats']);
    Route::get('/{id}', [AdminTenantController::class, 'show'])->where('id', '[0-9]+');
    Route::put('/{id}', [AdminTenantController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/{id}', [AdminTenantController::class, 'destroy'])->where('id', '[0-9]+');
    Route::post('/{id}/activate', [AdminTenantController::class, 'activate'])->where('id', '[0-9]+');
});

// Backup routes — UI (admin)
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin|super-admin'])->group(function () {
    Route::post('/tenants/{id}/backup', [AdminBackupController::class, 'run'])->where('id', '[0-9]+');
    Route::get('/tenants/{id}/backups', [AdminBackupController::class, 'index'])->where('id', '[0-9]+');
    Route::get('/backups/{logId}/download', [AdminBackupController::class, 'download'])->where('logId', '[0-9]+');
    Route::delete('/backups/{logId}', [AdminBackupController::class, 'destroy'])->where('logId', '[0-9]+');
});

// Backup external endpoint — CRON/Job (API Key auth)
Route::post('/admin/backup/run', [AdminBackupController::class, 'runExternal'])
    ->middleware(['backup.key']);

// Workflow management routes (admin)
Route::prefix('admin/workflow')
    ->middleware(['auth:sanctum', 'tenant', 'permission:workflows.view'])
    ->group(function () {
        Route::get('/case-types/{caseType}/stages', [AdminWorkflowStageController::class, 'index']);
        Route::post('/case-types/{caseType}/stages', [AdminWorkflowStageController::class, 'store']);
        Route::patch('/case-types/{caseType}/stages/reorder', [AdminWorkflowStageController::class, 'reorder']);
        Route::put('/stages/{stage}', [AdminWorkflowStageController::class, 'update']);
        Route::delete('/stages/{stage}', [AdminWorkflowStageController::class, 'destroy']);

        Route::get('/stages/{stage}/templates', [AdminTaskTemplateController::class, 'index']);
        Route::post('/stages/{stage}/templates', [AdminTaskTemplateController::class, 'store']);
        Route::patch('/stages/{stage}/templates/reorder', [AdminTaskTemplateController::class, 'reorder']);
        Route::put('/templates/{template}', [AdminTaskTemplateController::class, 'update']);
        Route::delete('/templates/{template}', [AdminTaskTemplateController::class, 'destroy']);
    });

// OAuth callback route (no auth required - called by OAuth provider redirect)
Route::middleware('throttle:login')->get('/oauth/{provider}/callback', [OAuthFlowController::class, 'callback'])
    ->where('provider', 'microsoft|google');

// Calendar OAuth callback (no auth - called by OAuth provider)
Route::middleware('throttle:login')->get('/calendar-oauth/{provider}/callback', [UserCalendarOAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'microsoft']);

// Public tenant branding route (no auth required)
Route::get('/tenant/{slug}/branding', [TenantController::class, 'branding']);
