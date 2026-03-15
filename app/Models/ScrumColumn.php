<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrumColumn extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'title', 'order_index'];

    public function tasks(): HasMany
    {
        return $this->hasMany(ScrumTask::class)->orderBy('order_index');
    }
}
