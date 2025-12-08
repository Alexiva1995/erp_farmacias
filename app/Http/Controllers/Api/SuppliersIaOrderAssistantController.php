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
                $items->solicitar = $items->solicitar + $items->totalQuantityInAutoOrder;
            }
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

        if ($request->filled("lapso_de_tiempo")) {
            $filtrosFallas["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtrosFallas["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtrosFallas["dateToday"] = $dateToday->format("Y-m-d");
            $filtrosFallas["previousDate"] = $this->generarPreviousDate($filtrosFallas["tiempo"], $filtrosFallas["tipo_de_tiempo"]);
        }

        if ($filtrosFallas["tipo_filtracion"] == "average") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosFallas);
        } else if ($filtrosFallas["tipo_filtracion"] == "sales") {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtrosFallas);
        } else {
            $productosFallas = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtrosFallas);
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

        $tempReponer = $this->productSupplier->getSupplierToReplenishTheProducts($productosFallas, $request->con_descuento);
        $tempReponer = $this->productSupplier->checkTolerance($tempReponer, $request->con_descuento);

        $respuesta["productos_a_reponer"] = $this->orderByDiscount($tempReponer);
        $respuesta["productosFallas"] = $productosFallas;

        $respuesta["productos_oportunidad_unica"] = $this->getOptimizedUniqueOpportunities($request);
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
        $listAutoOrders = $this->autoOrder->createMultiple($request->orders);

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

        if (empty($request->ids)) {
            return ApiResponse::success([], "ok", 200);
        }

        if ($request->filled("lapso_de_tiempo")) {
            $filtros["tipo_de_tiempo"] = explode(" ", $request->lapso_de_tiempo)[1];
            $filtros["tiempo"] = explode(" ", $request->lapso_de_tiempo)[0];
            $filtros["dateToday"] = $dateToday->format("Y-m-d H:i:s");

            $filtros["previousDate"] = $this->generarPreviousDate($filtros["tiempo"], $filtros["tipo_de_tiempo"]);
        }

        if ($filtros["tipo_filtracion"] == "average") {
            $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $productos = $this->product->filtrarIaOrderAssistantTypeSalesWithoutPaginate($filtros);
        }

        // Asignar el stock faltante
        if ($request->filled('idsConFantante') && $productos) {
            foreach ($request->idsConFantante as $key => $value) {
                foreach ($productos as $producto) {
                    if ($producto->id == $value["id"]) {
                        $producto->stockFaltante = $value["solicitar"];
                        break;
                    }
                }
            }
        }

        return ApiResponse::success($productos, "ok", 200);
    }
    private function getOptimizedUniqueOpportunities(Request $request)
    {
        // 1. GENERAR LLAVE DE CACHÉ
        $cacheKey = 'sorted_ids_' . md5(json_encode([
            'lab' => $request->laboratoryId,
            'groups' => $request->groups,
            'tipo' => $request->tipo_filtracion,
            'desc' => $request->con_descuento,
        ]));

        // Cacheamos la lógica pesada para obtener los IDs ordenados
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

            // Obtener productos base
            if ($filtros["tipo_filtracion"] == "average") {
                $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
            } else {
                $productos = $this->product->filtrarIaOrderAssistantTypeAverageWithoutPaginate($filtros);
            }

            // Cálculos previos
            $productos = $this->product->calcularAOProducts($productos);
            $productos = $this->product->removerProductosConPedidosAutomaticos($productos);
            $productos = $this->product->actualizarElSolicitadoConElAO($productos);

            // Filtrado de oportunidades
            $tempOportunidad = $this->productSupplier->getSupplierToReplenishTheProductsWithoutValidateSolicitar($productos, $request->con_descuento);
            $tempOportunidad = $this->productSupplier->checkTolerance($tempOportunidad, $request->con_descuento);
            $tempOportunidad = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($tempOportunidad);

            $listaOrdenada = $this->orderByDiscount($tempOportunidad);

            // Retornamos solo los IDs ordenados
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

        // 2. PREPARAR FECHAS PARA SUBQUERIES
        $timeZone = new DateTimeZone(config("app.timezone"));
        $dtNow = new DateTime("now", $timeZone);
        $dateTodayStr = $dtNow->format("Y-m-d H:i:s");
        $previousDateStr = $this->generarPreviousDate("1", "year");

        // 3. HIDRATAR TODOS LOS DATOS (SIN PAGINACIÓN)
        $productosDB = ModelsProduct::select(
            'products.*',
            // Costo Mínimo
            DB::raw('(
                SELECT COALESCE(MIN(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_min'),

            // Costo Máximo
            DB::raw('(
                SELECT COALESCE(MAX(unit_cost), 0)
                FROM product_lots 
                WHERE product_lots.product_id = products.id
                AND product_lots.quantity > 0
                AND (product_lots.expiration_date IS NULL OR product_lots.expiration_date >= CURDATE())
            ) AS cost_max'),

            // Ventas Grupales
            DB::raw("(
                SELECT COALESCE(SUM(od.quantity), 0)
                FROM order_details od
                JOIN orders o ON o.id = od.order_id
                JOIN products p ON p.id = od.product_id
                WHERE p.group_id = products.group_id
                AND o.status = 'Completed'
                AND o.created_at BETWEEN '$previousDateStr' AND '$dateTodayStr'
            ) AS total_group_sales"),

            // Promedio Anual
            DB::raw('sales_average * 12 AS promedio_calculado')
        )
            ->whereIn('id', $sortedIds)
            ->get();

        // 4. PROCESAR OBJETOS
        $productosDB = $this->product->calcularAOProducts($productosDB);
        $productosDB = $this->product->removerProductosConPedidosAutomaticos($productosDB);
        $productosDB = $this->product->actualizarElSolicitadoConElAO($productosDB);

        // 5. RE-ASOCIAR CON PROVEEDORES
        $itemsFinales = $this->productSupplier->getSupplierToReplenishTheProductsWithoutValidateSolicitar($productosDB, $request->con_descuento);
        $itemsFinales = $this->productSupplier->checkTolerance($itemsFinales, $request->con_descuento);
        $itemsFinales = $this->productSupplier->obtainProductsWithUniqueMarketOpportunities($itemsFinales);

        // 6. RE-ORDENAR FINAL (Para mantener el orden de descuento)
        $itemsFinalesOrdenados = $this->orderByDiscount($itemsFinales);

        // Retornamos el array puro
        return array_values($itemsFinalesOrdenados);
    }
    public function getUniqueOpportunityPagination(Request $request): JsonResponse
    {
        $paginacion = $this->getOptimizedUniqueOpportunities($request);
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

}
