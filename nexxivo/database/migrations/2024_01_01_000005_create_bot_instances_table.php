<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_instances', function (Blueprint $table) {
            $table->id();
            $table->string('instance_name')->unique();
            $table->enum('status', ['started', 'stopped', 'connected', 'disconnected'])->default('stopped');
            $table->text('qrcode')->nullable();
            $table->timestamp('qrcode_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_instances');
    }
};

