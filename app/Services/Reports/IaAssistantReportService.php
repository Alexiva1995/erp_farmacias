<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class IaAssistantReportService
{
    public function __construct(
        protected \App\Contracts\Product $productRepository,
        protected \App\Contracts\ProductSupplier $productSupplierRepository
    ) {
    }

    /**
     * Orquesta el reporte filtrado con paginación USANDO CACHÉ DE IDs (Estilo IA Assistant)
     */
    public function getFilteredReportWithPaginate(array $filtros)
    {
        $filtros = $this->prepareDateFilters($filtros);
        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        $page = (int) ($filtros['page'] ?? 1);
        $perPage = (int) ($filtros['itemsPerPage'] ?? 25);
        if ($perPage <= 0) $perPage = 999999;

        $esVistaGrupal = filter_var($filtros['tipo_vista'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 1. Hidratar todos los candidatos devueltos por la consulta base (universo filtrado por SQL)
        if ($tipo === 'sales') {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros);
        } else {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        }

        // 2. Procesar cálculos adicionales (AO, Combinado, etc.)
        if ($tipo === 'combinado') {
            $procesado = $this->processCombinedReport($resultado, $filtros);
        } else {
            $procesado = $this->processRegularReport($resultado, $tipo, $filtros);
        }

        // 3. Consolidar productos unificados (grupos) para tener el stock, ventas y 'solicitar' real del grupo
        $procesado = $this->consolidateCollection($procesado, $filtros);

        // 4. Filtrar estrictamente por estado de stock pos-procesamiento (Fallas / Exceso)
        $stockFilter = $filtros['stock'] ?? 'all';
        if ($stockFilter === 'fallas') {
            $procesado = $procesado->filter(fn($p) => (float)($p->solicitar ?? 0) > 0);
        } elseif ($stockFilter === 'exceso') {
            $procesado = $procesado->filter(fn($p) => (float)($p->solicitar ?? 0) < 0);
        }

        // 5. Ordenamiento dinámico sobre el universo completo de fallas
        $shortBy = $filtros['sortBy'] ?? 'solicitar';
        $orderDir = strtolower($filtros['orderBy'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $procesado = ($orderDir === 'desc') 
            ? $procesado->sortByDesc($shortBy) 
            : $procesado->sortBy($shortBy);
        
        $procesado = $procesado->values();
        $total = $procesado->count();

        // 6. Aplicar la paginación (Corte exacto de perPage sobre el universo filtrado real)
        $offset = ($page - 1) * $perPage;
        $itemsPagina = $procesado->slice($offset, $perPage)->values();

        // 7. Hidratar tendencia de ventas y proveedores SOLO para los 25 ítems visibles de la página
        if (filter_var($filtros['with_trend'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->hydrateSalesTrend($itemsPagina);
        }

        if (filter_var($filtros['with_suppliers'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->hydrateSuppliers($itemsPagina, $filtros);
        }

        // 8. Devolver paginador exacto con la página cortada
        return new LengthAwarePaginator($itemsPagina, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function getFilteredIds(array $filtros, bool $porGrupo = false): array
    {
        $filtrosLigero = $filtros;
        unset($filtrosLigero['page'], $filtrosLigero['itemsPerPage']);
        
        return $this->productRepository->getUniqueIdsForIaReport($filtrosLigero, $porGrupo);
    }

    public function countFilteredProducts(array $filtros): int
    {
        $filtros = $this->prepareDateFilters($filtros);
        return count($this->getFilteredIds($filtros, false));
    }

    /**
     * Orquesta el reporte filtrado SIN paginación (Exportación)
     */
    public function getFilteredReportWithoutPaginate(array $filtros)
    {
        $filtros = $this->prepareDateFilters($filtros);
        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        
        // Obtener los IDs filtrados (Fallas, etc.) para asegurar que el conteo coincida
        $allIds = $this->getFilteredIds($filtros, false);
        
        if (empty($allIds)) return collect([]);

        $filtrosHidratacion = $filtros;
        $filtrosHidratacion['ids_in'] = $allIds;
        
        if ($tipo === 'sales') {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosHidratacion);
            $procesado = $this->processRegularReport($resultado, $tipo, $filtros);
        } else {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosHidratacion);
            if ($tipo === 'combinado') {
                $procesado = $this->processCombinedReport($resultado, $filtros);
            } else {
                $procesado = $this->processRegularReport($resultado, $tipo, $filtros);
            }
        }

        $this->hydrateSalesTrend($procesado);
        
        // Siempre hidratar proveedores para exportación y ordenar por ellos
        $this->hydrateSuppliers($procesado, $filtros);

        // Consolidar productos unificados
        $procesado = $this->consolidateCollection($procesado, $filtros);

        $procesado = $procesado->sortBy(function($p) {
            return $p->best_supplier->name ?? 'ZZZ'; // Los sin proveedor al final
        })->values();

        return $procesado;
    }

    /**
     * Hidrata los productos con su tendencia de ventas real de los últimos 6 meses
     */
    private function hydrateSalesTrend($products): void
    {
        $items = ($products instanceof LengthAwarePaginator) ? $products->getCollection() : collect($products);
        if ($items->isEmpty()) return;

        $productIds = $items->pluck('id')->toArray();
        $sixMonthsAgo = now()->subMonths(11)->startOfMonth(); // 12 meses incluyendo el actual

        // 1. Generar la estructura base de los últimos 6 meses con valores en 0
        $baseTrend = [];
        for ($i = 0; $i < 12; $i++) {
            $date = $sixMonthsAgo->copy()->addMonths($i);
            $key = $date->format('Y') . '-' . (int)$date->format('n');
            $baseTrend[$key] = [
                'label' => $this->getMonthName((int)$date->format('n')),
                'value' => 0
            ];
        }

        // 2. Consultar ventas reales para los productos de la página
        $salesData = \Illuminate\Support\Facades\DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->select(
                'order_details.product_id',
                \Illuminate\Support\Facades\DB::raw('YEAR(orders.created_at) as year'),
                \Illuminate\Support\Facades\DB::raw('MONTH(orders.created_at) as month'),
                \Illuminate\Support\Facades\DB::raw('SUM(order_details.quantity) as total')
            )
            ->whereIn('order_details.product_id', $productIds)
            ->where('orders.status', 'Completed')
            ->where('orders.created_at', '>=', $sixMonthsAgo->format('Y-m-d'))
            ->groupBy('order_details.product_id', 'year', 'month')
            ->get();

        // 3. Mapear los resultados de la DB
        $itemSalesMap = [];
        foreach ($salesData as $row) {
            $pId = (int)$row->product_id;
            $key = (int)$row->year . '-' . (int)$row->month;
            $itemSalesMap[$pId][$key] = (float)$row->total;
        }

        // 4. Asignar la tendencia normalizada a cada producto
        $items->transform(function ($product) use ($baseTrend, $itemSalesMap) {
            $id = is_array($product) ? ($product['id'] ?? null) : ($product->id ?? null);
            if (!$id) return $product;

            $productTrend = $baseTrend;
            $sales = $itemSalesMap[(int)$id] ?? [];
            
            foreach ($sales as $dateKey => $qty) {
                if (isset($productTrend[$dateKey])) {
                    $productTrend[$dateKey]['value'] = $qty;
                }
            }

            $finalValues = array_values(array_column($productTrend, 'value'));
            $finalLabels = array_values(array_column($productTrend, 'label'));

            if (is_array($product)) {
                $product['sales_trend'] = $finalValues;
                $product['sales_trend_labels'] = $finalLabels;
            } else {
                // Usar setAttribute para asegurar que Laravel lo incluya en el JSON (Eloquents)
                if (method_exists($product, 'setAttribute')) {
                    $product->setAttribute('sales_trend', $finalValues);
                    $product->setAttribute('sales_trend_labels', $finalLabels);
                } else {
                    $product->sales_trend = $finalValues;
                    $product->sales_trend_labels = $finalLabels;
                }
            }
            
            return $product;
        });
    }

    /**
     * Hidrata los productos con la mejor oferta de proveedor disponible.
     */
    private function hydrateSuppliers($products, array $filtros): void
    {
        $conDescuento = filter_var($filtros['con_descuento'] ?? true, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";
        
        $items = ($products instanceof \Illuminate\Support\Collection) ? $products : collect($products);
        if ($items->isEmpty()) return;

        $items = $items->values();
        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection($items);
        
        $skipAiMatch = filter_var($filtros['skip_ai_match'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $itemsWithSuppliers = $this->productSupplierRepository->getSupplierToReplenishTheProducts($eloquentCollection, $conDescuento, $skipAiMatch);
        $itemsWithSuppliers = $this->productSupplierRepository->checkTolerance($itemsWithSuppliers, $conDescuento);
        
        foreach ($items as $index => $producto) {
            $supplierData = $itemsWithSuppliers[$index] ?? null;
            if ($supplierData) {
                $bestSupplier = $supplierData['supplier'] ?? null;
                if ($bestSupplier) {
                    $bestSupplier = clone $bestSupplier;
                }
                if ($bestSupplier && isset($supplierData['productSupplier'])) {
                    $bestSupplier->setAttribute('product_suppliers_id', $supplierData['productSupplier']->id ?? null);
                    $bestSupplier->setAttribute('unit_cost_usd_with_discount', $supplierData['productSupplier']->unit_cost_usd_with_discount ?? 0);
                    $bestSupplier->setAttribute('is_ai_matched', (bool)($supplierData['productSupplier']->is_ai_matched ?? false));
                    // Pasar también el nombre de la oferta sugerida para el modal
                    $bestSupplier->setAttribute('matched_name', $supplierData['productSupplier']->name ?? '');
                }
                $producto->setAttribute('best_supplier', $bestSupplier);
                $producto->setAttribute('best_supplier_price', $supplierData['precio_final_supplier'] ?? 0);
                $producto->setAttribute('best_supplier_percentage', $supplierData['percentageIncrease'] ?? 0);
            }
        }
    }

    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];
        return $months[$month] ?? '';
    }

    /**
     * Prepara los filtros de fecha
     */
    private function prepareDateFilters(array $filtros): array
    {
        if (!empty($filtros['lapso_de_tiempo'])) {
            $timeZone = new \DateTimeZone(config("app.timezone"));
            $dateToday = new \DateTime("now", $timeZone);
            
            $partes = explode(" ", $filtros['lapso_de_tiempo']);
            $filtros["tiempo"] = $partes[0];
            $filtros["tipo_de_tiempo"] = $partes[1] ?? 'months';
            
            $previousDate = new \DateTime("now", $timeZone);
            $previousDate->modify("-" . $filtros["tiempo"] . " " . $filtros["tipo_de_tiempo"]);
            
            $filtros["dateToday"] = $dateToday->format("Y-m-d H:i:s");
            // Se usa H:i:s para evitar problemas con la hora límite
            $filtros["previousDate"] = $previousDate->format("Y-m-d 00:00:00");
        }
        
        return $filtros;
    }

    /**
     * Procesa los reportes simples (Averages o Sales) sin cruces pesados
     */
    private function processRegularReport($resultados, string $tipo, array $filtros = [])
    {
        // $resultados puede ser un Paginator o una Collection
        $isPaginator = $resultados instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        $items = $isPaginator ? $resultados->getCollection() : collect($resultados);

        // Hidratación masiva de AO para evitar N+1
        $this->hydrateAutoOrderBulk($items, $filtros);

        $items->transform(function ($item) use ($tipo) {
            // La demanda ponderada en reportes simples es el valor base (ventas o promedio)
            $item->demanda_ponderada = ($tipo === 'sales') 
                ? ($item->total_sold_completed ?? 0) 
                : ($item->promedio_calculado ?? 0);

            // La fórmula regular que venía en el controlador: solicitar = solicitar
            // Nota: Ya se resta el AO en SQL, no volver a sumarlo aquí.
            $val = (float)($item->solicitar ?? 0);
            $item->solicitar = $val > 0 ? ceil($val) : floor($val);

            // Feature 2 y 3: hidratar flags de calidad del dato
            $this->hydrateProductFlags($item);

            return $item;
        });

        if ($isPaginator) {
            $resultados->setCollection($items);
            return $resultados;
        }

        return $items;
    }

    /**
     * Procesa el reporte COMBINADO resolviendo el N+1 a O(1)
     */
    private function processCombinedReport($resultados, array $filtros)
    {
        $isPaginator = $resultados instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        $items = $isPaginator ? $resultados->getCollection() : collect($resultados);

        if ($items->isEmpty()) {
            return $resultados;
        }

        // OBTENEMOS TODOS LOS IDs de esta página/lote (Matamos el N+1)
        $productIds = $items->pluck('id')->toArray();
        
        // Ejecutamos UNA sola consulta que traiga las "ventas" de estos IDs
        $filtrosVentas = $filtros;
        $filtrosVentas['ids_in'] = $productIds; // Nuevo filtro a implementar en Repo

        $ventasDb = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosVentas);
        
        // Convertimos a array asociativo por ID (Hash Map / Memoria) -> O(1) search
        // En lugar de hacer $col->first() en memoria O(N) lo hacemos O(1)
        $ventasMap = collect($ventasDb)->keyBy('id');

        // Hidratación masiva de AO para evitar N+1
        $this->hydrateAutoOrderBulk($items, $filtros);

        $items->transform(function ($item) use ($ventasMap) {
            // Buscar si tiene datos de venta en el mapa
            $itemVentas = $ventasMap->get($item->id);

            $promedio = $item->promedio_calculado ?? 0;
            $stockActual = $item->lote_quantity ?? 0;
            $autoOrder = $item->totalQuantityInAutoOrder ?? 0;

            if ($itemVentas) {
                $itemVentas = $this->productRepository->calcularAOProduct($itemVentas);
                $ventasTotales = $itemVentas->total_sold_completed ?? 0;
            } else {
                $ventasTotales = 0;
            }

            // Demanda ponderada combinada pura: siempre pondera (ventas + promedio) / 2
            $item->demanda_ponderada = ($ventasTotales + $promedio) / 2;

            // Fórmula combinada pura: ((ventas + promedio) / 2) - stock - AO
            $resultado = $item->demanda_ponderada - $stockActual - $autoOrder;

            // Invertir el signo para el análisis visual (faltante => positivo)
            // Sincronizar redondeo con SQL: ceil si > 0, floor si < 0
            $item->solicitar = $resultado > 0 ? ceil($resultado) : floor($resultado);

            // Feature 2 y 3: hidratar flags de calidad del dato
            $this->hydrateProductFlags($item);

            return $item;
        });

        return $items;
    }

    /**
     * Obtiene TODO el reporte de productos a reponer clasificados por tipo de análisis.
     * Diseñado para carga masiva inicial y paginación en el cliente.
     */
    public function getReplenishReportAll(array $filtros): array
    {
        $filtros = $this->prepareDateFilters($filtros);
        $conDescuento = filter_var($filtros['con_descuento'] ?? false, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";

        // 1. Obtener IDs base (fallas)
        $allIds = $this->getFilteredIds($filtros, false);
        if (empty($allIds)) {
            return [
                'increased' => [],
                'decreased' => [],
                'stable'    => [],
                'no_supplier' => [],
                'total'     => 0,
                'totalFallasBrutas' => 0
            ];
        }

        // 2. Hidratar productos
        $filtrosHidratacion = $filtros;
        $filtrosHidratacion['ids_in'] = $allIds;
        $tipoFiltracion = $filtros['tipo_filtracion'] ?? 'average';
        
        if ($tipoFiltracion === 'sales') {
            $productos = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosHidratacion);
        } else {
            $productos = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosHidratacion);
        }

        $productos = new \Illuminate\Database\Eloquent\Collection($productos);
        
        // El repositorio ya tiene 'solicitar' calculado en el SELECT base.
        // Como para el repositorio (fallas) 'solicitar' es positivo y el servicio de proveedores espera negativo para reponer, lo invertimos una vez.
        $productos->each(function($p) {
            $p->solicitar = -(float)($p->solicitar ?? 0);
        });

        $itemsReponer = $this->productSupplierRepository->getSupplierToReplenishTheProducts($productos, $conDescuento);
        $itemsReponer = $this->productSupplierRepository->checkTolerance($itemsReponer, $conDescuento);
        $itemsReponer = $this->consolidateReplenishCollection($itemsReponer, $filtros);

        // 4. Clasificar y ordenar
        $coleccion = collect($itemsReponer);
        
        // Antes de clasificar, invertimos de nuevo para el frontend: faltante => positivo
        $coleccion->each(function($item) {
            $item['product']->solicitar = -$item['product']->solicitar;
            // Redondear lógicamente: mantener el piso/techo según el signo original (que ahora es positivo)
            $item['product']->solicitar = $item['product']->solicitar > 0 ? ceil($item['product']->solicitar) : floor($item['product']->solicitar);
            
            // Regla de alza: si es incremento de precio, sugerir solo 1 unidad
            if (($item['increase'] ?? null) === true && $item['product']->solicitar > 0) {
                $item['product']->solicitar = 1;
            }
            
            // Sincronizar campo raíz
            $item['solicitar'] = $item['product']->solicitar;
        });

        $increased = $coleccion->filter(fn($i) => ($i['increase'] ?? null) === true);
        $decreased = $coleccion->filter(fn($i) => ($i['increase'] ?? null) === false);
        $stable    = $coleccion->filter(fn($i) => ($i['increase'] ?? null) === null);

        // Identificar productos sin proveedor
        $idsConProveedor = $coleccion->pluck('product.id')->unique()->toArray();
        $idsSinProveedor = array_diff($allIds, $idsConProveedor);
        
        $productosSinProveedor = [];
        if (!empty($idsSinProveedor)) {
             $filtrosSinProveedor = $filtros;
             $filtrosSinProveedor['ids_in'] = array_values($idsSinProveedor);
             if ($tipoFiltracion === 'sales') {
                 $productosSinProveedor = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosSinProveedor);
             } else {
                 $productosSinProveedor = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosSinProveedor);
             }
             
             // Invertir el signo para el frontend (ya que el repo lo devuelve positivo para falta)
             // No necesitamos procesarlos más porque el usuario ya vio estos números en la vista anterior
        }

        return [
            'increased' => $this->orderByDiscountCollection($increased, $conDescuento)->values()->all(),
            'decreased' => $this->orderByDiscountCollection($decreased, $conDescuento)->values()->all(),
            'stable'    => $this->orderByDiscountCollection($stable, $conDescuento)->values()->all(),
            'no_supplier' => $productosSinProveedor,
            'total'     => $coleccion->count(),
            'totalFallasBrutas' => count($allIds)
        ];
    }

    /**
     * Obtiene el reporte de productos a reponer (con proveedores) de forma paginada y filtrada por tipo.
     */
    public function getReplenishReportPaginated(array $filtros)
    {
        $filtros = $this->prepareDateFilters($filtros);
        $tipoAnalisis = $filtros['tipo_analisis'] ?? 'all'; // increased, decreased, stable, all
        $page = (int) ($filtros['page'] ?? 1);
        $perPage = (int) ($filtros['itemsPerPage'] ?? 20);
        if ($perPage <= 0) $perPage = 999999;
        $conDescuento = filter_var($filtros['con_descuento'] ?? false, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";

        // Reutilizamos la lógica de filtrado inicial
        $allIds = $this->getFilteredIds($filtros, false);
        
        if (empty($allIds)) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $filtrosHidratacion = $filtros;
        $filtrosHidratacion['ids_in'] = $allIds;
        
        $tipoFiltracion = $filtros['tipo_filtracion'] ?? 'average';
        if ($tipoFiltracion === 'sales') {
            $productos = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosHidratacion);
        } else {
            $productos = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosHidratacion);
        }

        $productos = new \Illuminate\Database\Eloquent\Collection($productos);
        $itemsReponer = $this->productSupplierRepository->getSupplierToReplenishTheProducts($productos, $conDescuento);
        $itemsReponer = $this->productSupplierRepository->checkTolerance($itemsReponer, $conDescuento);

        // Consolidar productos unificados para reponer
        $itemsReponer = $this->consolidateReplenishCollection($itemsReponer, $filtros);

        $coleccionFinal = collect($itemsReponer);

        if ($tipoAnalisis !== 'all') {
            $coleccionFinal = $coleccionFinal->filter(function ($item) use ($tipoAnalisis) {
                $increase = $item['increase'] ?? null;
                if ($tipoAnalisis === 'increased') return $increase === true;
                if ($tipoAnalisis === 'decreased') return $increase === false;
                if ($tipoAnalisis === 'stable') return $increase === null;
                return true;
            });
        }

        $itemsOrdenados = $this->orderByDiscountCollection($coleccionFinal, $conDescuento);

        $total = $itemsOrdenados->count();
        $offset = ($page - 1) * $perPage;
        $itemsPagina = $itemsOrdenados->slice($offset, $perPage)->values();

        return new LengthAwarePaginator($itemsPagina, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Feature 2: Detecta productos nuevos sin historial de ventas.
     * Feature 3: Detecta si el sales_average está desactualizado (> 48h).
     * Se aplica después de calcular solicitar en cada producto.
     */
    private function hydrateProductFlags($item): void
    {
        // Feature 3: sales_average obsoleto (más de 48 horas sin recalcular)
        $updatedAt = isset($item->sales_average_updated_at) && $item->sales_average_updated_at
            ? \Carbon\Carbon::parse($item->sales_average_updated_at)
            : null;
        $isStale = !$updatedAt || $updatedAt->lt(now()->subHours(48));

        if ($isStale) {
            try {
                $now = now();
                $windowStart = $now->copy()->subMonths(12);

                $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
                if ($isRestaurant) {
                    $totalSoldRaw = \Illuminate\Support\Facades\DB::table('inventory_movements')
                        ->where('product_id', $item->id)
                        ->where('quantity', '<', 0)
                        ->where('created_at', '>=', $windowStart)
                        ->sum('quantity');
                    $totalSold = $totalSoldRaw ? abs($totalSoldRaw) : 0;
                } else {
                    $totalSold = \Illuminate\Support\Facades\DB::table('order_details')
                        ->join('orders', 'order_details.order_id', '=', 'orders.id')
                        ->where('order_details.product_id', $item->id)
                        ->where('orders.status', 'Completed')
                        ->where('orders.created_at', '>=', $windowStart)
                        ->sum('order_details.quantity');
                }

                if ($totalSold === null || $totalSold == 0) {
                    $salesAverage = 0.0;
                } else {
                    $createdAt    = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : $now->copy()->subMonths(12);
                    $monthsOfLife = (int) ceil($createdAt->diffInMonths($now));
                    $actualMonths = max(1, min(12, $monthsOfLife));
                    $salesAverage = round($totalSold / $actualMonths, 2);
                }

                // Guardar en caliente en la base de datos de manera atómica
                \Illuminate\Support\Facades\DB::table('products')->where('id', $item->id)->update([
                    'sales_average'            => $salesAverage,
                    'sales_average_updated_at' => $now,
                ]);

                // Actualizar en memoria para el reporte actual
                $item->sales_average = $salesAverage;
                $item->sales_average_updated_at = $now->toDateTimeString();
                $item->is_stale_average = false;
            } catch (\Exception $e) {
                \Log::error("[IaAssistantReportService] Error en recálculo caliente para producto {$item->id}: " . $e->getMessage());
                $item->is_stale_average = true;
            }
        } else {
            $item->is_stale_average = false;
        }

        // Feature 2: producto nuevo sin historial de ventas
        // Condición: sales_average == 0, stock == 0 y fue creado hace menos de 90 días
        $sinPromedio = (float)($item->sales_average ?? 0) == 0;
        $sinStock    = (float)($item->lote_quantity ?? 0) == 0;
        $esNuevo     = isset($item->created_at) && $item->created_at
            && \Carbon\Carbon::parse($item->created_at)->gt(now()->subDays(90));

        $item->is_new_without_history = $sinPromedio && $esNuevo;

        // Si el producto es nuevo sin historial y no tiene stock, forzar solicitar = 1
        // para que aparezca como falla y el farmacéutico lo revise
        if ($item->is_new_without_history && $sinStock && (float)($item->solicitar ?? 0) <= 0) {
            $item->solicitar = 1;
        }
    }

    /**
     * Hidrata masivamente las cantidades en Auto Order (AO) para evitar N+1 queries.
     */
    private function hydrateAutoOrderBulk($products, array $filtros = []): void
    {
        $items = ($products instanceof LengthAwarePaginator) ? $products->getCollection() : collect($products);
        if ($items->isEmpty()) return;

        $productIds = $items->pluck('id')->toArray();

        // Una sola consulta SQL para obtener todos los totales de AO
        $query = \Illuminate\Support\Facades\DB::table('auto_order_details')
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
            ->select('product_suppliers.product_id', \Illuminate\Support\Facades\DB::raw('SUM(auto_order_details.quantity) as total'))
            ->whereIn('product_suppliers.product_id', $productIds)
            ->whereIn('auto_orders.status', [0, 1]) // PENDING, SENT (No Completados/Recibidos)
            ->where('auto_order_details.status', 0)
            ->whereNull('auto_orders.deleted_at')
            ->whereNull('auto_order_details.deleted_at');

        if (!empty($filtros['supplier_id'])) {
            $query->where('auto_orders.supplier_id', $filtros['supplier_id']);
        }

        $aoData = $query->groupBy('product_suppliers.product_id')
            ->get()
            ->keyBy('product_id');

        $items->each(function($p) use ($aoData) {
            $p->totalQuantityInAutoOrder = (float) ($aoData->get($p->id)->total ?? 0);
        });
    }

    private function orderByDiscountCollection($listaProductos, $conDescuento): Collection
    {
        $useDiscount = $conDescuento === "true";

        return $listaProductos->sortByDesc(function ($item) use ($useDiscount) {
            $producto = $item['product'] ?? null;
            $oferta = $item['productSupplier'] ?? null;
            
            if (!$producto || !$oferta) return -9999;

            $precioBase = (float) ($producto->unit_cost ?? 0);
            
            // Usar unit_cost_usd o unit_cost_usd_with_discount según preferencia
            $precioOferta = (float) ($useDiscount 
                ? ($oferta->unit_cost_usd_with_discount ?? $oferta->unit_cost_usd ?? 0)
                : ($oferta->unit_cost_usd ?? 0));
            
            if ($precioBase <= 0) return -9999;
            return (($precioBase - $precioOferta) / $precioBase) * 100;
        });
    }

    private function consolidateCollection($products, array $filtros)
    {
        $items = ($products instanceof LengthAwarePaginator) ? $products->getCollection() : collect($products);
        if ($items->isEmpty()) return $products;

        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        $conDescuentoStr = filter_var($filtros['con_descuento'] ?? true, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";

        // Obtener todos los group_ids para carga masiva
        $groupIds = $items->filter(fn($p) => $p->is_unified_group && !empty($p->group_id))
            ->pluck('group_id')
            ->unique()
            ->toArray();

        if (empty($groupIds)) {
            return $products;
        }

        // 1. Obtener todos los productos de estos grupos en una sola query
        $allGroupProducts = \App\Models\Product::whereIn('group_id', $groupIds)
            ->where('is_deleted', false)
            ->where('is_scarce', false)
            ->get()
            ->groupBy('group_id');

        // 2. Sumar stock en lote
        $stocks = \Illuminate\Support\Facades\DB::table('product_lots')
            ->join('products', 'products.id', '=', 'product_lots.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(product_lots.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->where(function($q) {
                $q->where('product_lots.expiration_date', '>=', now()->toDateString())
                  ->orWhereNull('product_lots.expiration_date');
            })
            ->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 3. Sumar ventas en lote
        $sales = \Illuminate\Support\Facades\DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(order_details.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$filtros['previousDate'], $filtros['dateToday']])
            ->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 4. Sumar Auto Order (AO) en lote
        $totalAOQuery = \Illuminate\Support\Facades\DB::table('auto_order_details')
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
            ->join('products', 'products.id', '=', 'product_suppliers.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(auto_order_details.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->whereIn('auto_orders.status', [0, 1])
            ->where('auto_order_details.status', 0)
            ->whereNull('auto_orders.deleted_at')
            ->whereNull('auto_order_details.deleted_at');

        if (!empty($filtros['supplier_id'])) {
            $totalAOQuery->where('auto_orders.supplier_id', $filtros['supplier_id']);
        }

        $autoOrders = $totalAOQuery->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 5. Concatenar laboratorios en lote
        $labs = \Illuminate\Support\Facades\DB::table('products')
            ->join('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->select('products.group_id', 'laboratories.name')
            ->whereIn('products.group_id', $groupIds)
            ->where('products.is_deleted', 0)
            ->where('products.is_scarce', 0)
            ->get()
            ->groupBy('group_id')
            ->map(function($items) {
                return implode(' / ', $items->pluck('name')->unique()->filter()->toArray());
            });

        // 6. Obtener ofertas en lote para todos los productos de todos los grupos
        $allProductsAcrossGroups = new \Illuminate\Database\Eloquent\Collection();
        foreach ($allGroupProducts as $gId => $productsInGroup) {
            foreach ($productsInGroup as $pr) {
                $allProductsAcrossGroups->push($pr);
            }
        }

        $allSuppliersData = $this->productSupplierRepository->getSupplierToReplenishTheProducts($allProductsAcrossGroups, $conDescuentoStr);
        $allSuppliersData = $this->productSupplierRepository->checkTolerance($allSuppliersData, $conDescuentoStr);

        $bestSupplierByGroupId = [];
        foreach ($allProductsAcrossGroups as $index => $pr) {
            $supplierData = $allSuppliersData[$index] ?? null;
            if ($supplierData && isset($supplierData['supplier'])) {
                $price = $supplierData['precio_final_supplier'] ?? 0;
                $gId = $pr->group_id;
                if ($price > 0) {
                    if (!isset($bestSupplierByGroupId[$gId]) || $price < $bestSupplierByGroupId[$gId]['precio_final_supplier']) {
                        $bestSupplierByGroupId[$gId] = $supplierData;
                    }
                }
            }
        }

        // Asignar los datos consolidados a cada producto unificado
        $items->each(function ($p) use ($filtros, $tipo, $allGroupProducts, $stocks, $sales, $autoOrders, $labs, $bestSupplierByGroupId) {
            if ($p->is_unified_group && $p->group_id) {
                $groupProducts = $allGroupProducts->get($p->group_id);
                if (!$groupProducts || $groupProducts->isEmpty()) return;

                $totalStock = (float)($stocks->get($p->group_id) ?? 0);
                $totalSales = (float)($sales->get($p->group_id) ?? 0);
                $totalAO = (float)($autoOrders->get($p->group_id) ?? 0);

                $sumSalesAverage = $groupProducts->sum('sales_average');
                $lapso = $filtros['lapso_de_tiempo'] ?? "1 month";
                $totalPromedio = match($lapso) {
                    "7 days"  => $sumSalesAverage / 4,
                    "15 days" => $sumSalesAverage / 2,
                    "1 month" => $sumSalesAverage,
                    "3 month" => $sumSalesAverage * 3,
                    "6 month" => $sumSalesAverage * 6,
                    "1 year"  => $sumSalesAverage * 12,
                    default    => $sumSalesAverage,
                };

                // Calcular solicitar y demanda ponderada
                if ($tipo === 'sales') {
                    $p->demanda_ponderada = $totalSales;
                    $resultado = $totalSales - $totalStock - $totalAO;
                } elseif ($tipo === 'combinado') {
                    $p->demanda_ponderada = ($totalSales + $totalPromedio) / 2;
                    $resultado = $p->demanda_ponderada - $totalStock - $totalAO;
                } else {
                    $p->demanda_ponderada = $totalPromedio;
                    $resultado = $totalPromedio - $totalStock - $totalAO;
                }

                $p->lote_quantity = $totalStock;
                $p->total_sold_completed = $totalSales;
                $p->promedio_calculado = $totalPromedio;
                $p->totalQuantityInAutoOrder = $totalAO;
                $p->solicitar = $resultado > 0 ? ceil($resultado) : floor($resultado);

                // Asignar laboratorios concatenados
                $concatenatedLabNames = $labs->get($p->group_id) ?: 'S/L';
                $labModel = new \App\Models\Laboratory();
                $labModel->name = $concatenatedLabNames;
                $p->setRelation('laboratory', $labModel);

                // Asignar mejor proveedor
                $bestOption = $bestSupplierByGroupId[$p->group_id] ?? null;
                if ($bestOption) {
                    $bestSupplier = $bestOption['supplier'];
                    if ($bestSupplier) {
                        $bestSupplier = clone $bestSupplier;
                    }
                    if ($bestSupplier && isset($bestOption['productSupplier'])) {
                        $bestSupplier->setAttribute('product_suppliers_id', $bestOption['productSupplier']->id ?? null);
                        $bestSupplier->setAttribute('unit_cost_usd_with_discount', $bestOption['productSupplier']->unit_cost_usd_with_discount ?? 0);
                    }
                    $p->setAttribute('cheapest_barcode', $bestOption['productSupplier']->barcode_match ?? $p->cheapest_barcode);
                    $p->setAttribute('cheapest_unit_cost', $bestOption['precio_final_supplier'] ?? 0);
                    $p->setAttribute('best_supplier', $bestSupplier);
                    $p->setAttribute('best_supplier_price', $bestOption['precio_final_supplier'] ?? 0);
                    $p->setAttribute('best_supplier_percentage', $bestOption['percentageIncrease'] ?? 0);
                } else {
                    $p->setAttribute('best_supplier', null);
                    $p->setAttribute('best_supplier_price', 0);
                    $p->setAttribute('best_supplier_percentage', 0);
                }
            }
        });

        return $products;
    }

    private function consolidateReplenishCollection(array $itemsReponer, array $filtros)
    {
        $conDescuento = filter_var($filtros['con_descuento'] ?? false, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";
        $tipo = $filtros['tipo_filtracion'] ?? 'average';

        $items = collect($itemsReponer);
        if ($items->isEmpty()) return $itemsReponer;

        $groupIds = $items->map(fn($item) => $item['product'] ?? null)
            ->filter(fn($p) => $p && $p->is_unified_group && !empty($p->group_id))
            ->pluck('group_id')
            ->unique()
            ->toArray();

        if (empty($groupIds)) {
            return $itemsReponer;
        }

        // 1. Obtener todos los productos de estos grupos en una sola query
        $allGroupProducts = \App\Models\Product::whereIn('group_id', $groupIds)
            ->where('is_deleted', false)
            ->where('is_scarce', false)
            ->get()
            ->groupBy('group_id');

        // 2. Sumar stock en lote
        $stocks = \Illuminate\Support\Facades\DB::table('product_lots')
            ->join('products', 'products.id', '=', 'product_lots.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(product_lots.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->where(function($q) {
                $q->where('product_lots.expiration_date', '>=', now()->toDateString())
                  ->orWhereNull('expiration_date');
            })
            ->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 3. Sumar ventas en lote
        $sales = \Illuminate\Support\Facades\DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(order_details.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [$filtros['previousDate'], $filtros['dateToday']])
            ->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 4. Sumar Auto Order (AO) en lote
        $totalAOQuery = \Illuminate\Support\Facades\DB::table('auto_order_details')
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
            ->join('products', 'products.id', '=', 'product_suppliers.product_id')
            ->select('products.group_id', \Illuminate\Support\Facades\DB::raw('SUM(auto_order_details.quantity) as total_quantity'))
            ->whereIn('products.group_id', $groupIds)
            ->whereIn('auto_orders.status', [0, 1])
            ->where('auto_order_details.status', 0)
            ->whereNull('auto_orders.deleted_at')
            ->whereNull('auto_order_details.deleted_at');

        if (!empty($filtros['supplier_id'])) {
            $totalAOQuery->where('auto_orders.supplier_id', $filtros['supplier_id']);
        }

        $autoOrders = $totalAOQuery->groupBy('products.group_id')
            ->pluck('total_quantity', 'group_id');

        // 5. Concatenar laboratorios en lote
        $labs = \Illuminate\Support\Facades\DB::table('products')
            ->join('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->select('products.group_id', 'laboratories.name')
            ->whereIn('products.group_id', $groupIds)
            ->where('products.is_deleted', 0)
            ->where('products.is_scarce', 0)
            ->get()
            ->groupBy('group_id')
            ->map(function($items) {
                return implode(' / ', $items->pluck('name')->unique()->filter()->toArray());
            });

        // Calcular cantidades sugeridas consolidadas por grupo
        $solicitarByGroupId = [];
        foreach ($groupIds as $gId) {
            $groupProducts = $allGroupProducts->get($gId);
            if (!$groupProducts || $groupProducts->isEmpty()) continue;

            $totalStock = (float)($stocks->get($gId) ?? 0);
            $totalSales = (float)($sales->get($gId) ?? 0);
            $totalAO = (float)($autoOrders->get($gId) ?? 0);

            $sumSalesAverage = $groupProducts->sum('sales_average');
            $lapso = $filtros['lapso_de_tiempo'] ?? "1 month";
            $totalPromedio = match($lapso) {
                "7 days"  => $sumSalesAverage / 4,
                "15 days" => $sumSalesAverage / 2,
                "1 month" => $sumSalesAverage,
                "3 month" => $sumSalesAverage * 3,
                "6 month" => $sumSalesAverage * 6,
                "1 year"  => $sumSalesAverage * 12,
                default    => $sumSalesAverage,
            };

            if ($tipo === 'sales') {
                $resultado = $totalSales - $totalStock - $totalAO;
            } elseif ($tipo === 'combinado') {
                $resultado = (($totalSales + $totalPromedio) / 2) - $totalStock - $totalAO;
            } else {
                $resultado = $totalPromedio - $totalStock - $totalAO;
            }

            $solicitarByGroupId[$gId] = $resultado > 0 ? ceil($resultado) : floor($resultado);
        }

        // 6. Obtener ofertas en lote para todos los productos de todos los grupos
        $allProductsAcrossGroups = new \Illuminate\Database\Eloquent\Collection();
        foreach ($allGroupProducts as $gId => $productsInGroup) {
            foreach ($productsInGroup as $pr) {
                $solicitarConsolidado = $solicitarByGroupId[$gId] ?? 0;
                $pr->solicitar = -$solicitarConsolidado;
                $allProductsAcrossGroups->push($pr);
            }
        }

        $allSuppliersData = $this->productSupplierRepository->getSupplierToReplenishTheProducts($allProductsAcrossGroups, $conDescuento);
        $allSuppliersData = $this->productSupplierRepository->checkTolerance($allSuppliersData, $conDescuento);

        $bestReponerByGroupId = [];
        foreach ($allProductsAcrossGroups as $index => $pr) {
            $supplierData = $allSuppliersData[$index] ?? null;
            if ($supplierData && isset($supplierData['supplier'])) {
                $price = $supplierData['precio_final_supplier'] ?? 0;
                $gId = $pr->group_id;
                if ($price > 0) {
                    if (!isset($bestReponerByGroupId[$gId]) || $price < $bestReponerByGroupId[$gId]['precio_final_supplier']) {
                        $bestReponerByGroupId[$gId] = $supplierData;
                    }
                }
            }
        }

        $processedItems = $items->map(function ($item) use ($filtros, $tipo, $conDescuento, $allGroupProducts, $stocks, $sales, $autoOrders, $labs, $solicitarByGroupId, $bestReponerByGroupId) {
            $p = $item['product'] ?? null;
            if ($p && $p->is_unified_group && $p->group_id) {
                $groupProducts = $allGroupProducts->get($p->group_id);
                if (!$groupProducts || $groupProducts->isEmpty()) return $item;

                $totalStock = (float)($stocks->get($p->group_id) ?? 0);
                $totalSales = (float)($sales->get($p->group_id) ?? 0);
                $totalAO = (float)($autoOrders->get($p->group_id) ?? 0);
                $solicitarConsolidado = $solicitarByGroupId[$p->group_id] ?? 0;

                $sumSalesAverage = $groupProducts->sum('sales_average');
                $lapso = $filtros['lapso_de_tiempo'] ?? "1 month";
                $totalPromedio = match($lapso) {
                    "7 days"  => $sumSalesAverage / 4,
                    "15 days" => $sumSalesAverage / 2,
                    "1 month" => $sumSalesAverage,
                    "3 month" => $sumSalesAverage * 3,
                    "6 month" => $sumSalesAverage * 6,
                    "1 year"  => $sumSalesAverage * 12,
                    default    => $sumSalesAverage,
                };

                if ($tipo === 'sales') {
                    $p->demanda_ponderada = $totalSales;
                } elseif ($tipo === 'combinado') {
                    $p->demanda_ponderada = ($totalSales + $totalPromedio) / 2;
                } else {
                    $p->demanda_ponderada = $totalPromedio;
                }

                $p->lote_quantity = $totalStock;
                $p->total_sold_completed = $totalSales;
                $p->promedio_calculado = $totalPromedio;
                $p->totalQuantityInAutoOrder = $totalAO;
                $p->solicitar = $solicitarConsolidado;

                // Laboratorios
                $concatenatedLabNames = $labs->get($p->group_id) ?: 'S/L';
                $labModel = new \App\Models\Laboratory();
                $labModel->name = $concatenatedLabNames;
                $p->setRelation('laboratory', $labModel);

                // Buscar la mejor oferta
                $bestReponerItem = $bestReponerByGroupId[$p->group_id] ?? null;

                if ($bestReponerItem) {
                    $bestReponerItem['product'] = $p;
                    $bestReponerItem['solicitar'] = -$solicitarConsolidado;

                    $bestSupplier = $bestReponerItem['supplier'];
                    if ($bestSupplier) {
                        $bestSupplier = clone $bestSupplier;
                    }
                    if ($bestSupplier && isset($bestReponerItem['productSupplier'])) {
                        $bestSupplier->setAttribute('product_suppliers_id', $bestReponerItem['productSupplier']->id ?? null);
                        $bestSupplier->setAttribute('unit_cost_usd_with_discount', $bestReponerItem['productSupplier']->unit_cost_usd_with_discount ?? 0);
                    }
                    $p->setAttribute('cheapest_barcode', $bestReponerItem['productSupplier']->barcode_match ?? $p->cheapest_barcode);
                    $p->setAttribute('cheapest_unit_cost', $bestReponerItem['precio_final_supplier'] ?? 0);
                    $p->setAttribute('best_supplier', $bestSupplier);
                    $p->setAttribute('best_supplier_price', $bestReponerItem['precio_final_supplier'] ?? 0);
                    $p->setAttribute('best_supplier_percentage', $bestReponerItem['percentageIncrease'] ?? 0);

                    return $bestReponerItem;
                } else {
                    $item['product'] = $p;
                    $item['solicitar'] = -$solicitarConsolidado;
                    $p->setAttribute('best_supplier', null);
                    $p->setAttribute('best_supplier_price', 0);
                    $p->setAttribute('best_supplier_percentage', 0);
                    return $item;
                }
            }

            return $item;
        });

        return $processedItems->toArray();
    }
}
