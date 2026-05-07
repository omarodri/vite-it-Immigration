<?php

namespace App\Console\Commands;

use Database\Seeders\DefaultWorkflowsSeeder;
use Illuminate\Console\Command;

class SeedWorkflowDefaults extends Command
{
    protected $signature = 'workflow:seed-defaults {tenant_id? : Seed only this tenant (omit to seed all tenants)}';

    protected $description = 'Seed default workflow stages and task templates. Skips existing stages and never modifies case_types.';

    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');

        $seeder = new DefaultWorkflowsSeeder();
        $seeder->setCommand($this);

        if ($tenantId) {
            $this->info("Seeding workflows for tenant #{$tenantId}…");
            $seeder->seedForTenant((int) $tenantId);
        } else {
            $this->info('Seeding workflows for all tenants…');
            $seeder->run();
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
