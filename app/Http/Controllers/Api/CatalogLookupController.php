<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\MasterCatalogClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogLookupController extends Controller
{
    public function __construct(
        protected MasterCatalogClientService $catalogClientService
    ) {}

    /**
     * Buscar código de barra tanto en la base de datos local como en el catálogo maestro.
     */
    public function lookup(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if (empty($barcode)) {
            return response()->json([
                'exists_locally'  => false,
                'found_in_master' => false,
                'product'         => null,
            ]);
        }

        // 1. Verificar si ya existe en la farmacia local
        $localProduct = Product::with(['laboratory', 'category', 'origin'])
            ->where('barcode', $barcode)
            ->first();

        if ($localProduct) {
            return response()->json([
                'exists_locally'  => true,
                'found_in_master' => false,
                'product'         => [
                    'id'                => $localProduct->id,
                    'name'              => $localProduct->name,
                    'barcode'           => $localProduct->barcode,
                    'active_ingredient' => $localProduct->active_ingredient,
                    'laboratory_id'     => $localProduct->laboratory_id,
                    'laboratory_name'   => $localProduct->laboratory?->name,
                    'category_id'       => $localProduct->category_id,
                    'category_name'     => $localProduct->category?->name,
                    'origin_id'         => $localProduct->origin_id,
                    'sale_price'        => (float) ($localProduct->sale_price ?? 0),
                    'unit_cost'         => (float) ($localProduct->unit_cost ?? 0),
                ],
            ]);
        }

        // 2. Si no existe localmente, consultar el Catálogo Maestro
        $masterResult = $this->catalogClientService->lookupByBarcode($barcode);

        if (!empty($masterResult['found']) && !empty($masterResult['product'])) {
            return response()->json([
                'exists_locally'  => false,
                'found_in_master' => true,
                'product'         => $masterResult['product'],
            ]);
        }

        return response()->json([
            'exists_locally'  => false,
            'found_in_master' => false,
            'product'         => null,
        ]);
    }
}
