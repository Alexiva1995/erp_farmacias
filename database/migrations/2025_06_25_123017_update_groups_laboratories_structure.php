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
        Schema::table('groups_laboratories', function (Blueprint $table) {
            $table->unique('name', 'uniq_lab_group_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups_laboratories', function (Blueprint $table) {
            $table->dropUnique('uniq_lab_group_name');
        });
    }
};
