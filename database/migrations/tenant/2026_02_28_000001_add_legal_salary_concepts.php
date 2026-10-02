<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega los conceptos salariales legales venezolanos si no existen.
     */
    public function up(): void
    {
        // Eliminar duplicados del Bono de Alimentación (conservar el de menor id)
        $duplicates = DB::table('salary_concepts')
            ->where('name', 'Bono de Alimentación')
            ->orderBy('id')
            ->skip(1)
            ->take(100) // Workaround for MySQL offset without limit error
            ->pluck('id');

        if ($duplicates->isNotEmpty()) {
            DB::table('salary_concepts')->whereIn('id', $duplicates->toArray())->delete();
        }

        // Insertar conceptos faltantes
        $concepts = [
            ['name' => 'Ayuda de Salud',          'type' => 'salary',    'frequency' => 'monthly'],
            ['name' => 'IVSS (4%)',                'type' => 'deduction', 'frequency' => 'fortnight'],
            ['name' => 'RPE - Paro Forzoso (0.5%)','type' => 'deduction', 'frequency' => 'fortnight'],
            ['name' => 'FAOV (1%)',                'type' => 'deduction', 'frequency' => 'fortnight'],
        ];

        foreach ($concepts as $concept) {
            DB::table('salary_concepts')->updateOrInsert(
                ['name' => $concept['name']],
                array_merge($concept, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('salary_concepts')->whereIn('name', [
            'Ayuda de Salud',
            'IVSS (4%)',
            'RPE - Paro Forzoso (0.5%)',
            'FAOV (1%)',
        ])->delete();
    }
};
