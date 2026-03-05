<?php

namespace App\Http\Controllers\Api;

use App\Contracts\AutoOrder;
use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Product as ModelsProduct;
class SuppliersIaOrderAssistantController extends Controller
{
    //

    public function __construct(
        protected Product $product,
        protected ProductSupplier $productSupplier,
        protected AutoOrder $autoOrder
    ) {
    }


    public function filtrarPaginate(Request $request): JsonResponse
    {
        $respuesta = [
            "tipo_filtracion" => $request->tipo_filtracion,
            "tipo_vista" => $request->tipo_vista,
            "paginate" => [],
        ];

        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
            "tipo_filtracion" => $request->tipo_filtracion,
            "tipo_vista" => $request->tipo_vista,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
        ];

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("stock")) {
            $filtros["stock"] = $request->stock;
        }

        if ($request->filled("laboratoryId")) {
            $filtros["laboratoryId"] = $request->laboratoryId;
        }

        if ($request->filled("groups")) {
            $filtros["groups"] = $request->groups;
        }

        if ($request->filled("isColombian")) {
            $filtros["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled("lapso_de_tiempo")) {
            $timeZone = new DateTimeZone(config("app.timezone"));
            $dateToday = new DateTime("now", $timeZone);
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $previousDate = new DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d");
        }

        if ($respuesta["tipo_filtracion"] == "average") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
        } elseif ($respuesta["tipo_filtracion"] == "sales") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeSales($filtros);
        } elseif ($respuesta["tipo_filtracion"] == "combinado") {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
        } else {
            $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
        }

        $respuesta["paginate"]->each(function ($items) use ($filtros) {
            $items = $this->product->calcularAOProduct($items);

            // Verificar si el producto tiene ventas 0 y stock 0 (caso especial: sin historial)
            $ventasCero = ($items->total_sold_completed ?? 0) == 0;
            $stockCero = ($items->lote_quantity ?? 0) == 0;
            $aoActual = $items->totalQuantityInAutoOrder ?? 0;

            // El campo 'solicitar' ya viene calculado correctamente desde el SQL del repositorio:
            // solicitar = demanda - stock - AO  (positivo = necesita pedido, negativo = exceso)

            // Caso especial: producto sin ventas y sin stock en inventario → falla por definición
            if ($ventasCero && $stockCero) {
                // Si tiene unidades ya en pedido, está cubierto (exceso leve)
                $items->solicitar = ($aoActual > 0) ? -$aoActual : 1;
            }

            // Redondear hacia arriba si falta, hacia abajo si sobra
            $items->solicitar = $items->solicitar > 0 ? ceil($items->solicitar) : floor($items->solicitar);
        });

        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function generateListProductoToRequest(Request $request): JsonResponse
    {
        $timeZone = new DateTimeZone(config("app.timezone"));
        $dateToday = new DateTime("now", $timeZone);

        $respuesta = [
            "listaDeProductos" => [],
            "productos" => [],
            "productosFallas" => [],
            "productos_a_reponer" => [],
            "productos_oportunidad_unica" => [],
        ];

        $productosFallas = null;
        $filtrosFallas = [
            "tipo_filtracion" => $request->tipo_filtracion,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
            "laboratoryId" => $request->laboratoryId,
            "groups" => $request->groups,
            "stock" => "fallas",
        ];

        if ($request->filled("laboratoryId"))
            $filtrosFallas["laboratoryId"] = $request->laboratoryId;
        if ($request->filled("groups"))
            $filtrosFallas["groups"] = $request->groups;
        if ($request->filled("isColombian"))
            $filtrosFallas["isColombian"] = filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN);

        if ($request->filled("lapso_de_tiempo")) {
            $filtrosFallas["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtrosFallas["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtrosFallas["dateToday"] = $dateToday->format("Y-m-d");
            $filtrosFallas["previousDate"] = $this->generarPreviousDate($filtrosFallas["tiempo"], $filtrosFallas["tipo_de_tiempo"]);
        }

        if ($filtrosFallas["tipo_filtracion"] == "average") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosFallas);
        } else {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosFallas);
        }

        if ($productosFallas == null) {
            return ApiResponse::error("Por favor pase un tipo de filtro average o sales", 400);
        }

        $productosFallas = $this->product->calcularAOProducts($productosFallas);
        $productosFallas = $this->product->removerProductosConPedidosAutomaticos($productosFallas);
        $productosFallas = $this->product->actualizarElSolicitadoConElAO($productosFallas);

        if ($filtrosFallas["tipo_filtracion"] == "combinado") {
            foreach ($productosFallas as $producto) {
                $producto->solicitar = (($producto->promedio_calculado + $producto->total_sold_completed) / 2 - $producto->lote_quantity - $producto->totalQuantityInAutoOrder) * -1;
            }
        }

        // Para productos con ventas 0 y stock 0, deben ser negativos (fallas)
        // NO forzar a 1, deben calcularse como negativos
        foreach ($productosFallas as $producto) {
            $ventasCero = ($producto->total_sold_completed ?? 0) == 0;
            $stockCero = ($producto->lote_quantity ?? 0) == 0;
            $aoActual = $producto->totalQuantityInAutoOrder ?? 0;

            if ($ventasCero && $stockCero) {
                $aoActual = $producto->totalQuantityInAutoOrder ?? 0;

                if (($filtrosFallas['stock'] ?? '') === 'fallas') {
                    $producto->solicitar = 0;
                } else {
                    $producto->solicitar = 0 - $aoActual;
                }

                // Si no hay AO, debe ser negativo (falla)
                if ($aoActual == 0) {
                    $producto->solicitar = -1; // Falla: necesita al menos 1
                }
            }
        }

        $tempReponer = $this->productSupplier->getSupplierToReplenishTheProducts($productosFallas, $request->con_descuento);
        $tempReponer = $this->productSupplier->checkTolerance($tempReponer, $request->con_descuento);

        $respuesta["productos_a_reponer"] = $this->orderByDiscount($tempReponer);
        $respuesta["productosFallas"] = $productosFallas;

        // $respuesta["productos_oportunidad_unica"] = $this->getOptimizedUniqueOpportunities($request);
        $respuesta["productos_oportunidad_unica"] = [];

        return ApiResponse::success($respuesta, "ok", 200);
    }


    public function generarPreviousDate($cantidad = "0", $tiempo = "days")
    {
        $timeZone = new DateTimeZone(config("app.timezone"));
        $fecha = new DateTime("now", $timeZone);
        $fecha->modify("-" . $cantidad . " " . $tiempo);
        return $fecha->format("Y-m-d");
    }

    public function generarOrden(Request $request): JsonResponse
    {
        $withoutSupplierIds = $request->input('without_supplier_ids', []);
        $listAutoOrders = $this->autoOrder->createMultiple($request->orders, $withoutSupplierIds);


        return ApiResponse::success($listAutoOrders, "ok", 200);
    }

    public function consultarProductosSinProveedor(Request $request): JsonResponse
    {

        $timeZone = new DateTimeZone(config("app.timezone"));
        $dateToday = new DateTime("now", $timeZone);

        $productos = null;
        $filtros = [
            "tipo_filtracion" => $request->tipo_filtracion,
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
            "stock" => "all",
            "dateToday" => null,
            "previousDate" => null,
            "orderBy" => "asc",
            "sortBy" => "name",
            "ids" => $request->ids ?? null,

        ];

        if ($request->filled("lapso_de_tiempo")) {
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
            $filtros["previousDate"] = $this->generarPreviousDate($filtros["tiempo"], $filtros["tipo_de_tiempo"]);
        }


        if ($filtros["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }


        foreach ($request->idsConFantante as $key => $value) {

            for ($index = 0; $index < count($productos); $index++) {

                if ($productos[$index]->id == $value["id"]) {
                    $productos[$index]->stockFaltante = $value["solicitar"];
                }
            }
        }
        return ApiResponse::success($productos, "ok", 200);
    }
    private function getOptimizedUniqueOpportunities(Request $request)
    {
        $cacheKey = 'sorted_ids_' . md5(json_encode([
            'lab' => $request->laboratoryId,
            'groups' => $request->groups,
            'tipo' => $request->tipo_filtracion,
            'desc' => $request->con_descuento,
        ]));

        $sortedIds = Cache::remember($cacheKey, 600, function () use ($request) {

            $timeZone = new DateTimeZone(config("app.timezone"));
            $dateToday = new DateTime("now", $timeZone);

            $filtros = [
                "tipo_filtracion" => $request->tipo_filtracion,
                "lapso_de_tiempo" => "1 year",
                "dateToday" => $dateToday->format("Y-m-d h:m:s"),
                "previousDate" => $this->generarPreviousDate("1", "year"),
                "orderBy" => "asc",
                "sortBy" => "name",
            ];

            if ($request->filled("laboratoryId"))
                $filtros["laboratoryId"] = $request->laboratoryId;
            if ($request->filled("groups"))
                $filtros["groups"] = $request->groups;

            if ($filtros["tipo_filtracion"] == "average") {
                $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
            } else {
                $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
            }

            $productos = $this->product->calcularAOProducts($productos);
            $productos = $this->product->removerProductosConPedidosAutomaticos($productos);
            $productos = $this->product->actualizarElSolicitadoConElAO($productos);

            $tempOportunidad = $this->productSupplier->getSupplierToReplenishTheProductsWithoutValidateSolicitar($productos, $request->con_descuento);
            $tempOportunidad = $this->productSupplier->checkTolerance($tempOportunidad, $request->con_descuento);
            $tempOportunidad = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($tempOportunidad);

            $listaOrdenada = $this->orderByDiscount($tempOportunidad);

            return collect($listaOrdenada)->map(function ($item) {
                return $item['product']->id;
            })->values()->all();
        });

        $page = $request->input('page', 1);
        $perPage = 20;

        $totalItems = count($sortedIds);
        $idsPaginaActual = collect($sortedIds)->forPage($page, $perPage)->values();

        if ($idsPaginaActual->isEmpty()) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], $totalItems, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query()
            ]);
        }

        $timeZone = new DateTimeZone(config("app.timezone"));
        $dtNow = new DateTime("now", $timeZone);
        $dateTodayStr = $dtNow->format("Y-m-d H:i:s");
        $previousDateStr = $this->generarPreviousDate("1", "year");

        $productosDB = ModelsProduct::select(
            'products.*',

            DB::raw('(
                SELECT COALESCE(MIN(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_min'),

            DB::raw('(
                SELECT COALESCE(MAX(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_max'),

            DB::raw("(
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = 'Completed'
                AND o.created_at BETWEEN '$previousDateStr' AND '$dateTodayStr'
            ) AS total_group_sales"),
            DB::raw('sales_average * 12 AS promedio_calculado')

        )
            ->whereIn('id', $idsPaginaActual)
            ->get();

        $productosDB = $this->product->calcularAOProducts($productosDB);
        $productosDB = $this->product->removerProductosConPedidosAutomaticos($productosDB);
        $productosDB = $this->product->actualizarElSolicitadoConElAO($productosDB);

        $itemsFinales = $this->productSupplier->getSupplierToReplenishTheProductsWithoutValidateSolicitar($productosDB, $request->con_descuento);
        $itemsFinales = $this->productSupplier->checkTolerance($itemsFinales, $request->con_descuento);
        $itemsFinales = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($itemsFinales);

        $itemsFinalesOrdenados = $this->orderByDiscount($itemsFinales);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsFinalesOrdenados,
            $totalItems,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
    public function getUniqueOpportunityPagination(Request $request): JsonResponse
    {
        // $paginacion = $this->getOptimizedUniqueOpportunities($request);
        $paginacion = ["data" => [], "total" => 0];
        return ApiResponse::success($paginacion, "ok", 200);
    }
    private function orderByDiscount(array $listaProductos): array
    {
        return collect($listaProductos)->sortByDesc(function ($item) {
            $producto = $item['product'];
            $oferta = $item['productSupplier'];
            $precioBase = (float) ($producto->unit_cost ?? 0);
            $precioOferta = (float) ($oferta->unit_cost_with_discount > 0
                ? $oferta->unit_cost_with_discount
                : $oferta->unit_cost);
            $precioOferta = $precioOferta ?: 0;
            if ($precioBase <= 0) {
                return -9999;
            }
            $descuento = (($precioBase - $precioOferta) / $precioBase) * 100;

            return $descuento;

        })->values()->all();
    }


    public function getProductosMarcados(Request $request): JsonResponse
    {
        /*try {
            $perPage = $request->query('perPage', 10);
            $sortBy = $request->query('sortBy', 'id');
            $order = $request->query('order', 'desc');
            $productos = $this->autoOrder->getMarkedProductsWithoutSupplier(
                (int) $perPage, 
                $sortBy, 
                $order
            );

            return response()->json($productos);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener productos'], 500);
        }*/

        try {

            $respuesta = [
                "tipo_filtracion" => $request->tipo_filtracion,
                "tipo_vista" => $request->tipo_vista,
                "paginate" => [],
            ];

            $filtros = [
                "itemsPerPage" => $request->itemsPerPage,
                "page" => $request->page,
                "tipo_filtracion" => $request->tipo_filtracion,
                "tipo_vista" => $request->tipo_vista,
                "lapso_de_tiempo" => $request->lapso_de_tiempo,
                "is_ordered" => true,
            ];

            if ($request->filled("orderBy") && $request->filled("sortBy")) {
                $filtros["orderBy"] = $request->orderBy;
                $filtros["sortBy"] = $request->sortBy;
            }

            if ($request->filled("stock") && $request->stock !== 'all') {
                $filtros["stock"] = $request->stock;
            }

            if ($request->filled("laboratoryId")) {
                $filtros["laboratoryId"] = $request->laboratoryId;
            }

            if ($request->filled("groups")) {
                $filtros["groups"] = $request->groups;
            }

            if ($request->filled("lapso_de_tiempo")) {
                $timeZone = new DateTimeZone(config("app.timezone"));
                $dateToday = new DateTime("now", $timeZone);
                $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
                $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
                $previousDate = new DateTime("now", $timeZone);
                $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
                $filtros["dateToday"] = $dateToday->format("Y-m-d h:m:s");
                $filtros["previousDate"] = $previousDate->format("Y-m-d");
            }

            if ($respuesta["tipo_filtracion"] == "sales") {
                $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeSales($filtros);
            } else {
                $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
            }

            $respuesta["paginate"]->each(function ($items) use ($filtros) {
                $items = $this->product->calcularAOProduct($items);
                $ventasCero = ($items->total_sold_completed ?? 0) == 0;
                $stockCero = ($items->lote_quantity ?? 0) == 0;
                $esProductoSinVentasNiStock = $ventasCero && $stockCero;

                if ($filtros["tipo_filtracion"] == "combinado") {
                    $filtrosVentas = $filtros;
                    $filtrosVentas["id"] = $items->id;
                    $itemVentas = $this->product->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosVentas)->first();

                    if ($itemVentas) {
                        $itemVentas = $this->product->calcularAOProduct($itemVentas);

                        $ventasTotales = $itemVentas->total_sold_completed ?? 0;
                        $promedio = $items->promedio_calculado ?? 0;
                        $stockActual = $items->lote_quantity ?? 0;
                        $autoOrder = $items->totalQuantityInAutoOrder ?? 0;

                        $resultado = (($ventasTotales + $promedio) / 2) - $stockActual - $autoOrder;

                        $items->solicitar = -$resultado;
                    } else {
                        $promedio = $items->promedio_calculado ?? 0;
                        $stockActual = $items->lote_quantity ?? 0;
                        $autoOrder = $items->totalQuantityInAutoOrder ?? 0;

                        $resultado = $promedio - $stockActual - $autoOrder;

                        $items->solicitar = -$resultado;
                    }

                    $items->solicitar = $items->solicitar > 0 ? ceil($items->solicitar) : floor($items->solicitar);
                } else {
                    $stock = $items->lote_quantity ?? 0;
                    $ventas = $items->total_sold_completed ?? 0;
                    // demanda - stock - AO (positivo = necesita pedir, negativo = exceso)
                    $items->solicitar = $ventas - $stock - $aoActual;
                }
                if ($esProductoSinVentasNiStock) {
                    $aoActual = $items->totalQuantityInAutoOrder ?? 0;
                    $items->solicitar = 0 - $aoActual;
                    if ($aoActual == 0) {
                        $items->solicitar = -1;
                    }
                }
            });

            return ApiResponse::success($respuesta, "ok", 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener productos'], 500);
        }
    }

    public function toggleScarce(Request $request, $id)
    {
        $product = ModelsProduct::findOrFail($id);
        $product->update([
            'is_scarce' => !$product->is_scarce,
        ]);

        return ApiResponse::success(["is_scarce" => $product->is_scarce], "Estado actualizado");
    }

    public function directOrder(Request $request)
    {
        $request->validate([
            'productId' => 'required',
            'quantity' => 'required|numeric|min:1',
        ]);

        $productId = $request->productId;
        $quantity = $request->quantity;

        // Buscar el proveedor mas barato con barcode
        $cheapestProvider = \App\Models\ProductSupplier::where('product_id', $productId)
            ->whereNotNull('barcode_match')
            ->where('barcode_match', '!=', '')
            ->where('unit_cost_usd', '>', 0)
            ->orderBy('unit_cost_usd', 'asc')
            ->first();

        if (!$cheapestProvider) {
            return ApiResponse::error("No se encontró un proveedor con código de barras para este producto.");
        }

        $queryService = app(\App\Services\Suppliers\SupplierQueryService::class);

        $mockRequest = new Request();
        $mockRequest->replace([
            'productId' => $cheapestProvider->id,
            'main_product_id' => $productId,
            'quantity' => $quantity,
            'discount' => false
        ]);

        $results = $queryService->addProductToOrder($mockRequest);

        if ($results['success']) {
            return ApiResponse::success(
                ['warning' => $results['warning']],
                'Producto añadido al pedido directamente'
            );
        }

        return ApiResponse::error('No se pudo procesar el pedido directo');
    }

}
