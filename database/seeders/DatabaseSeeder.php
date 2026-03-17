<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolePermissionSeeder::class);

        // Seed tenants
        $this->call(TenantSeeder::class);

        // Seed global case types
        $this->call(CaseTypeSeeder::class);

        // Create admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@vite-it.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@vite-it.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // Create consultor user if not exists
        $editor = User::firstOrCreate(
            ['email' => 'consultor@vite-it.com'],
            [
                'name' => 'Consultor User',
                'email' => 'consultor@vite-it.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $editor->assignRole('consultor');

        // Create apoyo user if not exists
        $user = User::firstOrCreate(
            ['email' => 'apoyo@vite-it.com'],
            [
                'name' => 'Apoyo User',
                'email' => 'apoyo@vite-it.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('apoyo');
    }
}
