<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las mejoras de promociones para farmacias y restaurantes PyME.
     */
    public function up(): void
    {
        if (Schema::hasTable('general_promotions')) {
            Schema::table('general_promotions', function (Blueprint $table) {
                $table->string('name', 120)->nullable()->after('id');
                $table->decimal('discount_percentage', 5, 2)->nullable()->after('fixed_price');
                $table->date('start_date')->nullable()->after('categories');
                $table->date('end_date')->nullable()->after('start_date');
                $table->json('days_of_week')->nullable()->after('end_date');
                $table->time('start_time')->nullable()->after('days_of_week');
                $table->time('end_time')->nullable()->after('start_time');
                $table->decimal('min_order_amount', 10, 2)->nullable()->after('end_time');
                $table->string('coupon_code', 50)->nullable()->after('min_order_amount');
                $table->string('sales_channel', 20)->default('all')->after('coupon_code');
                $table->boolean('stackable')->default(false)->after('sales_channel');
                $table->integer('priority')->default(10)->after('stackable');

                $table->index('coupon_code', 'idx_general_promotions_coupon');
                $table->index(['is_active', 'start_date', 'end_date'], 'idx_general_promotions_active_dates');
            });
        }

        if (Schema::hasTable('individual_offers')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('individual_offers', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }

            Schema::table('individual_offers', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
                $table->unsignedBigInteger('dish_id')->nullable()->after('product_id');
                $table->boolean('is_active')->default(true)->after('one_per_customer');
                $table->json('days_of_week')->nullable()->after('end_date');
                $table->time('start_time')->nullable()->after('days_of_week');
                $table->time('end_time')->nullable()->after('start_time');

                $table->index('dish_id', 'idx_individual_offer_dish');
            });

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('individual_offers', function (Blueprint $table) {
                    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                    if (Schema::hasTable('dishes')) {
                        $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('cascade');
                    }
                });
            }
        }

        if (Schema::hasTable('category_offers')) {
            Schema::table('category_offers', function (Blueprint $table) {
                $table->json('days_of_week')->nullable()->after('end_date');
                $table->time('start_time')->nullable()->after('days_of_week');
                $table->time('end_time')->nullable()->after('start_time');
                $table->string('sales_channel', 20)->default('all')->after('end_time');
            });
        }

        if (Schema::hasTable('product_packs')) {
            Schema::table('product_packs', function (Blueprint $table) {
                $table->json('days_of_week')->nullable()->after('max_sale_date');
                $table->time('start_time')->nullable()->after('days_of_week');
                $table->time('end_time')->nullable()->after('start_time');
                $table->string('sales_channel', 20)->default('all')->after('end_time');
            });
        }

        if (Schema::hasTable('product_pack_items')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('product_pack_items', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                    $table->dropUnique('uq_pack_product');
                });
            }

            Schema::table('product_pack_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
                $table->unsignedBigInteger('dish_id')->nullable()->after('product_id');

                $table->index(['pack_id', 'dish_id'], 'idx_pack_dish');
            });

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('product_pack_items', function (Blueprint $table) {
                    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                    if (Schema::hasTable('dishes')) {
                        $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('cascade');
                    }
                });
            }
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'exclude_from_promotions')) {
                    $table->boolean('exclude_from_promotions')->default(false);
                }
            });
        }

        if (Schema::hasTable('dishes')) {
            Schema::table('dishes', function (Blueprint $table) {
                if (!Schema::hasColumn('dishes', 'exclude_from_promotions')) {
                    $table->boolean('exclude_from_promotions')->default(false);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'applied_coupon_code')) {
                    $table->string('applied_coupon_code', 50)->nullable();
                }
            });
        }
    }

    /**
     * Revierte las mejoras de promociones.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'applied_coupon_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('applied_coupon_code');
            });
        }

        if (Schema::hasTable('dishes') && Schema::hasColumn('dishes', 'exclude_from_promotions')) {
            Schema::table('dishes', function (Blueprint $table) {
                $table->dropColumn('exclude_from_promotions');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'exclude_from_promotions')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('exclude_from_promotions');
            });
        }

        if (Schema::hasTable('product_pack_items')) {
            Schema::table('product_pack_items', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['dish_id']);
                }
                $table->dropIndex('idx_pack_dish');
                $table->dropColumn('dish_id');
            });
        }

        if (Schema::hasTable('product_packs')) {
            Schema::table('product_packs', function (Blueprint $table) {
                $table->dropColumn(['days_of_week', 'start_time', 'end_time', 'sales_channel']);
            });
        }

        if (Schema::hasTable('category_offers')) {
            Schema::table('category_offers', function (Blueprint $table) {
                $table->dropColumn(['days_of_week', 'start_time', 'end_time', 'sales_channel']);
            });
        }

        if (Schema::hasTable('individual_offers')) {
            Schema::table('individual_offers', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['dish_id']);
                }
                $table->dropIndex('idx_individual_offer_dish');
                $table->dropColumn(['dish_id', 'is_active', 'days_of_week', 'start_time', 'end_time']);
            });
        }

        if (Schema::hasTable('general_promotions')) {
            Schema::table('general_promotions', function (Blueprint $table) {
                $table->dropIndex('idx_general_promotions_coupon');
                $table->dropIndex('idx_general_promotions_active_dates');
                $table->dropColumn([
                    'name',
                    'discount_percentage',
                    'start_date',
                    'end_date',
                    'days_of_week',
                    'start_time',
                    'end_time',
                    'min_order_amount',
                    'coupon_code',
                    'sales_channel',
                    'stackable',
                    'priority',
                ]);
            });
        }
    }
};
