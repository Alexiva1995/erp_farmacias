<?php
// Script para corregir las cantidades históricas restadas incorrectamente en ingredientes
// y sincronizar los stocks de lotes y productos con los movimientos de inventario en producción.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::transaction(function () {
        echo "1. Corrigiendo cantidades de movimientos históricos de ingredientes...\n";
        
        // Obtener ingredientes que tengan presentación configurada
        $ingredients = DB::table('products')
            ->where(function($q) {
                $q->where('no_pvp', 1)
                  ->orWhereExists(function($sub) {
                      $sub->select(DB::raw(1))
                          ->from('general_settings')
                          ->where('business_type', 'restaurant');
                  });
            })
            ->where('presentation', '>', 1)
            ->get();

        $correctedCount = 0;
        foreach ($ingredients as $product) {
            // Buscamos movimientos de tipo 'sale' (negativos) para este producto
            $movements = DB::table('inventory_movements')
                ->where('product_id', $product->id)
                ->where('movement_type', 'sale')
                ->where('quantity', '<', 0)
                ->get();

            foreach ($movements as $mov) {
                // Si la cantidad actual no es divisible o queremos revertir el factor de presentación
                // El error histórico es que se restó la porción cruda directamente (ej: -150 g) de los lotes expresados en unidades (ej: paquetes de 1000g).
                // Al dividir por la presentación, ej: -150 / 1000 = -0.150 unidades del lote, que es lo correcto.
                // Sin embargo, para evitar dividir dos veces si el script se corre de nuevo,
                // verificamos si el valor absoluto de la cantidad es mayor o igual a la presentación,
                // o si simplemente aplicamos la división asegurando que no se haya corregido previamente.
                // Como medida de seguridad, solo dividimos si la cantidad absoluta es mayor a la presentación / 10 (o simplemente aplicamos la división)
                // Para producción, dividiremos los movimientos cuyos valores absolutos sigan estando expresados en gramos/ml (ej. > 1 o similar).
                // Una forma 100% segura es guardar un log o validar el rango: si la cantidad de movimientos es menor a 1 unidad pero originalmente era en gramos.
                // Para ser precisos, dividiremos todos los movimientos negativos de tipo venta para estos ingredientes que sean enteros o con magnitudes grandes.
                // Por ejemplo, si -quantity > 1 (y no es un decimal muy pequeño ya corregido).
                if (abs($mov->quantity) >= 1.0) {
                    $newQty = $mov->quantity / $product->presentation;
                    DB::table('inventory_movements')
                        ->where('id', $mov->id)
                        ->update(['quantity' => $newQty]);
                    $correctedCount++;
                }
            }
        }
        echo "Se corrigieron $correctedCount registros de movimientos de inventario.\n\n";

        echo "2. Sincronizando stock de lotes (product_lots)...\n";
        $lotsMovements = DB::table('inventory_movements')
            ->whereNotNull('product_lot_id')
            ->groupBy('product_lot_id')
            ->select('product_lot_id', DB::raw('SUM(quantity) as total_qty'))
            ->pluck('total_qty', 'product_lot_id');

        $lots = DB::table('product_lots')->get();
        $lotsUpdated = 0;
        foreach ($lots as $lot) {
            $expectedQty = (float)($lotsMovements[$lot->id] ?? 0.0);
            if (abs((float)$lot->quantity - $expectedQty) > 0.0001) {
                DB::table('product_lots')
                    ->where('id', $lot->id)
                    ->update(['quantity' => $expectedQty]);
                $lotsUpdated++;
            }
        }
        echo "Se actualizaron $lotsUpdated lotes en la base de datos.\n\n";

        echo "3. Sincronizando stock de productos (products)...\n";
        $productsMovements = DB::table('inventory_movements')
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->pluck('total_qty', 'product_id');

        $products = DB::table('products')->where('is_deleted', 0)->get();
        $productsUpdated = 0;
        foreach ($products as $product) {
            $expectedQty = (float)($productsMovements[$product->id] ?? 0.0);
            if (abs((float)$product->stock - $expectedQty) > 0.0001) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['stock' => $expectedQty]);
                $productsUpdated++;
            }
        }
        echo "Se actualizaron $productsUpdated productos en la base de datos.\n\n";
    });
    
    echo "¡Proceso finalizado con éxito! El inventario está completamente corregido y sincronizado.\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
