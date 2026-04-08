<?php

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
        if ($perPage <= 0) $perPage = 25;

        $esVistaGrupal = filter_var($filtros['tipo_vista'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // 1. Obtener todos los IDs (de productos o grupos) que coinciden con los filtros (Cacheado)
        $allIds = $this->getFilteredIds($filtros, $esVistaGrupal);
        $total = count($allIds);

        // 2. Paginar los IDs en memoria
        $offset = ($page - 1) * $perPage;
        $currentPageIds = array_slice($allIds, $offset, $perPage);

        if (empty($currentPageIds)) {
            return new LengthAwarePaginator([], $total, $perPage, $page);
        }

        // 3. Hidratar los modelos
        $filtrosHidratacion = $filtros;
        if ($esVistaGrupal) {
            // Si es vista grupal, los IDs son de GRUPOS
            $filtrosHidratacion['groups'] = $currentPageIds;
            // Quitamos q para que traiga todos los productos del grupo una vez filtrados los grupos
            // Nota: Si se requiere que los productos dentro del grupo también respeten q, se puede mantener q
        } else {
            // Si es vista individual, los IDs son de PRODUCTOS
            $filtrosHidratacion['ids_in'] = $currentPageIds;
        }
        
        // Ejecutamos la consulta base según el tipo
        if ($tipo === 'sales') {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosHidratacion);
        } else {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosHidratacion);
        }

        // 4. Procesar cálculos adicionales (AO, Combinado, etc.)
        if ($tipo === 'combinado') {
            $procesado = $this->processCombinedReport($resultado, $filtros);
        } else {
            $procesado = $this->processRegularReport($resultado, $tipo);
        }

        // 5. Hidratar tendencia de ventas (Últimos 6 meses)
        $this->hydrateSalesTrend($procesado);

        // 5.1 Hidratar proveedores si se solicita
        if (filter_var($filtros['with_suppliers'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $conDescuento = filter_var($filtros['con_descuento'] ?? false, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";
            
            // Reutilizamos la lógica masiva existente
            $itemsWithSuppliers = $this->productSupplierRepository->getSupplierToReplenishTheProducts($procesado, $conDescuento);
            $itemsWithSuppliers = $this->productSupplierRepository->checkTolerance($itemsWithSuppliers, $conDescuento);
            
            // Mapear de vuelta a los productos (O(N) ya que las listas están sincronizadas por orden)
            foreach ($procesado as $index => $producto) {
                $supplierData = $itemsWithSuppliers[$index] ?? null;
                if ($supplierData) {
                    $producto->best_supplier = $supplierData['supplier'] ?? null;
                    $producto->best_supplier_price = $supplierData['precio_final_supplier'] ?? 0;
                    $producto->best_supplier_percentage = $supplierData['percentageIncrease'] ?? 0;
                }
            }
        }

        // 6. Devolver paginador manual
        return new LengthAwarePaginator($procesado, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Devuelve grupos paginados con sus productos anidados (para vista de acordeón)
     */
    public function getGroupedReportWithPaginate(array $filtros): array
    {
        $filtros = $this->prepareDateFilters($filtros);
        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        $page = (int) ($filtros['page'] ?? 1);
        $perPage = (int) ($filtros['itemsPerPage'] ?? 25);
        if ($perPage <= 0) $perPage = 25;

        // 1. Obtener todos los group_ids ordenados alfabéticamente
        $allGroupIds = $this->getFilteredIds($filtros, true);
        $totalGroups = count($allGroupIds);

        // 2. Paginar los group_ids en memoria (ESTO sí limita a 25 grupos)
        $offset = ($page - 1) * $perPage;
        $currentGroupIds = array_slice($allGroupIds, $offset, $perPage);

        if (empty($currentGroupIds)) {
            return [
                'grupos' => [],
                'total_grupos' => $totalGroups,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($totalGroups / $perPage),
            ];
        }

        // 3. Pre-cargar los nombres de grupos de esta página en una sola query
        $grupoNombres = \App\Models\GroupsProduct::whereIn('id', $currentGroupIds)
            ->pluck('name', 'id');

        // 4. Para cada grupo de esta página, traer sus productos
        $filtrosBase = $filtros;
        unset($filtrosBase['page'], $filtrosBase['itemsPerPage']);

        $grupos = [];
        foreach ($currentGroupIds as $groupId) {
            $filtrosGrupo = $filtrosBase;
            $filtrosGrupo['groups'] = [$groupId];
            unset($filtrosGrupo['tipo_vista']);

            // Obtener productos del grupo
            if ($tipo === 'sales') {
                $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosGrupo);
            } else {
                $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosGrupo);
            }

            if ($tipo === 'combinado') {
                $procesado = $this->processCombinedReport($resultado, $filtrosGrupo);
            } else {
                $procesado = $this->processRegularReport($resultado, $tipo);
            }

            $this->hydrateSalesTrend($procesado);

            // Hidratar proveedores si se solicita
            if (filter_var($filtros['with_suppliers'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $conDescuento = filter_var($filtros['con_descuento'] ?? false, FILTER_VALIDATE_BOOLEAN) ? "true" : "false";
                $itemsWithSuppliers = $this->productSupplierRepository->getSupplierToReplenishTheProducts($procesado, $conDescuento);
                $itemsWithSuppliers = $this->productSupplierRepository->checkTolerance($itemsWithSuppliers, $conDescuento);
                
                foreach ($procesado as $index => $producto) {
                    $supplierData = $itemsWithSuppliers[$index] ?? null;
                    if ($supplierData) {
                        $producto->best_supplier = $supplierData['supplier'] ?? null;
                        $producto->best_supplier_price = $supplierData['precio_final_supplier'] ?? 0;
                        $producto->best_supplier_percentage = $supplierData['percentageIncrease'] ?? 0;
                    }
                }
            }

            // Serializar a array asociativo profundo (funciona con Eloquent models y stdClass)
            $productosArray = json_decode(json_encode($procesado), true);

            $grupos[] = [
                'group_id'   => $groupId,
                'group_name' => $grupoNombres[$groupId] ?? '',
                'productos'  => array_values($productosArray),
                'total_solicitar' => collect($productosArray)->sum(function($p) {
                    return (float) ($p['solicitar'] ?? 0);
                })
            ];
        }

        return [
            'grupos'       => $grupos,
            'total_grupos' => $totalGroups,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($totalGroups / $perPage),
        ];
    }

    /**
     * Obtiene y cachea los IDs (de productos o grupos) que coinciden con los filtros
     */
    public function getFilteredIds(array $filtros, bool $porGrupo = false): array
    {
        $cacheKey = 'ia_report_ids_v4_' . ($porGrupo ? 'grp_' : 'prd_') . md5(json_encode([
            'lapso' => $filtros['lapso_de_tiempo'] ?? '',
            'lab' => $filtros['laboratoryId'] ?? [],
            'groups' => $filtros['groups'] ?? [],
            'is_col' => $filtros['isColombian'] ?? null,
            'q' => $filtros['q'] ?? '',
            'stock' => $filtros['stock'] ?? 'fallas',
            'tipo' => $filtros['tipo_filtracion'] ?? 'average',
            'ws' => $filtros['without_supplier'] ?? false,
            'sb' => $filtros['sortBy'] ?? '',
            'ob' => $filtros['orderBy'] ?? '',
        ]));

        return Cache::remember($cacheKey, 600, function () use ($filtros, $porGrupo) {
            $filtrosLigero = $filtros;
            unset($filtrosLigero['page'], $filtrosLigero['itemsPerPage']);
            
            $tipo = $filtros['tipo_filtracion'] ?? 'average';
            
            return $this->productRepository->getUniqueIdsForIaReport($filtrosLigero, $porGrupo);
        });
    }

    /**
     * Devuelve el conteo total de productos que coinciden con los filtros del asistente.
     * Usa la misma fuente que el asistente (getUniqueIdsForIaReport) para garantizar coincidencia.
     */
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
            $procesado = $this->processRegularReport($resultado, $tipo);
        } else {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosHidratacion);
            if ($tipo === 'combinado') {
                $procesado = $this->processCombinedReport($resultado, $filtros);
            } else {
                $procesado = $this->processRegularReport($resultado, $tipo);
            }
        }

        $this->hydrateSalesTrend($procesado);
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
            $key = $date->format('Y-n'); // Ej: 2024-4
            $baseTrend[$key] = [
                'label' => $this->getMonthName($date->month),
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
            $key = $row->year . '-' . $row->month;
            $itemSalesMap[$row->product_id][$key] = (float)$row->total;
        }

        // 4. Asignar la tendencia normalizada a cada producto
        $items->each(function ($product) use ($baseTrend, $itemSalesMap) {
            $productTrend = $baseTrend; // Copia del esqueleto de 6 meses
            $sales = $itemSalesMap[$product->id] ?? [];

            foreach ($sales as $key => $total) {
                if (isset($productTrend[$key])) {
                    $productTrend[$key]['value'] = $total;
                }
            }

            $product->sales_trend = array_column($productTrend, 'value');
            $product->sales_trend_labels = array_column($productTrend, 'label');
        });
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
    private function processRegularReport($resultados, string $tipo)
    {
        // $resultados puede ser un Paginator o una Collection
        $isPaginator = $resultados instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
        $items = $isPaginator ? $resultados->getCollection() : collect($resultados);

        // Hidratación masiva de AO para evitar N+1
        $this->hydrateAutoOrderBulk($items);

        $items->transform(function ($item) use ($tipo) {
            // Ya no llamamos a calcularAOProduct individualmente
            
            // La demanda ponderada en reportes simples es el valor base (ventas o promedio)
            $item->demanda_ponderada = ($tipo === 'sales') 
                ? ($item->total_sold_completed ?? 0) 
                : ($item->promedio_calculado ?? 0);

            // La fórmula regular que venía en el controlador: solicitar = solicitar + AO
            $item->solicitar = (float)($item->solicitar ?? 0) + (float)($item->totalQuantityInAutoOrder ?? 0);
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
        $this->hydrateAutoOrderBulk($items);

        $items->transform(function ($item) use ($ventasMap) {
            // Ya no llamamos a calcularAOProduct individualmente
            
            // Buscar si tiene datos de venta en el mapa
            $itemVentas = $ventasMap->get($item->id);

            $promedio = $item->promedio_calculado ?? 0;
            $stockActual = $item->lote_quantity ?? 0;
            $autoOrder = $item->totalQuantityInAutoOrder ?? 0;

            if ($itemVentas) {
                // Hay historial de ventas para el producto
                $itemVentas = $this->productRepository->calcularAOProduct($itemVentas);
                $ventasTotales = $itemVentas->total_sold_completed ?? 0;
                
                // Demanda ponderada combinada
                $item->demanda_ponderada = ($ventasTotales + $promedio) / 2;

                // Fórmula combinada: ((ventas + promedio) / 2) - stock - AO
                $resultado = $item->demanda_ponderada - $stockActual - $autoOrder;
            } else {
                // Fórmula base si no hay ventas recientes: promedio - stock - AO
                $item->demanda_ponderada = $promedio;
                $resultado = $promedio - $stockActual - $autoOrder;
            }

            // Invertir el signo para el análisis visual (faltante => positivo)
            $item->solicitar = $resultado;
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

        // 4. Clasificar y ordenar
        $coleccion = collect($itemsReponer);
        
        // Antes de clasificar, invertimos de nuevo para el frontend: faltante => positivo
        $coleccion->each(function($item) {
            $item['product']->solicitar = -$item['product']->solicitar;
            // Redondear lógicamente: mantener el piso/techo según el signo original (que ahora es positivo)
            $item['product']->solicitar = $item['product']->solicitar > 0 ? ceil($item['product']->solicitar) : floor($item['product']->solicitar);
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
     * Hidrata masivamente las cantidades en Auto Order (AO) para evitar N+1 queries.
     */
    private function hydrateAutoOrderBulk($products): void
    {
        $items = ($products instanceof LengthAwarePaginator) ? $products->getCollection() : collect($products);
        if ($items->isEmpty()) return;

        $productIds = $items->pluck('id')->toArray();

        // Una sola consulta SQL para obtener todos los totales de AO
        $aoData = \Illuminate\Support\Facades\DB::table('auto_order_details')
            ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
            ->select('product_suppliers.product_id', \Illuminate\Support\Facades\DB::raw('SUM(auto_order_details.quantity) as total'))
            ->whereIn('product_suppliers.product_id', $productIds)
            ->groupBy('product_suppliers.product_id')
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

}

