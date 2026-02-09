<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip if no authenticated user
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Skip if user is super admin (can access all tenants)
        if ($user->hasRole('super-admin')) {
            return;
        }

        // Apply tenant filter
        if ($user->tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        }
    }
}
