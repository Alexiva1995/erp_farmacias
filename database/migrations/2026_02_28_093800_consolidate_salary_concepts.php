<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Unificar Salario Básico Mensual (ID 10) -> Salario Base (ID 1)
        // Renombrar ID 1 a 'Salario Básico Mensual' si existe
        DB::table('salary_concepts')->where('id', 1)->update(['name' => 'Salario Básico Mensual']);

        // Mover relaciones de users_salary_details del 10 al 1
        DB::table('users_salary_details')
            ->where('salary_concept_id', 10)
            ->update(['salary_concept_id' => 1]);

        // 2. Unificar Performance Bonus (ID 9) -> Bono Extraordinario de Rendimiento (ID 7)
        // Mover relaciones de users_salary_details del 9 al 7
        DB::table('users_salary_details')
            ->where('salary_concept_id', 9)
            ->update(['salary_concept_id' => 7]);

        // 3. Eliminar los conceptos duplicados
        DB::table('salary_concepts')->whereIn('id', [9, 10])->delete();
        
        // 4. Asegurar que los nombres de los conceptos 1 y 7 sean los correctos
        DB::table('salary_concepts')->where('id', 1)->update(['name' => 'Salario Básico Mensual']);
        DB::table('salary_concepts')->where('id', 7)->update(['name' => 'Bono Extraordinario de Rendimiento']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No es reversible sin riesgo de pérdida de distinción original, pero restauramos nombres si es necesario
    }
};
