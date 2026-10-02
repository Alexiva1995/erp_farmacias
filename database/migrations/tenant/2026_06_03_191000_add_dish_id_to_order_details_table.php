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
        Schema::table('order_details', function (Blueprint $table) {
            $table->foreignId('dish_id')->nullable()->after('pack_id')->constrained('dishes')->nullOnDelete();
            $table->string('product_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['dish_id']);
            $table->dropColumn('dish_id');
            // Nota: En MySQL no es fácil revertir a ENUM si hay datos incompatibles, pero lo dejamos como string por simplicidad y compatibilidad.
        });
    }
};
