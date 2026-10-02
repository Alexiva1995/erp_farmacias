<?php

use App\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $cases = array_map(fn($case) => "'{$case->value}'", TransactionType::cases());
            $enumValues = implode(',', $cases);

            DB::statement("ALTER TABLE `invoice_payments` MODIFY `method` ENUM({$enumValues}) NULL");
            DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM({$enumValues}) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $oldTypes = "'CASH','CARD','TRANSFER','MOBILE','BINANCE','PAYPAL','CREDIT'";
            DB::statement("ALTER TABLE `invoice_payments` MODIFY `method` ENUM({$oldTypes}) NULL");
            DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM({$oldTypes}) NOT NULL");
        }
    }
};
