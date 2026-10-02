<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('total_package_usd', 12, 2)->nullable()->after('user_id');
            $table->decimal('saldo_deuda', 12, 2)->default(0)->after('total_package_usd');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['total_package_usd', 'saldo_deuda']);
        });
    }
};
