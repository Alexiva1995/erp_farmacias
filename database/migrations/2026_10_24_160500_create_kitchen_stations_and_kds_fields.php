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
        // 1. Tabla de Estaciones de Cocina
        if (!Schema::hasTable('kitchen_stations')) {
            Schema::create('kitchen_stations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 30)->unique();
                $table->string('color', 30)->default('primary');
                $table->string('icon', 50)->default('tabler-chef-hat');
                $table->string('printer_ip', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();
            });
        }

        // 2. Columnas en order_details para el KDS
        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('order_details', 'kitchen_status')) {
                    $table->string('kitchen_status', 30)->default('draft')->after('notes');
                }
                if (!Schema::hasColumn('order_details', 'course_number')) {
                    $table->unsignedTinyInteger('course_number')->default(1)->after('kitchen_status');
                }
                if (!Schema::hasColumn('order_details', 'commanded_at')) {
                    $table->timestamp('commanded_at')->nullable()->after('course_number');
                }
                if (!Schema::hasColumn('order_details', 'prepared_at')) {
                    $table->timestamp('prepared_at')->nullable()->after('commanded_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                $columns = ['kitchen_status', 'course_number', 'commanded_at', 'prepared_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('order_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('kitchen_stations');
    }
};
