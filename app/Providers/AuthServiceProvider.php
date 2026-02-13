<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Companion;
use App\Models\ImmigrationCase;
use App\Models\User;
use App\Policies\CasePolicy;
use App\Policies\ClientPolicy;
use App\Policies\CompanionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Client::class => ClientPolicy::class,
        Companion::class => CompanionPolicy::class,
        ImmigrationCase::class => CasePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Implicitly grant "admin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });
    }
}
