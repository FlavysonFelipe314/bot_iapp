<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained()->onDelete('cascade');
            $table->string('contact');
            $table->text('trigger_message');
            $table->json('execution_result')->nullable();
            $table->timestamps();

            $table->index(['flow_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_executions');
    }
};

