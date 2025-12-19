<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flow extends Model
{
    protected $fillable = [
        'name',
        'description',
        'triggers',
        'actions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'triggers' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
    ];

    public function executions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }
}

