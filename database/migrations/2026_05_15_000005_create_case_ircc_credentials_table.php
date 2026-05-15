<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Spec 60 — IRCC Credentials Vault.
     *
     * Stores encrypted IRCC portal credentials per case. All sensitive payload
     * columns are TEXT to accommodate Laravel's "encrypted" Eloquent cast
     * output (base64 + IV + HMAC), which is significantly larger than the
     * plaintext value and frequently exceeds VARCHAR(255).
     *
     * Foreign key target is `cases` (physical table) — the Eloquent model
     * ImmigrationCase maps to that table (see CLAUDE.md gotcha G11).
     */
    public function up(): void
    {
        Schema::create('case_ircc_credentials', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('case_id');

            // Encrypted sensitive fields (stored as ciphertext — TEXT required)
            $table->text('ircc_username')->nullable();
            $table->text('ircc_password')->nullable();
            $table->text('email_address')->nullable();
            $table->text('email_password')->nullable();
            $table->text('application_number')->nullable();
            $table->text('notes')->nullable();
            $table->text('security_questions')->nullable(); // encrypted:array stores ciphertext, not JSON — must be TEXT (G13)

            // Crypto key rotation marker
            $table->unsignedTinyInteger('key_version')->default(1);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique('case_id', 'case_ircc_credentials_case_id_unique');

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');

            $table->foreign('case_id')
                ->references('id')->on('cases')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            // Composite index for tenant-scoped lookups
            $table->index(['tenant_id', 'case_id'], 'idx_tenant_case');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_ircc_credentials');
    }
};
