<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->boolean('include_ignored')->default(true)->after('exclude_novaventa');
        });
    }

    public function down(): void
    {
        Schema::table('auto_replenishment_configs', function (Blueprint $table) {
            $table->dropColumn('include_ignored');
        });
    }
};
