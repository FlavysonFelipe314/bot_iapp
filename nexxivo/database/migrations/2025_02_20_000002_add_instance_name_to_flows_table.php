<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('flows', 'instance_name')) {
            return;
        }
        Schema::table('flows', function (Blueprint $table) {
            $table->string('instance_name')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table) {
            $table->dropColumn('instance_name');
        });
    }
};
