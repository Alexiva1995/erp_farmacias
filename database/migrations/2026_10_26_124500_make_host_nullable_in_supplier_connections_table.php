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
        if (Schema::hasTable('supplier_connections') && Schema::hasColumn('supplier_connections', 'host')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                $table->string('host')->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_connections') && Schema::hasColumn('supplier_connections', 'host')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                $table->string('host')->nullable(false)->change();
            });
        }
    }
};
