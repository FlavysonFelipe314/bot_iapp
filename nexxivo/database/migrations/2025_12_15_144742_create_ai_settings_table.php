<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Inserir configurações padrão
        DB::table('ai_settings')->insert([
            ['key' => 'default_provider', 'value' => 'ollama', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ollama_url', 'value' => 'http://localhost:11434', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ollama_model', 'value' => 'llama2', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'gemini_api_key', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'gemini_model', 'value' => 'gemini-pro', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'elevenlabs_api_key', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'elevenlabs_voice_id', 'value' => 'JBFqnCBsd6RMkjVDRZzb', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'elevenlabs_model', 'value' => 'eleven_multilingual_v2', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
