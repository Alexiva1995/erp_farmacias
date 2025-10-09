<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table) {
            $table->json('parse_using')->nullable()->default(null)->after('structure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_connections', function (Blueprint $table) {
            $table->dropColumn('parse_using');
        });
    }
};
