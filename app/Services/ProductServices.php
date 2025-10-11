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
    ) {}

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
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeAverageWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function filtrarIndividualProductForAssistantReportTypeAveragesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeAverageWithoutPaginate($filtros);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithoutPaginate(array $filtros): Collection
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeSelesWithoutPaginate($filtros);
    }

    public function filtrarIndividualProductForAssistantReportTypeSalesWithPaginate(array $filtros): LengthAwarePaginator
    {
        return $this->productRepository->filtrarIndividualProductForAssistantReportTypeSalesWithPaginate($filtros, $filtros["itemsPerPage"]);
    }

    public function exportAssistantReportExcel(array $filtros): AssistantReportProductExport
    {
        $query = null;
        if ($filtros["tipo_filtracion"] == "average") {
            $query = $this->productRepository->builerFiltrarIndividualProductForAssistantReportTypeAverage($filtros);
        }
        if ($filtros["tipo_filtracion"] == "sales") {
            $query = $this->productRepository->builerFiltrarIndividualProductForAssistantReportTypeSales($filtros);
        }
        return new AssistantReportProductExport($query);
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

    public function removerProductosConPedidosAutomaticos(Collection $productos): Collection
    {
        $productosFiltrados = collect();
        $idsAhDescartar = [];
        for ($index2 = 0; $index2 < $productos->count(); $index2++) {
            $producto = $productos[$index2];
            if (($producto->solicitar + $producto->totalQuantityInAutoOrder) == 0 && $producto->totalQuantityInAutoOrder > 0) {
                $productosFiltrados->add($producto);
                $idsAhDescartar[] = $producto->id;
            }
        }
        // dump($idsAhDescartar);
        // dump('------------------');

        $productos = $productos->filter(function ($prod) use ($idsAhDescartar) {
            return !in_array($prod->id, $idsAhDescartar);
        })->values();
        // dump("despus de filtrar");
        // dd($productos);

        return $productos;
    }

    public function actualizarElSolicitadoConElAO(Collection $productos): Collection
    {
        $productosActualizados = $productos->map(function ($producto) {
            $producto->solicitar += $producto->totalQuantityInAutoOrder;
            return $producto;
        });

        return $productosActualizados;
    }
}
