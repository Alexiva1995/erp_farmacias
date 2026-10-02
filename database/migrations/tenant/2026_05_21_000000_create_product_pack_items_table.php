<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear la tabla pivote física
        Schema::create('product_pack_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('product_packs')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('discount_percentage', 10, 2)->default(0.00);
            $table->decimal('sale_price', 10, 2)->default(0.00);
            $table->timestamps();

            // Índice compuesto único para optimización de búsquedas y JOINS rápidos
            $table->unique(['pack_id', 'product_id'], 'uq_pack_product');
        });

        // Migrar datos existentes en JSON 'pack_config' a 'product_pack_items'
        $this->migrateJsonToPivot();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_pack_items');
    }

    /**
     * Extrae los datos de pack_config en JSON y los inserta en la tabla pivote product_pack_items
     */
    private function migrateJsonToPivot(): void
    {
        $packs = DB::table('product_packs')->get();

        foreach ($packs as $pack) {
            if ($pack->pack_config) {
                $config = json_decode($pack->pack_config, true);
                if (is_array($config)) {
                    foreach ($config as $productId => $item) {
                        $quantity = 1;
                        $discountPercentage = 0.00;
                        $salePrice = 0.00;

                        if (is_array($item)) {
                            $quantity = (int)($item['quantity'] ?? 1);
                            $discountPercentage = (float)($item['discount_percentage'] ?? 0.00);
                            $salePrice = (float)($item['sale_price'] ?? 0.00);
                        } else {
                            $quantity = (int)$item;
                            // Buscar precio base del producto si es formato antiguo
                            $product = DB::table('products')->where('id', $productId)->first();
                            if ($product) {
                                $salePrice = (float)$product->sale_price;
                            }
                        }

                        // Validar si el producto existe antes de insertarlo
                        // para evitar errores de llave foránea por datos inconsistentes/huérfanos.
                        $productExists = DB::table('products')->where('id', $productId)->exists();
                        if ($productExists) {
                            DB::table('product_pack_items')->insert([
                                'pack_id' => $pack->id,
                                'product_id' => $productId,
                                'quantity' => $quantity,
                                'discount_percentage' => $discountPercentage,
                                'sale_price' => $salePrice,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }
};
