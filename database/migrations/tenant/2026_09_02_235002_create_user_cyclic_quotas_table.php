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
        Schema::create('user_cyclic_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cycle_id')->constrained('inventory_cycles')->onDelete('cascade');
            $table->date('quota_date');
            $table->unsignedInteger('quota_tier')->default(1)->comment('1: base (+1 pt), 2: extra (+2 pts), 3+: extra (+4 pts)');
            $table->unsignedInteger('assigned_quantity')->default(50);
            $table->json('assigned_product_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'cycle_id', 'quota_date', 'quota_tier'], 'uq_user_cyclic_quota_day_tier');
            $table->index(['user_id', 'quota_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cyclic_quotas');
    }
};
