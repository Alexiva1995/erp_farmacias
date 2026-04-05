<?php

namespace App\Services;

use App\Contracts\Product;
use App\Contracts\ProductSupplier;
use App\Exports\AssistantReportProductExport;
use App\Exports\StockProductExport;
use App\Models\Product as ModelsProduct;
use App\Repository\AutoOrderDetailsRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductSupplierRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductServices implements Product
{
    public function __construct(
        protected ProductRepository $productRepository,
        protected ProductSupplierRepository $productSupplierRepository,
        protected AutoOrderDetailsRepository $autoOrderDetailsRepository,
    ) {
    }

    public function consultProduct(): Collection
    {
        return $this->productRepository->consultarTodosLosProductOrdenaPor();
    }

    public function filtrarStock(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforStocktWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarStockWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforStocktWithoutPaginate($filtros);
    }

    public function exportExcel(array $filtros): StockProductExport
    {
        $query = $this->productRepository->builerFiltrarProductforStock($filtros);
        return new StockProductExport($query);
    }

    public function filtrarIaOrderAssistantTypeAverage(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeAverageWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIaOrderAssistantTypeAverageWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeAverageWithoutPaginate($filtros);
    }
    // 
    public function filtrarIaOrderAssistantTypeSales(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeSalesWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIaOrderAssistantTypeSalesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarProductforIaOrderAssistantTypeSalesWithoutPaginate($filtros);
    }

    public function filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithPaginate(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesToArray(array $filtros): array
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesToArray($filtros);
    }

    public function exportAssistantReportExcel(array $filtros): AssistantReportProductExport
    {
        $respuestaConsulta = null;
        if ($filtros["tipo_filtracion"] == "average") {
            $respuestaConsulta = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $respuestaConsulta = $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros);
        } else {
            $respuestaConsulta = $this->productRepository->filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate($filtros);
        }
        // if ($filtros["tipo_filtracion"] != "average" && $filtros["tipo_filtracion"] != "sales") {
        //     for ($index = 0; $index < count($respuestaConsulta); $index++) {
        //         $itemsBusqueda = null;
        //         # code...
        //         $filtros["orderBy"] = "ASC";
        //         $filtros["sortBy"] = "id";
        //         $filtros["id"] = $respuestaConsulta[$index]->id;
        //         $itemsBusqueda = $this->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtros)->first();
        //         if ($itemsBusqueda) {
        //             $itemsBusqueda = $this->calcularAOProduct($itemsBusqueda);
        //             $itemsBusqueda->solicitar = $itemsBusqueda->solicitar + $itemsBusqueda->totalQuantityInAutoOrder;
        //             $respuestaConsulta[$index]->solicitar = ceil(($respuestaConsulta[$index]->solicitar + $itemsBusqueda->solicitar) / 2);
        //         }
        //     }
        // }
        if ($filtros["tipo_filtracion"] == "combinado") {
            for ($index = 0; $index < count($respuestaConsulta); $index++) {
                // Obtener datos de ventas para este producto específico
                $filtrosVentas = $filtros;
                $filtrosVentas["id"] = $respuestaConsulta[$index]->id;
                $itemVentas = $this->filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate($filtrosVentas)->first();
                if ($itemVentas) {
                    // Calcular AO para el item de ventas también
                    $itemVentas = $this->calcularAOProduct($itemVentas);

                    // Obtener valores correctos para el cálculo
                    $ventasTotales = $itemVentas->total_sold_completed ?? 0; // Usar total_sold_completed
                    $promedio = $respuestaConsulta[$index]->promedio_calculado ?? 0; // Usar promedio_calculado
                    $stockActual = $respuestaConsulta[$index]->lote_quantity ?? 0; // Stock actual
                    $autoOrder = $respuestaConsulta[$index]->totalQuantityInAutoOrder ?? 0; // Cantidad en auto order

                    // Fórmula: (ventas + promedio) / 2 - stock - AO
                    $resultado = (($ventasTotales + $promedio) / 2) - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis (como funciona en promedio)
                    // Si el resultado es negativo (falta producto), se muestra positivo
                    // Si el resultado es positivo (exceso de producto), se muestra negativo
                    $respuestaConsulta[$index]->solicitar = -$resultado;
                } else {
                    // Si no hay datos de ventas, usar solo el promedio menos stock y AO
                    $promedio = $respuestaConsulta[$index]->promedio_calculado ?? 0;
                    $stockActual = $respuestaConsulta[$index]->lote_quantity ?? 0;
                    $autoOrder = $respuestaConsulta[$index]->totalQuantityInAutoOrder ?? 0;

                    $resultado = $promedio - $stockActual - $autoOrder;

                    // Invertir el signo para el análisis
                    $respuestaConsulta[$index]->solicitar = -$resultado;
                }

                // Redondear el resultado hacia arriba para combinado (mantener el signo)
                $respuestaConsulta[$index]->solicitar = $respuestaConsulta[$index]->solicitar > 0 ? ceil($respuestaConsulta[$index]->solicitar) : floor($respuestaConsulta[$index]->solicitar);
            }
        }
        return new AssistantReportProductExport($respuestaConsulta);
    }

    public function calcularAOProduct(ModelsProduct $producto): ModelsProduct
    {

        $total = 0;
        $productsSuppliers = $this->productSupplierRepository->consultarTodosLosProveedorProIdProducto($producto->id);
        for ($j = 0; $j < count($productsSuppliers); $j++) {
            # code...
            $suma = $this->autoOrderDetailsRepository->consultDetailByProductSupplierId($productsSuppliers[$j]->id);
            if ($suma) {
                $total += $suma;
            }
        }
        $producto->totalQuantityInAutoOrder = $total;


        return $producto;
    }

    public function calcularAOProducts(Collection $productos): Collection
    {
        $productosConPedidosAutomaticos = $productos->map(function ($producto) {
            return $this->calcularAOProduct($producto);
        });

        return $productosConPedidosAutomaticos;
    }



    public function actualizarElSolicitadoConElAO(Collection $productos): Collection
    {
        $productosActualizados = $productos->map(function ($producto) {
            $producto->solicitar += $producto->totalQuantityInAutoOrder;
            return $producto;
        });

        return $productosActualizados;
    }
    function consultProductById(int $id): ?ModelsProduct
    {
        return $this->consultProductById($id);
    }
    public function getUniqueIdsForIaReport(array $filtros, bool $porGrupo = false): array
    {
        return $this->productRepository->getUniqueIdsForIaReport($filtros, $porGrupo);
    }
}
