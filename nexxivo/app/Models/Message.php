<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'instance_name',
        'message_id',
        'from',
        'to',
        'message',
        'direction',
        'raw_data',
        'timestamp',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'timestamp' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

