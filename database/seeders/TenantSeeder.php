<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default tenant
        $defaultTenant = Tenant::firstOrCreate(
            ['slug' => 'vite-it-demo'],
            [
                'name' => 'VITE-IT Demo Consultancy',
                'settings' => [
                    'logo_url' => null,
                    'primary_color' => '#4361ee',
                    'company_name' => 'VITE-IT Demo Consultancy',
                    'company_email' => 'demo@vite-it.com',
                    'company_phone' => '+1 (514) 555-0100',
                    'address' => '123 Immigration Blvd, Montreal, QC',
                ],
                'is_active' => true,
            ]
        );

        // Assign existing users without tenant to the default tenant
        User::whereNull('tenant_id')->update(['tenant_id' => $defaultTenant->id]);

        $this->command->info("Default tenant created: {$defaultTenant->name}");
        $this->command->info("Users assigned to default tenant: " . User::where('tenant_id', $defaultTenant->id)->count());
    }
}
