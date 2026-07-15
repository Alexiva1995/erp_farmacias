<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProductSupplier;
use App\Models\Product;
use App\Models\ProductSupplier as ModelsProductSupplier;
use App\Models\Supplier;
use App\Repositories\ProductLotsRepository;
use App\Repositories\ProductSupplierRepository;
use DateTime;
use Illuminate\Database\Eloquent\Collection;

class ProductSupplierServices implements ProductSupplier
{

    public function __construct(
        protected ProductSupplierRepository $productSupplierRepository,
        protected ProductLotsRepository $productLotsRepository
    ) {
    }


    public function consultSupplierByProductWithBetterPrice(Product $product, string $conDescuento): Collection
    {
        return $this->productSupplierRepository->consultSupplierByProductWithBetterPrice($product->id, $conDescuento);
    }



    public function calculatePercentageDifferenceIncrease(float $price, float $supplierPrice): float
    {
        if ($price == 0) {
            return 0;
        }

        $resta = $supplierPrice - $price;
        $resultado = ($resta / $price) * 100;
        return $resultado;
    }

    /** 
     * esta funcion retorna true si hay si hay un incremento, false si no y null
     * */
    public function checkIfTheProductHasIncreasedInPrice(float $percentageIncrease, float $maximumPercentageMaximo): bool|null
    {
        $incremento = null;
        if ($percentageIncrease > $maximumPercentageMaximo) {
            $incremento = true;
        }

        if ($percentageIncrease < -$maximumPercentageMaximo) {
            $incremento = false;
        }
        return $incremento;
    }

    public function checkPurchaseOpportunity(float $percentageIncrease, float $maximumPercentageMaximo): bool
    {
        return $percentageIncrease < $maximumPercentageMaximo ? true : false;
    }

    public function getSupplierToReplenishTheProducts(Collection $products, string $conDescuento): array
    {
        // Delegamos al repositorio que ya tiene la lógica masiva optimizada y sin restricciones de 'solicitar'
        return $this->productSupplierRepository->getSupplierToReplenishTheProducts($products, $conDescuento);
    }

    public function getSupplierToReplenishTheProductsWithoutValidateSolicitar(Collection $products, string $conDescuento): array
    {
        $respuesta = [];
        for ($index = 0; $index < count($products); $index++) {

            $ofertas = $this->consultSupplierByProductWithBetterPrice($products[$index], $conDescuento);
            $products[$index]->ofertas = $ofertas;
            $products[$index]->solicitar = floor((float) $products[$index]->solicitar);

            for ($index2 = 0; $index2 < count($ofertas); $index2++) {

                $oferta = $ofertas[$index2];

                // el el proveedor tienene stock disponible repone el producto
                if ($oferta->quantity != null && $oferta->quantity > 0) {
                    $suma = null;
                    // formula dependiendo si el solicitar es positivo o negativo
                    if ((int) $products[$index]->solicitar >= 0) {
                        $suma = $ofertas[$index2]->quantity - (int) $products[$index]->solicitar;
                    } else {
                        $suma = (int) $products[$index]->solicitar + $ofertas[$index2]->quantity;
                    }

                    if ($suma < 0) {
                        $products[$index]->solicitar = $suma;
                        // CAMBIO: Para oportunidades de mercado, siempre usar 0 como sugerencia
                        $reponer = 0; // En lugar de $ofertas[$index2]->quantity;
                        $respuesta[] = $this->supplierProductFormat($products[$index], $ofertas[$index2]->supplier, $oferta, $reponer);
                    } else if ($suma >= 0) {
                        // CAMBIO: Para oportunidades de mercado, siempre usar 0 como sugerencia
                        $reponer = 0; // En lugar de abs((int) $products[$index]->solicitar);
                        $products[$index]->solicitar = 0;
                        $respuesta[] = $this->supplierProductFormat($products[$index], $ofertas[$index2]->supplier, $oferta, $reponer);
                        break;
                    }
                }
            }
        }
        return $respuesta;
    }

    public function supplierProductFormat(Product $product, Supplier $supplier, ModelsProductSupplier $productSupplier, $repuesto): array
    {
        $data = [
            // object
            "supplier" => $supplier,
            "product" => $product,
            "productSupplier" => $productSupplier,
            "precio_final_supplier" => 0,
            // data
            "reponer" => $repuesto,
            "solicitar" => $product->solicitar,
            "percentageIncrease" => 0,
            "increase" => null,
            "tolerance" => 0,
            "purchasingOpportunity" => null,
            // "checkUniquePurchaseOpportunity" => false,
        ];

        return $data;
    }

    public function checkTolerance(array $replenishTheProducts, string $conDescuento): array
    {
        // dump($conDescuento);
        for ($index = 0; $index < count($replenishTheProducts); $index++) {

            $replenishTheProduct = $replenishTheProducts[$index];
            $unitCostProductSupplier = 0;

            // Obtener el costo del lote histórico más barato para evitar que incrementos recientes queden enmascarados si el stock actual es 0
            $lowestLot = $this->productLotsRepository->checkTheLotWithTheLowestPriceOnlyProduct($replenishTheProduct["product"]);
            $lowestLotCost = $lowestLot ? (float)$lowestLot->unit_cost : (float)$replenishTheProduct["product"]->unit_cost;
            $costoBaseComparacion = ($lowestLotCost > 0 && $lowestLotCost < (float)$replenishTheProduct["product"]->unit_cost) 
                ? $lowestLotCost 
                : (float)$replenishTheProduct["product"]->unit_cost;

            if ($replenishTheProduct["productSupplier"]) {
                if ($conDescuento == "true") {
                    if ($replenishTheProduct["productSupplier"]->unit_cost_usd_with_discount != null && $replenishTheProduct["productSupplier"]->unit_cost_usd_with_discount != "") {
                        $replenishTheProduct["percentageIncrease"] = $this->calculatePercentageDifferenceIncrease($costoBaseComparacion, (float)$replenishTheProduct["productSupplier"]->unit_cost_usd_with_discount);
                        $unitCostProductSupplier = (float) $replenishTheProduct["productSupplier"]->unit_cost_usd_with_discount;
                    }
                } else {
                    if ($replenishTheProduct["productSupplier"]->unit_cost_usd != null && $replenishTheProduct["productSupplier"]->unit_cost_usd != "") {
                        $replenishTheProduct["percentageIncrease"] = $this->calculatePercentageDifferenceIncrease($costoBaseComparacion, (float)$replenishTheProduct["productSupplier"]->unit_cost_usd);
                        $unitCostProductSupplier = (float) $replenishTheProduct["productSupplier"]->unit_cost_usd;
                    }
                }
            }


            // si el prducto tiene un rango de precion entre el 0 o 4 manejamos un 20%
            if ($unitCostProductSupplier > 0 && $unitCostProductSupplier <= 4) {
                $replenishTheProduct["increase"] = $this->checkIfTheProductHasIncreasedInPrice($replenishTheProduct["percentageIncrease"], 20);
                // $replenishTheProduct["purchasingOpportunity"] = $this->checkPurchaseOpportunity($replenishTheProduct["percentageIncrease"], 0);
                $replenishTheProduct["tolerance"] = 20;
            }
            // si el producto tiene un precio mayor a 4 manejamos un 10%
            else if ($unitCostProductSupplier > 4) {
                $replenishTheProduct["increase"] = $this->checkIfTheProductHasIncreasedInPrice($replenishTheProduct["percentageIncrease"], 10);
                $replenishTheProduct["tolerance"] = 10;
            }

            $replenishTheProduct["precio_final_supplier"] = $unitCostProductSupplier;
            $replenishTheProducts[$index] = $replenishTheProduct;
        }

        return $replenishTheProducts;
    }

    public function obtainProductsWithUniqueMarketOpportunities(array $productos): array
    {
        $productosConOportunidad = [];

        for ($index = 0; $index < count($productos); $index++) {
            $producto = $productos[$index];
            $costoBaseProducto = (float) ($producto["product"]->unit_cost ?? 0);

            if ($costoBaseProducto > 0) {
                if ((float) $producto["precio_final_supplier"] < $costoBaseProducto) {
                    $fechaReferencia = $producto["product"]->updated_at ?? new \DateTime();

                    if (!($fechaReferencia instanceof \DateTimeInterface)) {
                        $fechaReferencia = new \DateTime((string) $fechaReferencia);
                    }

                    $producto["checkUniquePurchaseOpportunity"] = true;

                    $producto["cost_lot"] = $costoBaseProducto;
                    $producto["cost_lot_data"] = $fechaReferencia->format("d-m-Y");

                    $productos[$index] = $producto;
                    $productosConOportunidad[] = $productos[$index];
                }
            }
        }

        return $productosConOportunidad;
    }

    public function getTheLowestLotCost(Collection $productos): array
    {
        $productosConOportunidad = [];
        for ($index = 0; $index < count($productos); $index++) {
            # code...
            $producto = $productos[$index];

            $lote = $this->productLotsRepository->checkTheLotWithTheLowestPriceOnlyProduct($producto);
            $producto->lote = $lote;
            $productosConOportunidad[] = $producto;
        }

        return $productosConOportunidad;
    }
}
