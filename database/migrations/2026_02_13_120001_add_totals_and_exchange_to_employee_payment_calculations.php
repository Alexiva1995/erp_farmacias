<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_payment_calculations', function (Blueprint $table) {
            $table->decimal('exchange_rate_ves', 12, 4)->nullable()->after('nuevo_saldo_deuda');
            $table->decimal('total_pagado_usd', 12, 2)->nullable()->after('exchange_rate_ves');
            $table->decimal('total_pagado_ves', 12, 2)->nullable()->after('total_pagado_usd');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payment_calculations', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate_ves', 'total_pagado_usd', 'total_pagado_ves']);
        });
    }
};
