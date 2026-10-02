<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('origins', function (Blueprint $table) {
            $table->string('name', 255)->unique('uniq_origin_name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('origins', function (Blueprint $table) {
            $table->dropUnique('uniq_origin_name');
        });
    }
};
