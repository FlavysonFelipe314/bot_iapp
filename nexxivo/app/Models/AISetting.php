<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AISetting extends Model
{
    protected $table = 'ai_settings';
    
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Obter valor de uma configuração
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Definir valor de uma configuração
     */
    public static function set(string $key, $value)
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
