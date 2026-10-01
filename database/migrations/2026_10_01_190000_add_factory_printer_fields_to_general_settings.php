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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'factory_printer_ip')) {
                $table->string('factory_printer_ip', 100)->default('127.0.0.1')->nullable()->after('fiscal_machine_type');
            }
            if (!Schema::hasColumn('general_settings', 'factory_printer_port')) {
                $table->integer('factory_printer_port')->default(8090)->nullable()->after('factory_printer_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'factory_printer_ip')) {
                $table->dropColumn('factory_printer_ip');
            }
            if (Schema::hasColumn('general_settings', 'factory_printer_port')) {
                $table->dropColumn('factory_printer_port');
            }
        });
    }
};
