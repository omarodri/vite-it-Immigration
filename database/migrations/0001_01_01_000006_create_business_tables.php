<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Clients ──────────────────────────────────────────────────────
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Personal
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nationality')->nullable();
            $table->string('second_nationality')->nullable();
            $table->string('language')->default('es');
            $table->string('second_language')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('passport_number')->nullable();
            $table->string('passport_country')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed', 'common_law', 'separated'])->nullable();
            $table->string('profession')->nullable();
            $table->text('description')->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_country_code', 6)->nullable()->default('+1');
            $table->string('secondary_phone')->nullable();
            $table->string('secondary_phone_country_code', 6)->nullable()->default('+1');

            // Legal status in Canada
            $table->enum('canada_status', [
                'asylum_seeker', 'refugee', 'temporary_resident', 'permanent_resident',
                'citizen', 'visitor', 'student', 'worker', 'other',
            ])->nullable();
            $table->date('status_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->enum('entry_point', ['airport', 'land_border', 'green_path'])->nullable();
            $table->string('iuc')->nullable();
            $table->string('work_permit_number')->nullable();
            $table->string('study_permit_number')->nullable();
            $table->date('permit_expiry_date')->nullable();
            $table->string('other_status_1')->nullable();
            $table->string('other_status_2')->nullable();

            // Admin
            $table->enum('status', ['prospect', 'active', 'inactive', 'archived'])->default('prospect');
            $table->boolean('is_primary_applicant')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
            $table->index(['tenant_id', 'passport_number']);
            $table->unique(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'phone']);
        });

        // ── Companions ───────────────────────────────────────────────────
        Schema::create('companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('relationship', 50)->default('other');
            $table->string('relationship_other')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('passport_number')->nullable();
            $table->string('passport_country')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('nationality')->nullable();
            $table->text('notes')->nullable();
            $table->string('iuc')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('phone_country_code', 6)->nullable()->default('+1');
            $table->string('canada_status', 50)->nullable();
            $table->string('canada_status_other')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
        });

        // ── Case Types ───────────────────────────────────────────────────
        Schema::create('case_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->enum('category', ['temporary_residence', 'permanent_residence', 'refugee', 'citizenship']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
        });

        // ── Cases ────────────────────────────────────────────────────────
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('case_number')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Status
            $table->enum('status', ['active', 'inactive', 'archived', 'closed'])->default('active');
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('language')->default('es');

            // Tracking
            $table->string('stage', 50)->nullable();
            $table->string('ircc_status', 50)->nullable();
            $table->string('final_result', 20)->nullable();
            $table->string('ircc_code', 50)->nullable();
            $table->text('description')->nullable();

            // Financial / Admin
            $table->string('archive_box_number')->nullable();
            $table->string('contract_number', 50)->nullable();
            $table->string('service_type', 20)->default('fee_based');
            $table->decimal('fees', 10, 2)->nullable();

            // Closure
            $table->dateTime('closed_at')->nullable();
            $table->text('closure_notes')->nullable();

            // Cloud folder sync
            $table->string('root_external_folder_id')->nullable();
            $table->enum('folder_sync_status', ['pending', 'synced', 'failed', 'not_applicable'])->default('not_applicable');
            $table->timestamp('folder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'ircc_status']);
            $table->index(['tenant_id', 'service_type']);
        });

        // ── Document Folders ─────────────────────────────────────────────
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('category')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_url', 512)->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'case_id']);
            $table->index(['external_id', 'tenant_id']);
        });

        // ── Documents ────────────────────────────────────────────────────
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('document_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->enum('category', ['admission', 'history', 'evidence', 'hearing', 'contract', 'other'])->default('other');
            $table->enum('storage_type', ['local', 'onedrive', 'google_drive'])->default('local');
            $table->string('storage_path')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('checksum', 64)->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->enum('scan_status', ['pending', 'clean', 'infected', 'error'])->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'category']);
            $table->index(['storage_type', 'external_id']);
        });

        // ── Tasks (created after documents for FK) ───────────────────────
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('type', ['translation', 'case_creation', 'accounting', 'filing', 'document', 'other'])->default('other');
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['new', 'assigned', 'in_progress', 'rejected', 'resolved', 'closed'])->default('new');
            $table->dateTime('due_date')->nullable();
            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->decimal('actual_hours', 5, 2)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'priority']);
        });

        // ── Follow-ups ───────────────────────────────────────────────────
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['phone', 'email', 'meeting', 'video_call', 'other'])->default('phone');
            $table->enum('type', ['task', 'follow_up', 'note'])->default('follow_up');
            $table->dateTime('contact_date');
            $table->decimal('duration_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'contact_date']);
        });

        // ── Events ───────────────────────────────────────────────────────
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('client_name_snapshot')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->enum('sync_source', ['local', 'outlook', 'google'])->default('local');
            $table->string('external_id')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'start_date']);
            $table->index(['tenant_id', 'created_by']);
            $table->index(['sync_source', 'external_id']);
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        // ── OAuth Tokens ─────────────────────────────────────────────────
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['microsoft', 'google']);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->dateTime('expires_at');
            $table->json('scopes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['tenant_id', 'provider']);
        });

        // ── Task Time Entries ────────────────────────────────────────────
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 5, 2);
            $table->date('work_date');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'task_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'work_date']);
        });

        // ── Case Companions (pivot) ──────────────────────────────────────
        Schema::create('case_companions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('companion_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['case_id', 'companion_id']);
        });

        // ── Case Important Dates ─────────────────────────────────────────
        Schema::create('case_important_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('case_id');
            $table->index('due_date');
        });

        // ── Case Tasks ───────────────────────────────────────────────────
        Schema::create('case_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('label', 150);
            $table->boolean('is_completed')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('case_id');
            $table->index(['case_id', 'is_completed']);
        });

        // ── Case Invoices ────────────────────────────────────────────────
        Schema::create('case_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_collected')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('case_id');
            $table->index(['case_id', 'is_collected']);
        });

        // ── Invitation Codes ─────────────────────────────────────────────
        Schema::create('invitation_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->unsignedInteger('uses_remaining')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('email');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_codes');
        Schema::dropIfExists('case_invoices');
        Schema::dropIfExists('case_tasks');
        Schema::dropIfExists('case_important_dates');
        Schema::dropIfExists('case_companions');
        Schema::dropIfExists('task_time_entries');
        Schema::dropIfExists('oauth_tokens');
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('events');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('case_types');
        Schema::dropIfExists('companions');
        Schema::dropIfExists('clients');
    }
};
