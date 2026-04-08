<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$productId = 15097;
$productIds = [(string) $productId]; // Forzamos string para probar

$bestOffersData = DB::table('product_suppliers')
    ->whereIn('product_id', $productIds)
    ->whereNull('deleted_at')
    ->get();

echo "Total ofertas encontradas: " . $bestOffersData->count() . "\n";

foreach ($productIds as $id) {
    // Probamos el filtrado de la coleccion
    $ofertas = $bestOffersData->where('product_id', $id);
    $bestOffer = $ofertas->first();
    
    echo "ID buscado: $id (" . gettype($id) . ")\n";
    if ($bestOffer) {
        echo "OFERTA ENCONTRADA: " . $bestOffer->id . " (Product ID en DB: " . $bestOffer->product_id . " - " . gettype($bestOffer->product_id) . ")\n";
    } else {
        echo "OFERTA NO ENCONTRADA con where('product_id', \$id)\n";
        
        // Probamos con filtrado estricto
        $ofertas2 = $bestOffersData->filter(function($item) use ($id) {
            return $item->product_id == $id;
        });
        if ($ofertas2->isNotEmpty()) {
            echo "Pero filtrada con == (laxo) SI aparece.\n";
        }
    }
}
