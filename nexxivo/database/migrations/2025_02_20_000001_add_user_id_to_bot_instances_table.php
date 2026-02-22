<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bot_instances', 'user_id')) {
            return;
        }
        Schema::table('bot_instances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_instances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
