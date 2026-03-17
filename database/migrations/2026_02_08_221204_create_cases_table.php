<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('case_number')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Status & Priority
            $table->enum('status', ['active', 'inactive', 'archived', 'closed'])->default('active');
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
            $table->unsignedTinyInteger('progress')->default(0)->comment('0-100 percentage');
            $table->string('language')->default('es');

            // Operational tracking
            $table->string('stage', 50)->nullable()->default(null);
            $table->string('ircc_status', 50)->nullable()->default(null);
            $table->string('final_result', 20)->nullable()->default(null);
            $table->string('ircc_code', 50)->nullable()->default(null);

            // Description
            $table->text('description')->nullable();

            // Financial / Admin
            $table->string('archive_box_number')->nullable()->comment('Nro caja de archivo');
            $table->string('contract_number', 50)->nullable()->default(null);
            $table->string('service_type', 20)->default('fee_based');
            $table->decimal('fees', 10, 2)->nullable()->default(null);

            // Closure
            $table->datetime('closed_at')->nullable();
            $table->text('closure_notes')->nullable()->comment('Nota de cierre');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'client_id']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'stage']);
            $table->index(['tenant_id', 'ircc_status']);
            $table->index(['tenant_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
