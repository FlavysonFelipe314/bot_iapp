<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->string('instance_name');
            $table->string('message_id')->unique();
            $table->string('from');
            $table->string('to')->nullable();
            $table->text('message');
            $table->enum('direction', ['incoming', 'outgoing'])->default('incoming');
            $table->json('raw_data')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['instance_name', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

