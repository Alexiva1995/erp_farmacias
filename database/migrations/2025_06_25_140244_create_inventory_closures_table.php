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
        Schema::create('inventory_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('inventory_cycles')->onDelete('cascade');
            $table->integer('missing_units');
            $table->integer('leftover_units');
            $table->decimal('missing_amount', 12, 2);
            $table->decimal('leftover_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->dateTime('closed_at');
            $table->timestamps();

            $table->index('cycle_id', 'idx_closure_cycle');
        });

        DB::statement("
            ALTER TABLE inventory_closures 
            ADD COLUMN total_units BIGINT 
            GENERATED ALWAYS AS (missing_units + leftover_units) VIRTUAL
            AFTER total_amount
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_closures');
    }
};
