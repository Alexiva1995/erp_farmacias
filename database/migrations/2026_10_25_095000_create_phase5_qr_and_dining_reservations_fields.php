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
        // 1. dining_tables para autopedido QR y asistencia
        Schema::table('dining_tables', function (Blueprint $table) {
            if (!Schema::hasColumn('dining_tables', 'qr_token')) {
                $table->string('qr_token', 64)->nullable()->unique()->after('name');
            }
            if (!Schema::hasColumn('dining_tables', 'needs_attention')) {
                $table->boolean('needs_attention')->default(false)->after('status');
            }
            if (!Schema::hasColumn('dining_tables', 'attention_reason')) {
                $table->string('attention_reason', 50)->nullable()->after('needs_attention'); // waiter_call, bill_requested, qr_order_pending
            }
        });

        // 2. kitchen_stations para coordenadas 3D en el simulador
        Schema::table('kitchen_stations', function (Blueprint $table) {
            if (!Schema::hasColumn('kitchen_stations', 'pos_x')) {
                $table->float('pos_x')->default(50)->after('sort_order');
            }
            if (!Schema::hasColumn('kitchen_stations', 'pos_y')) {
                $table->float('pos_y')->default(420)->after('pos_x');
            }
        });

        // 3. users para presencia activa en estaciones y restaurante
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'active_kitchen_station_id')) {
                $table->foreignId('active_kitchen_station_id')->nullable()->after('role_id')->constrained('kitchen_stations')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'last_restaurant_activity_at')) {
                $table->timestamp('last_restaurant_activity_at')->nullable()->after('active_kitchen_station_id');
            }
        });

        // 4. reservations adaptadas a salón de restaurante
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'court_id')) {
                $table->unsignedBigInteger('court_id')->nullable()->change();
            }
            if (!Schema::hasColumn('reservations', 'dining_table_id')) {
                $table->foreignId('dining_table_id')->nullable()->after('court_id')->constrained('dining_tables')->nullOnDelete();
            }
            if (!Schema::hasColumn('reservations', 'guests_count')) {
                $table->unsignedTinyInteger('guests_count')->default(2)->after('dining_table_id');
            }
            if (!Schema::hasColumn('reservations', 'special_requests')) {
                $table->text('special_requests')->nullable()->after('status');
            }
            if (!Schema::hasColumn('reservations', 'deposit_amount_usd')) {
                $table->decimal('deposit_amount_usd', 10, 2)->default(0.00)->after('special_requests');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'dining_table_id')) {
                $table->dropForeign(['dining_table_id']);
                $table->dropColumn(['dining_table_id', 'guests_count', 'special_requests', 'deposit_amount_usd']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'active_kitchen_station_id')) {
                $table->dropForeign(['active_kitchen_station_id']);
                $table->dropColumn(['active_kitchen_station_id', 'last_restaurant_activity_at']);
            }
        });

        Schema::table('kitchen_stations', function (Blueprint $table) {
            if (Schema::hasColumn('kitchen_stations', 'pos_x')) {
                $table->dropColumn(['pos_x', 'pos_y']);
            }
        });

        Schema::table('dining_tables', function (Blueprint $table) {
            if (Schema::hasColumn('dining_tables', 'qr_token')) {
                $table->dropColumn(['qr_token', 'needs_attention', 'attention_reason']);
            }
        });
    }
};
