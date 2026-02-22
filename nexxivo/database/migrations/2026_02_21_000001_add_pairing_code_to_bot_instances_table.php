<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_instances', function (Blueprint $table) {
            $table->string('pairing_code', 32)->nullable()->after('qrcode_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_instances', function (Blueprint $table) {
            $table->dropColumn('pairing_code');
        });
    }
};
