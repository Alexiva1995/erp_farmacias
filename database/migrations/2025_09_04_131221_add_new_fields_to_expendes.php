<?php

use App\Models\Expense;
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
        Schema::table('expenses', function (Blueprint $table) {
            //
            $table->enum("status", [
                Expense::STATUS_PENDING,
                Expense::STATUS_APPROVED,
                Expense::STATUS_CANCELLED,
            ]);
            $table->enum("count", [
                Expense::COUNT_EFECTIVO,
                Expense::COUNT_TARJETA,
                Expense::COUNT_PAGO_MOVIL,
                Expense::COUNT_TRANSFERENCIA,
                Expense::COUNT_BINANCE,
                Expense::COUNT_PAYPAL,
            ])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            //
            $table->dropColumn("status");
            $table->dropColumn("count");
        });
    }
};
