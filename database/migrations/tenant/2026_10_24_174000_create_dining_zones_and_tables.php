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
        // 1. Zonas del restaurante (Salón principal, Terraza, Barra, VIP, etc.)
        if (!Schema::hasTable('dining_zones')) {
            Schema::create('dining_zones', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('background_style', 50)->default('grid'); // grid, wooden, concrete, grass
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Mesas del salón
        if (!Schema::hasTable('dining_tables')) {
            Schema::create('dining_tables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dining_zone_id')->constrained('dining_zones')->onDelete('cascade');
                $table->string('name', 50); // Ej: Mesa 1, T-02, Barra 4
                $table->integer('capacity')->default(4);
                $table->string('shape', 20)->default('square'); // square, round, rectangle, bar
                $table->float('pos_x')->default(100);
                $table->float('pos_y')->default(100);
                $table->float('width')->default(80);
                $table->float('height')->default(80);
                $table->float('rotation')->default(0);
                $table->string('status', 20)->default('available'); // available, occupied, bill_requested, reserved
                $table->unsignedBigInteger('current_order_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['dining_zone_id', 'status']);
            });
        }

        // 3. Modificaciones en orders para desacoplar órdenes abiertas y pre-cuenta / split
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'dining_table_id')) {
                $table->unsignedBigInteger('dining_table_id')->nullable()->after('client_id');
                $table->foreign('dining_table_id')->references('id')->on('dining_tables')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'parent_order_id')) {
                $table->unsignedBigInteger('parent_order_id')->nullable()->after('dining_table_id');
                $table->foreign('parent_order_id')->references('id')->on('orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'guests_count')) {
                $table->unsignedTinyInteger('guests_count')->default(1)->after('dining_table_id');
            }
            if (!Schema::hasColumn('orders', 'service_charge_percentage')) {
                $table->decimal('service_charge_percentage', 5, 2)->default(0)->after('total_amount_usd');
            }
            if (!Schema::hasColumn('orders', 'service_charge_amount')) {
                $table->decimal('service_charge_amount', 14, 2)->default(0)->after('service_charge_percentage');
            }
            if (!Schema::hasColumn('orders', 'service_charge_amount_usd')) {
                $table->decimal('service_charge_amount_usd', 14, 2)->default(0)->after('service_charge_amount');
            }
            if (!Schema::hasColumn('orders', 'pre_bill_printed_at')) {
                $table->timestamp('pre_bill_printed_at')->nullable()->after('service_charge_amount_usd');
            }
        });

        // 4. Modificaciones en order_details para asiento/comensal
        Schema::table('order_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_details', 'seat_number')) {
                $table->unsignedTinyInteger('seat_number')->nullable()->after('order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'seat_number')) {
                $table->dropColumn('seat_number');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'dining_table_id')) {
                $table->dropForeign(['dining_table_id']);
                $table->dropColumn('dining_table_id');
            }
            if (Schema::hasColumn('orders', 'parent_order_id')) {
                $table->dropForeign(['parent_order_id']);
                $table->dropColumn('parent_order_id');
            }
            if (Schema::hasColumn('orders', 'guests_count')) {
                $table->dropColumn('guests_count');
            }
            if (Schema::hasColumn('orders', 'service_charge_percentage')) {
                $table->dropColumn('service_charge_percentage');
            }
            if (Schema::hasColumn('orders', 'service_charge_amount')) {
                $table->dropColumn('service_charge_amount');
            }
            if (Schema::hasColumn('orders', 'service_charge_amount_usd')) {
                $table->dropColumn('service_charge_amount_usd');
            }
            if (Schema::hasColumn('orders', 'pre_bill_printed_at')) {
                $table->dropColumn('pre_bill_printed_at');
            }
        });

        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_zones');
    }
};
