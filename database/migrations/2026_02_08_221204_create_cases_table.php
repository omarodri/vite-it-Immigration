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

            // Description
            $table->text('description')->nullable();

            // Key Dates
            $table->datetime('hearing_date')->nullable()->comment('Fecha de audiencia');
            $table->datetime('fda_deadline')->nullable()->comment('Plazo para deposito FDA');
            $table->datetime('brown_sheet_date')->nullable()->comment('Fecha hoja marron');
            $table->datetime('evidence_deadline')->nullable()->comment('Plazo envio doc de pruebas');

            // Archive Info
            $table->string('archive_box_number')->nullable()->comment('Nro caja de archivo');

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
            $table->index(['tenant_id', 'hearing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cases');
    }
};
