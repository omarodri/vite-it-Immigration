<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_folders')->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'case_id']);
        });

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
            $table->string('external_id')->nullable()->comment('ID in OneDrive/Google Drive');
            $table->string('external_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'case_id']);
            $table->index(['tenant_id', 'category']);
            $table->index(['storage_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
    }
};
