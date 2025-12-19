<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowExecution extends Model
{
    protected $fillable = [
        'flow_id',
        'contact',
        'trigger_message',
        'execution_result',
    ];

    protected $casts = [
        'execution_result' => 'array',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}

