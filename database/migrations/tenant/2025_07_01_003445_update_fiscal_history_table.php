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
        $hasIndex = false;
        try {
            if (Schema::hasTable('fiscal_history')) {
                $indexes = Schema::getIndexes('fiscal_history');
                foreach ($indexes as $index) {
                    if ($index['name'] === 'fiscal_history_order_id_index') {
                        $hasIndex = true;
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback por si getIndexes no es compatible
            $hasIndex = DB::getDriverName() === 'sqlite';
        }

        Schema::table('fiscal_history', function (Blueprint $table) use ($hasIndex) {
            try {
                $table->dropForeign(['order_id']);
            } catch (\Exception $e) {}

            if ($hasIndex) {
                $table->dropIndex('fiscal_history_order_id_index');
            }

            $table->dropColumn('order_id');
            $table->unsignedBigInteger('fiscal_id')->after('user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_history', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');
        });
    }
};
