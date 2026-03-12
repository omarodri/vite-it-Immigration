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

        // Create editor user if not exists
        $editor = User::firstOrCreate(
            ['email' => 'editor@vite-it.com'],
            [
                'name' => 'Editor User',
                'email' => 'editor@vite-it.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $editor->assignRole('editor');

        // Create regular user if not exists
        $user = User::firstOrCreate(
            ['email' => 'user@vite-it.com'],
            [
                'name' => 'Regular User',
                'email' => 'user@vite-it.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('user');
    }
}
