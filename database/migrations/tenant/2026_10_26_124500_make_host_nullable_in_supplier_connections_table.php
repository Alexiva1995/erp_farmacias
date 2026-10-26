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
        if (Schema::hasTable('supplier_connections')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_connections', 'host')) {
                    $table->string('host')->nullable()->default(null)->change();
                }
                if (Schema::hasColumn('supplier_connections', 'pasv')) {
                    $table->boolean('pasv')->default(false)->change();
                }
                if (Schema::hasColumn('supplier_connections', 'has_header')) {
                    $table->boolean('has_header')->default(false)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_connections')) {
            Schema::table('supplier_connections', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_connections', 'host')) {
                    $table->string('host')->nullable(false)->change();
                }
                if (Schema::hasColumn('supplier_connections', 'pasv')) {
                    $table->boolean('pasv')->nullable(false)->change();
                }
                if (Schema::hasColumn('supplier_connections', 'has_header')) {
                    $table->boolean('has_header')->nullable(false)->change();
                }
            });
        }
    }
};
