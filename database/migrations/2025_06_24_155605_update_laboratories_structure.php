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
        Schema::table('laboratories', function (Blueprint $table) {
            $table->unique('name', 'uniq_lab_name');
            $table->index('group_id', 'idx_lab_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laboratories', callback: function (Blueprint $table) {
            $table->dropUnique('uniq_lab_name');
            $table->dropIndex('idx_lab_group');
        });
    }
};
