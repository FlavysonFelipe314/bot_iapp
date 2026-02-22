<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotInstance extends Model
{
    protected $fillable = [
        'user_id',
        'instance_name',
        'status',
        'qrcode',
        'qrcode_generated_at',
        'pairing_code',
    ];

    protected $casts = [
        'qrcode_generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

