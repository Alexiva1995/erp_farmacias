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
        protected AutoOrder $autoOrder,
        protected \App\Services\Reports\IaAssistantReportService $iaAssistantReportService
    ) {
    }


    public function filtrarPaginate(Request $request): JsonResponse
    {
        $respuesta = [
            "tipo_filtracion" => $request->tipo_filtracion,
            "tipo_vista" => $request->tipo_vista,
            "paginate" => [],
        ];

        $filtros = $this->prepararFiltros($request);

        $esVistaGrupal = filter_var($filtros['tipo_vista'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($esVistaGrupal) {
            // Vista grupal: devolver grupos paginados con productos anidados
            $respuesta["paginate"] = $this->iaAssistantReportService->getGroupedReportWithPaginate($filtros);
        } else {
            // Vista individual: paginación normal
            if ($respuesta["tipo_filtracion"] == "combinado") {
                $respuesta["paginate"] = $this->iaAssistantReportService->getFilteredReportWithPaginate($filtros);
            } elseif ($respuesta["tipo_filtracion"] == "average") {
                $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
            } elseif ($respuesta["tipo_filtracion"] == "sales") {
                $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeSales($filtros);
            } else {
                $respuesta["paginate"] = $this->product->filtrarIaOrderAssistantTypeAverage($filtros);
            }
        }

        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function stats(Request $request): JsonResponse
    {
        $filtros = $this->prepararFiltros($request);
        
        // Obtenemos todos los productos filtrados sin paginar
        if ($request->tipo_filtracion == "combinado") {
            // El servicio ya tiene un método para obtener sin paginar
            $items = $this->iaAssistantReportService->getFilteredReportWithoutPaginate($filtros);
        } elseif ($request->tipo_filtracion == "sales") {
            $items = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        } else {
            $items = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }

        $stats = [
            'necesitan' => 0,
            'exceso' => 0,
            'ok' => 0
        ];

        foreach ($items as $item) {
            $solicitarRounded = $this->roundIaAnalysis($item->solicitar);
            $loteQuantity = (float)($item->lote_quantity ?? 0);

            if ($solicitarRounded > 0 || ($solicitarRounded == 0 && $loteQuantity <= 0)) {
                $stats['necesitan']++;
            } elseif ($solicitarRounded < 0) {
                $stats['exceso']++;
            } else {
                $stats['ok']++;
            }
        }

        return ApiResponse::success($stats, "ok", 200);
    }

    private function prepararFiltros(Request $request): array
    {
        $filtros = [
            "itemsPerPage" => (int) ($request->itemsPerPage ?? 10),
            "page" => (int) ($request->page ?? 1),
            "tipo_filtracion" => $request->tipo_filtracion,
            "tipo_vista" => filter_var($request->tipo_vista, FILTER_VALIDATE_BOOLEAN),
            "lapso_de_tiempo" => $request->lapso_de_tiempo,
        ];

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        if ($request->filled("q")) {
            $filtros["q"] = $request->q;
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
            $filtros["dateToday"] = $dateToday->format("Y-m-d H:i:s");
            $filtros["previousDate"] = $previousDate->format("Y-m-d 00:00:00");
        }

        return $filtros;
    }

    private function roundIaAnalysis($value)
    {
        if ($value === null || $value === "" || !is_numeric($value)) return 0;
        
        $num = (float)$value;
        if ($num == 0) return 0;
        
        $sign = $num > 0 ? 1 : -1;
        $abs = abs($num);
        $floor = floor($abs);
        $decimal = $abs - $floor;
        
        $roundedAbs = $decimal > 0.333 ? ceil($abs) : $floor;
        
        $result = $roundedAbs * $sign;
        return $result == 0 ? 0 : (int)$result;
    }

    public function generateListProductoToRequest(Request $request): JsonResponse
    {
        $filtros = $request->all();
        $filtros['stock'] = $request->get('stock', 'fallas');

        // Para retrocompatibilidad y conteo inicial, traemos la lista completa pero ligera
        // Aunque el frontend ahora usará paginación, este endpoint puede devolver los totales por pestaña
        $reporte = $this->iaAssistantReportService->getReplenishReportPaginated([...$filtros, 'itemsPerPage' => 9999]);
        
        $respuesta = [
            "productos_a_reponer" => [], // Se vacía para forzar uso de paginación o se deja la primera página?
            // Mejor devolver la primera página para carga inicial rápida
            "productos_a_reponer" => $this->iaAssistantReportService->getReplenishReportPaginated([...$filtros, 'page' => 1, 'itemsPerPage' => 20])->items(),
            "totalFallas" => $reporte->total(), // Este es el total de "Encontrados"
            "productosFallas" => [], // Opcional si se necesita la lista completa de fallas brutas
        ];

        return ApiResponse::success($respuesta, "ok", 200);
    }

    public function getReplenishPagination(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            $data = $this->iaAssistantReportService->getReplenishReportAll($request->all());
            return ApiResponse::success($data, "ok", 200);
        }

        $paginacion = $this->iaAssistantReportService->getReplenishReportPaginated($request->all());
        return ApiResponse::success($paginacion, "ok", 200);
    }


    public function generarPreviousDate($cantidad = "0", $tiempo = "days")
    {
        $timeZone = new DateTimeZone(config("app.timezone"));
        $fecha = new DateTime("now", $timeZone);
        $fecha->modify("-" . $cantidad . " " . $tiempo);
        return $fecha->format("Y-m-d 00:00:00");
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
            $filtros["dateToday"] = $dateToday->format("Y-m-d H:i:s");
            $filtros["previousDate"] = $this->generarPreviousDate($filtros["tiempo"], $filtros["tipo_de_tiempo"]);
        }


        if ($filtros["tipo_filtracion"] == "average" || $filtros["tipo_filtracion"] == "combinado") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        } elseif ($filtros["tipo_filtracion"] == "sales") {
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
                "lapso_de_tiempo" => $request->lapso_de_tiempo ?? "1 year",
                "dateToday" => $dateToday->format("Y-m-d H:i:s"),
                "previousDate" => $this->generarPreviousDate("1", "year"),
                "orderBy" => $request->orderBy ?? "asc",
                "sortBy" => $request->sortBy ?? "name",
                "q" => $request->q,
                "stock" => $request->stock,
                "isColombian" => filter_var($request->isColombian, FILTER_VALIDATE_BOOLEAN),
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
                    $aoActual = $items->totalQuantityInAutoOrder ?? 0;
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

    public function toggleScarce(int $id): JsonResponse
    {
        $product = ModelsProduct::findOrFail($id);
        
        $actionService = app(\App\Services\Products\ProductActionService::class);
        $updatedProduct = $actionService->toggleScarceProduct($product);

        return response()->json([
            'message' => 'Estado de escasez actualizado con éxito.',
            'is_scarce' => $updatedProduct->is_scarce
        ]);
    }
}
