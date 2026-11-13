<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía la precisión decimal de quantity y unit_cost a 4 decimales en kitchen_wastes.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kitchen_wastes')) {
            return;
        }

        Schema::table('kitchen_wastes', function (Blueprint $table) {
            $table->decimal('quantity', 14, 4)->default(1.0000)->change();
            $table->decimal('unit_cost', 14, 4)->default(0.0000)->change();
        });
    }

    /**
     * Revierte la precisión decimal a 2 decimales.
     */
    public function down(): void
    {
        if (!Schema::hasTable('kitchen_wastes')) {
            return;
        }

        Schema::table('kitchen_wastes', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1.00)->change();
            $table->decimal('unit_cost', 14, 2)->default(0.00)->change();
        });
    }
};
