<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'comparator_discount_mode')) {
                $table->string('comparator_discount_mode', 30)->default('original')->after('min_order_amount');
            }
            if (!Schema::hasColumn('suppliers', 'comparator_discount_percentage')) {
                $table->decimal('comparator_discount_percentage', 5, 2)->default(0)->after('comparator_discount_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('suppliers', 'comparator_discount_mode')) {
                $cols[] = 'comparator_discount_mode';
            }
            if (Schema::hasColumn('suppliers', 'comparator_discount_percentage')) {
                $cols[] = 'comparator_discount_percentage';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
