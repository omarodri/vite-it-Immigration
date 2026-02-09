<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Personal Information
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

            // Contact Information
            $table->string('email')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();

            // Legal Status in Canada
            $table->enum('canada_status', [
                'asylum_seeker',
                'refugee',
                'temporary_resident',
                'permanent_resident',
                'citizen',
                'visitor',
                'student',
                'worker',
                'other'
            ])->nullable();
            $table->date('status_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->enum('entry_point', ['airport', 'land_border', 'green_path'])->nullable();
            $table->string('iuc')->nullable()->comment('Unique Client Identifier');
            $table->string('work_permit_number')->nullable();
            $table->string('study_permit_number')->nullable();
            $table->date('permit_expiry_date')->nullable();
            $table->string('other_status_1')->nullable();
            $table->string('other_status_2')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_primary_applicant')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'passport_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
