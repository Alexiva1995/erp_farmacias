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
        Schema::table('product_packs', function (Blueprint $table) {
            $table->decimal('total_price', 10, 2)->nullable()->after('pack_config');
            $table->integer('max_quantity')->nullable()->after('total_price');
            $table->dateTime('max_sale_date')->nullable()->after('max_quantity');
            $table->boolean('is_active')->default(true)->after('max_sale_date');
        });

        // Actualizar el comentario de pack_config
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE product_packs MODIFY COLUMN pack_config JSON NULL COMMENT 'JSON: {product_id: {quantity, discount_percentage, sale_price}}'");
        }

        // Migrar datos existentes al nuevo formato
        $this->migrateExistingData();

        // Agregar índices
        Schema::table('product_packs', function (Blueprint $table) {
            $table->index('max_sale_date', 'idx_max_sale_date');
            $table->index('is_active', 'idx_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_packs', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'max_quantity', 'max_sale_date', 'is_active']);
            $table->dropIndex('idx_max_sale_date');
            $table->dropIndex('idx_is_active');
        });

        // Restaurar comentario original
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE product_packs MODIFY COLUMN pack_config JSON NULL COMMENT 'JSON: {product_id: quantity}'");
        }
    }

    /**
     * Migrar datos existentes al nuevo formato
     */
    private function migrateExistingData(): void
    {
        $packs = DB::table('product_packs')->get();

        foreach ($packs as $pack) {
            if ($pack->pack_config) {
                $oldConfig = json_decode($pack->pack_config, true);
                $newConfig = [];
                $totalPrice = 0;

                foreach ($oldConfig as $productId => $quantity) {
                    // Buscar el producto para obtener el precio
                    $product = DB::table('products')->where('id', $productId)->first();
                    
                    if ($product) {
                        $salePrice = $product->sale_price;
                        $newConfig[$productId] = [
                            'quantity' => (int)$quantity,
                            'discount_percentage' => 0,
                            'sale_price' => (float)$salePrice,
                        ];
                        $totalPrice += $salePrice * $quantity;
                    }
                }

                // Actualizar con el nuevo formato y calcular precio total
                DB::table('product_packs')
                    ->where('id', $pack->id)
                    ->update([
                        'pack_config' => json_encode($newConfig),
                        'total_price' => $totalPrice
                    ]);
            }
        }
    }
};
