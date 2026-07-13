<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega el concepto de Gratificación Extraordinaria por Rendimiento.
     */
    public function up(): void
    {
        DB::table('salary_concepts')->updateOrInsert(
            ['name' => 'Performance Bonus'],
            [
                'type' => 'salary',
                'frequency' => 'monthly',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('salary_concepts')
            ->where('name', 'Performance Bonus')
            ->delete();
    }
};
