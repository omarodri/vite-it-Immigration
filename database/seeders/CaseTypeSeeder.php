<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CaseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Temporary Residence
            ['name' => 'Tourist Visa',              'code' => 'TO', 'category' => 'temporary_residence', 'description' => 'tourist-description'],
            ['name' => 'Student Visa',              'code' => 'ST', 'category' => 'temporary_residence', 'description' => 'student-description'],
            ['name' => 'Work Permit',               'code' => 'WK', 'category' => 'temporary_residence', 'description' => 'work-description'],
            ['name' => 'VISA EMIT',                 'code' => 'EM', 'category' => 'temporary_residence', 'description' => 'emit-description'],
            ['name' => 'Visitor Record',            'code' => 'FV', 'category' => 'temporary_residence', 'description' => 'fv-description'],
            ['name' => 'Solicitud de Permiso PST',  'code' => 'PS', 'category' => 'temporary_residence', 'description' => 'psst-description'],
            // Permanent Residence
            ['name' => 'Express Entry',             'code' => 'EE', 'category' => 'permanent_residence', 'description' => 'expressEntry-description'],
            ['name' => 'ARRIMA Programme',          'code' => 'AR', 'category' => 'permanent_residence', 'description' => 'arrima-description'],
            ['name' => 'PEQ',                       'code' => 'PQ', 'category' => 'permanent_residence', 'description' => 'peq-description'],
            ['name' => 'Pilot Program',             'code' => 'PI', 'category' => 'permanent_residence', 'description' => 'pilot-description'],
            ['name' => 'Skilled Worker',            'code' => 'SW', 'category' => 'permanent_residence', 'description' => 'skilledWorker-description'],
            ['name' => 'Sponsorship',               'code' => 'SP', 'category' => 'permanent_residence', 'description' => 'sponsorship-description'],
            ['name' => 'Humanitario',               'code' => 'HU', 'category' => 'permanent_residence', 'description' => 'humanitarian-description'],
            // Refugee / Asylum
            ['name' => 'Refugee/Asylum',            'code' => 'AS', 'category' => 'refugee', 'description' => 'refugee-description'],
            ['name' => 'Appeal',                    'code' => 'AP', 'category' => 'refugee', 'description' => 'appeal-description'],
            ['name' => 'Federal Court',             'code' => 'FC', 'category' => 'refugee', 'description' => 'federalCourt-description'],
            ['name' => 'ERAR',                      'code' => 'ER', 'category' => 'refugee', 'description' => 'erar-description'],
            // Citizenship
            ['name' => 'Citizenship',               'code' => 'CZ', 'category' => 'citizenship', 'description' => 'citizenship-description'],
            ['name' => 'Citizenship Certificate',   'code' => 'CC', 'category' => 'citizenship', 'description' => 'citizenshipCertificate-description'],
            ['name' => 'Passport',                  'code' => 'PA', 'category' => 'citizenship', 'description' => 'passport-description'],
            ['name' => 'Travel Document',           'code' => 'TD', 'category' => 'citizenship', 'description' => 'travelDocument-description'],
        ];

        foreach ($types as $type) {
            DB::table('case_types')->updateOrInsert(
                ['code' => $type['code']],
                array_merge($type, ['tenant_id' => null, 'is_active' => true, 'updated_at' => now()])
            );
        }
    }
}
