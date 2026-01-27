<?php

namespace App\Services\Quotation;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class QuotationActionService
{

    public function __construct(

    ) {
    }

    public function loadProductDetails(Product $product): Product
    {
        $product->load([
            'laboratory',
            'individualOffers',
            'category.offers',
        ]);

        // Calcular valid_stock_sum (stock válido: lotes no expirados con cantidad > 0)
        $validStockSum = $product->lots()
            ->where('expiration_date', '>=', now())
            ->where('quantity', '>', 0)
            ->sum('quantity');
        
        // Agregar valid_stock_sum como atributo al producto usando setRawAttributes
        // para asegurar que se incluya en la serialización JSON
        $product->setAttribute('valid_stock_sum', $validStockSum ?? 0);
        
        // Asegurar que los accesores estén disponibles en la serialización
        $product->append(['price_bs', 'price_cop', 'discount_percentage', 'discount_type', 'discount_source_id']);
        
        return $product;
    }


    public function createQuotation(array $validatedData): Quotation
    {
        DB::beginTransaction();
        try {
            $quotationProductsData = [];
            $now = now();
            $taxExemptValue = ($validatedData['total_iva_usd'] > 0) ? 1 : 0;

            $quotation = Quotation::create([
                'currency' => $validatedData['currency'] ?? null,
                'tax_exempt' => $taxExemptValue,
                'vat' => $validatedData['total_iva_usd'],
                'total' => $validatedData['grand_total_usd'],
                'created_by' => \Auth::id(),
                'client_id' => empty($validatedData['client_id']) ? null : $validatedData['client_id']
            ]);

            foreach ($validatedData['products'] as $itemData) {
                $quotationProductsData[] = [
                    'quotation_id' => $quotation->id,
                    'product_id' => $itemData['id'],
                    'units' => $itemData['quantity'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($quotationProductsData)) {
                QuotationProduct::insert($quotationProductsData);
            }
            DB::commit();
            $quotation->load(['products.product']);
            return $quotation;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating quotation: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }

    public function getProducts(string $quotationId): ?Quotation
    {
        $quotation = Quotation::with('products')->find($quotationId);
        return $quotation;
    }

    public function getLastNumber(): ?Quotation
    {
        $quotation = Quotation::latest('id')->first();
        return $quotation;
    }
}
