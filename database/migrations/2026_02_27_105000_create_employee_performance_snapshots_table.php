<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_performance_snapshots', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('employee_id');
            $blueprint->integer('month');
            $blueprint->integer('year');
            
            // Employee identity snapshot
            $blueprint->string('name');
            $blueprint->string('last_name');

            // Metrics Raw
            $blueprint->decimal('sales', 15, 2)->default(0);
            $blueprint->decimal('growth', 8, 2)->default(0);
            $blueprint->integer('expirations')->default(0);
            $blueprint->integer('inventory_counted')->default(0);
            $blueprint->integer('inventory_errors')->default(0);
            $blueprint->integer('premium_products')->default(0);
            $blueprint->integer('cleaning_assigned')->default(0);
            $blueprint->integer('cleaning_completed')->default(0);
            $blueprint->integer('strategy_sales')->default(0);
            
            // Invoice Metrics
            $blueprint->integer('invoice_items')->default(0);
            $blueprint->integer('invoice_headers')->default(0);
            $blueprint->integer('invoice_archived')->default(0);
            
            // Calculated Scores
            $blueprint->decimal('score_sales', 8, 2)->default(0);
            $blueprint->decimal('score_growth', 8, 2)->default(0);
            $blueprint->decimal('score_expiration', 8, 2)->default(0);
            $blueprint->decimal('score_inventory', 8, 2)->default(0);
            $blueprint->decimal('score_premium', 8, 2)->default(0);
            $blueprint->decimal('score_invoice', 8, 2)->default(0);
            $blueprint->decimal('score_cleaning', 8, 2)->default(0);
            $blueprint->decimal('score_strategy', 8, 2)->default(0);
            $blueprint->decimal('total_score', 10, 2)->default(0);

            // Legacy/Extra fields if needed
            $blueprint->decimal('score_loaded', 8, 2)->default(0);
            $blueprint->decimal('score_registered', 8, 2)->default(0);
            $blueprint->decimal('score_ordered', 8, 2)->default(0);

            $blueprint->timestamps();
            
            $blueprint->unique(['employee_id', 'month', 'year'], 'unique_performance_snapshot');
            $blueprint->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance_snapshots');
    }
};
