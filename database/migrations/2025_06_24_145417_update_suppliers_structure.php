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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('supplier_name', 'name');

            $table->json('dispatch_days')->nullable(false)->change();
            $table->json('order_days')->nullable(false)->change();

            $table->enum('payment_method', ['Bs', 'Divisas'])->nullable()->default('Bs')->change();
            $table->tinyInteger('cash_payment')->nullable()->default(0)->change();
            $table->tinyInteger('charges_igtf')->nullable()->default(0)->change();
            $table->decimal('rating', 5, 2)->nullable()->default(0.00)->change();

            $table->dropColumn('deleted_at');
            $table->tinyInteger('is_deleted')->nullable()->default(0)->after('rating');

            $table->index('is_deleted', 'idx_supplier_active');
            $table->index('rating', 'idx_supplier_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->renameColumn('name', 'supplier_name');

            $table->json('dispatch_days')->nullable()->change();
            $table->json('order_days')->nullable()->change();

            $table->enum('payment_method', ['Bs', 'Divisas'])->default('Bs')->nullable(false)->change();
            $table->tinyInteger('cash_payment')->default(0)->nullable(false)->change();
            $table->tinyInteger('charges_igtf')->default(0)->nullable(false)->change();
            $table->decimal('rating', 5, 2)->default(0.00)->nullable(false)->change();

            $table->dropIndex('idx_supplier_active');
            $table->dropIndex('idx_supplier_rating');
            $table->dropColumn('is_deleted');
            $table->timestamp('deleted_at')->nullable()->after('rating');
        });
    }
};
