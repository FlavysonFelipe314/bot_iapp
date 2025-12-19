<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotInstance extends Model
{
    protected $fillable = [
        'instance_name',
        'status',
        'qrcode',
        'qrcode_generated_at',
    ];

    protected $casts = [
        'qrcode_generated_at' => 'datetime',
    ];
}

