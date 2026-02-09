<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('category', ['temporary_residence', 'permanent_residence', 'humanitarian']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
        });

        // Seed default case types
        DB::table('case_types')->insert([
            // Temporary Residence
            ['name' => 'Tourist Visa', 'code' => 'TOURIST', 'category' => 'temporary_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Student Visa', 'code' => 'STUDENT', 'category' => 'temporary_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Work Permit', 'code' => 'WORK', 'category' => 'temporary_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'EMIT', 'code' => 'EMIT', 'category' => 'temporary_residence', 'created_at' => now(), 'updated_at' => now()],
            // Permanent Residence
            ['name' => 'Express Entry', 'code' => 'EXPRESS_ENTRY', 'category' => 'permanent_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ARRIMA', 'code' => 'ARRIMA', 'category' => 'permanent_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PEQ', 'code' => 'PEQ', 'category' => 'permanent_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pilot Program', 'code' => 'PILOT', 'category' => 'permanent_residence', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Skilled Worker', 'code' => 'SKILLED_WORKER', 'category' => 'permanent_residence', 'created_at' => now(), 'updated_at' => now()],
            // Humanitarian
            ['name' => 'Refugee/Asylum', 'code' => 'ASYLUM', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Asylum Claim', 'code' => 'ASYLUM_CLAIM', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Appeal', 'code' => 'APPEAL', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Federal Court', 'code' => 'FEDERAL_COURT', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ERAR', 'code' => 'ERAR', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sponsorship', 'code' => 'SPONSORSHIP', 'category' => 'humanitarian', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('case_types');
    }
};
