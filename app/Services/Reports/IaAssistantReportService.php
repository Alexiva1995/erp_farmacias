<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class IaAssistantReportService
{
    public function __construct(protected \App\Contracts\Product $productRepository)
    {
    }

    /**
     * Orquesta el reporte filtrado con paginación USANDO CACHÉ DE IDs (Estilo IA Assistant)
     */
    public function getFilteredReportWithPaginate(array $filtros)
    {
        $filtros = $this->prepareDateFilters($filtros);
        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        $page = $filtros['page'] ?? 1;
        $perPage = $filtros['itemsPerPage'] ?? 10;

        // 1. Obtener todos los IDs que coinciden con los filtros (Cacheado)
        $allIds = $this->getFilteredProductIds($filtros);
        $total = count($allIds);

        // 2. Paginar los IDs en memoria
        $offset = ($page - 1) * $perPage;
        $currentPageIds = array_slice($allIds, $offset, $perPage);

        if (empty($currentPageIds)) {
            return new LengthAwarePaginator([], $total, $perPage, $page);
        }

        // 3. Hidratar los modelos solo para los IDs de la página actual
        $filtrosHidratacion = $filtros;
        $filtrosHidratacion['ids_in'] = $currentPageIds;
        
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

        // 5. Devolver paginador manual
        return new LengthAwarePaginator($procesado, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Obtiene y cachea los IDs de productos que coinciden con los filtros
     */
    private function getFilteredProductIds(array $filtros): array
    {
        $cacheKey = 'ia_report_ids_' . md5(json_encode([
            'lapso' => $filtros['lapso_de_tiempo'] ?? '',
            'lab' => $filtros['laboratoryId'] ?? [],
            'groups' => $filtros['groups'] ?? [],
            'is_col' => $filtros['is_colombia'] ?? null,
            'q' => $filtros['q'] ?? '',
        ]));

        return Cache::remember($cacheKey, 600, function () use ($filtros) {
            // Usamos una consulta ligera que solo traiga IDs
            // Para esto, usamos el builder de averages pero solo pidiendo ID
            $filtrosLigero = $filtros;
            unset($filtrosLigero['page'], $filtrosLigero['itemsPerPage']);
            
            // Obtenemos todos sin paginar para tener el set completo de IDs
            $tipo = $filtros['tipo_filtracion'] ?? 'average';
            
            if ($tipo === 'sales') {
                $collection = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosLigero);
            } else {
                $collection = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtrosLigero);
            }

            return $collection->pluck('id')->toArray();
        });
    }

    /**
     * Orquesta el reporte filtrado SIN paginación (Exportación)
     */
    public function getFilteredReportWithoutPaginate(array $filtros)
    {
        $filtros = $this->prepareDateFilters($filtros);
        
        $tipo = $filtros['tipo_filtracion'] ?? 'average';
        
        if ($tipo === 'sales') {
            $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros);
            return $this->processRegularReport($resultado, $tipo);
        }

        $resultado = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);

        if ($tipo === 'combinado') {
            return $this->processCombinedReport($resultado, $filtros);
        }

        return $this->processRegularReport($resultado, $tipo);
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

        $items->transform(function ($item) {
            $item = $this->productRepository->calcularAOProduct($item);
            // La fórmula regular que venía en el controlador: solicitar = solicitar + AO
            $item->solicitar = $item->solicitar + ($item->totalQuantityInAutoOrder ?? 0);
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

        $items->transform(function ($item) use ($ventasMap) {
            $item = $this->productRepository->calcularAOProduct($item);
            
            // Buscar si tiene datos de venta en el mapa
            $itemVentas = $ventasMap->get($item->id);

            $promedio = $item->promedio_calculado ?? 0;
            $stockActual = $item->lote_quantity ?? 0;
            $autoOrder = $item->totalQuantityInAutoOrder ?? 0;

            if ($itemVentas) {
                // Hay historial de ventas para el producto
                $itemVentas = $this->productRepository->calcularAOProduct($itemVentas);
                $ventasTotales = $itemVentas->total_sold_completed ?? 0;
                
                // Fórmula combinada: ((ventas + promedio) / 2) - stock - AO
                $resultado = (($ventasTotales + $promedio) / 2) - $stockActual - $autoOrder;
            } else {
                // Fórmula base si no hay ventas recientes: promedio - stock - AO
                $resultado = $promedio - $stockActual - $autoOrder;
            }

            // Invertir el signo para el análisis visual (faltante => positivo)
            $item->solicitar = -$resultado;
            
            // Redondear lógicamente: mantener el piso/techo según el signo original
            $item->solicitar = $item->solicitar > 0 ? ceil($item->solicitar) : floor($item->solicitar);

            return $item;
        });

        if ($isPaginator) {
            $resultados->setCollection($items);
            return $resultados;
        }

        return $items;
    }
}

