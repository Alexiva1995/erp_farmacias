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
        Schema::table('supplier_connections', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_connections', 'secondary_structure')) {
                $table->json('secondary_structure')->nullable()->after('structure');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_connections', 'secondary_structure')) {
                $table->dropColumn('secondary_structure');
            }
        });
    }
};
