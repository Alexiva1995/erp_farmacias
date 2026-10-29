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
        if (!Schema::hasTable('delivery_routes')) {
            Schema::create('delivery_routes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('name', 150);
                $table->string('zone_state', 100)->nullable();
                $table->string('driver_name', 150)->nullable();
                $table->vehicle_plate = $table->string('vehicle_plate', 50)->nullable();
                $table->json('dispatch_days')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'b2b_workflow_status')) {
                    $table->string('b2b_workflow_status', 40)->default('draft')->index()->after('status');
                }
                if (!Schema::hasColumn('orders', 'delivery_route_id')) {
                    $table->unsignedBigInteger('delivery_route_id')->nullable()->index()->after('b2b_workflow_status');
                }
                if (!Schema::hasColumn('orders', 'packages_count')) {
                    $table->integer('packages_count')->default(0)->after('delivery_route_id');
                }
                if (!Schema::hasColumn('orders', 'cold_boxes_count')) {
                    $table->integer('cold_boxes_count')->default(0)->after('packages_count');
                }
                if (!Schema::hasColumn('orders', 'assigned_picker_id')) {
                    $table->unsignedBigInteger('assigned_picker_id')->nullable()->index()->after('cold_boxes_count');
                }
                if (!Schema::hasColumn('orders', 'assigned_checker_id')) {
                    $table->unsignedBigInteger('assigned_checker_id')->nullable()->index()->after('assigned_picker_id');
                }
                if (!Schema::hasColumn('orders', 'parent_b2b_order_id')) {
                    $table->unsignedBigInteger('parent_b2b_order_id')->nullable()->index()->after('assigned_checker_id');
                }
                if (!Schema::hasColumn('orders', 'credit_term_days')) {
                    $table->integer('credit_term_days')->default(0)->after('parent_b2b_order_id');
                }
            });
        }

        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('order_details', 'product_lot_id')) {
                    $table->unsignedBigInteger('product_lot_id')->nullable()->index()->after('product_id');
                }
                if (!Schema::hasColumn('order_details', 'picked_quantity')) {
                    $table->decimal('picked_quantity', 12, 2)->default(0)->after('quantity');
                }
                if (!Schema::hasColumn('order_details', 'checked_quantity')) {
                    $table->decimal('checked_quantity', 12, 2)->default(0)->after('picked_quantity');
                }
                if (!Schema::hasColumn('order_details', 'credit_days_applied')) {
                    $table->integer('credit_days_applied')->default(0)->after('checked_quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                $columns = ['product_lot_id', 'picked_quantity', 'checked_quantity', 'credit_days_applied'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('order_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = [
                    'b2b_workflow_status',
                    'delivery_route_id',
                    'packages_count',
                    'cold_boxes_count',
                    'assigned_picker_id',
                    'assigned_checker_id',
                    'parent_b2b_order_id',
                    'credit_term_days',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('delivery_routes');
    }
};
