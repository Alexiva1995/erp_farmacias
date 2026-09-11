<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $specialties = [
            'Medicina General',
            'Pediatría',
            'Ginecología y Obstetricia',
            'Cardiología',
            'Traumatología y Ortopedia',
            'Dermatología',
            'Medicina Interna',
            'Oftalmología',
            'Otorrinolaringología',
            'Neurología',
            'Gastroenterología',
            'Urología',
            'Endocrinología',
            'Neumología',
            'Psiquiatría',
            'Cirugía General',
            'Anestesiología',
            'Oncología',
            'Nefrología',
            'Infectología',
            'Nutrición y Dietética',
            'Odontología General',
        ];

        $now = now();
        $insertData = [];

        foreach ($specialties as $name) {
            $insertData[] = [
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('specialties')->insertOrIgnore($insertData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
