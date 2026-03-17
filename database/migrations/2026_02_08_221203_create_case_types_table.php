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
            $table->string('code', 10)->unique();
            $table->enum('category', ['temporary_residence', 'permanent_residence', 'refugee', 'citizenship']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
        });

        // Default global case types (tenant_id = null)
        DB::table('case_types')->insert([
            // Temporary Residence
            ['name' => 'Tourist Visa',              'code' => 'TO', 'category' => 'temporary_residence', 'description' => 'tourist-description',  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Student Visa',              'code' => 'ST', 'category' => 'temporary_residence', 'description' => 'student-description',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Work Permit',               'code' => 'WK', 'category' => 'temporary_residence', 'description' => 'work-description',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'VISA EMIT',                 'code' => 'EM', 'category' => 'temporary_residence', 'description' => 'emit-description',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Visitor Record',            'code' => 'FV', 'category' => 'temporary_residence', 'description' => 'fv-description',        'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Solicitud de Permiso PST',  'code' => 'PS', 'category' => 'temporary_residence', 'description' => 'psst-description',      'created_at' => now(), 'updated_at' => now()],
            // Permanent Residence
            ['name' => 'Express Entry',             'code' => 'EE', 'category' => 'permanent_residence', 'description' => 'expressEntry-description',  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ARRIMA Programme',          'code' => 'AR', 'category' => 'permanent_residence', 'description' => 'arrima-description',        'created_at' => now(), 'updated_at' => now()],
            ['name' => 'PEQ',                       'code' => 'PQ', 'category' => 'permanent_residence', 'description' => 'peq-description',           'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pilot Program',             'code' => 'PI', 'category' => 'permanent_residence', 'description' => 'pilot-description',         'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Skilled Worker',            'code' => 'SW', 'category' => 'permanent_residence', 'description' => 'skilledWorker-description', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sponsorship',               'code' => 'SP', 'category' => 'permanent_residence', 'description' => 'sponsorship-description',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Humanitario',               'code' => 'HU', 'category' => 'permanent_residence', 'description' => 'humanitarian-description',  'created_at' => now(), 'updated_at' => now()],
            // Refugee / Asylum
            ['name' => 'Refugee/Asylum',            'code' => 'AS', 'category' => 'refugee', 'description' => 'refugee-description',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Appeal',                    'code' => 'AP', 'category' => 'refugee', 'description' => 'appeal-description',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Federal Court',             'code' => 'FC', 'category' => 'refugee', 'description' => 'federalCourt-description','created_at' => now(), 'updated_at' => now()],
            ['name' => 'ERAR',                      'code' => 'ER', 'category' => 'refugee', 'description' => 'erar-description',        'created_at' => now(), 'updated_at' => now()],
            // Citizenship
            ['name' => 'Citizenship',               'code' => 'CZ', 'category' => 'citizenship', 'description' => 'citizenship-description',            'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Citizenship Certificate',   'code' => 'CC', 'category' => 'citizenship', 'description' => 'citizenshipCertificate-description', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Passport',                  'code' => 'PA', 'category' => 'citizenship', 'description' => 'passport-description',              'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Travel Document',           'code' => 'TD', 'category' => 'citizenship', 'description' => 'travelDocument-description',        'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('case_types');
    }
};
