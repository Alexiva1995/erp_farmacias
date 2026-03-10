<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\SuppliersConfigProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceQueryService
{
    public function getInvoicesQuery(Request $request): Builder
    {
        // IMPORTANTE: Las facturas se muestran a TODOS los usuarios sin filtrar por usuario
        // No se aplica ningún filtro por uploaded_by, registered_by, ordered_by, etc.
        $query = Invoice::query()->with('supplier');

        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_number', 'like', '%' . $request->q . '%')
                    ->orWhere('control_number', 'like', '%' . $request->q . '%')
                    ->orWhereHas('supplier', function ($supplierQuery) use ($request) {
                        $supplierQuery->where('name', 'like', '%' . $request->q . '%');
                    });
            });
        }

        if ($request->filled('supplierId')) {
            $query->where('supplier_id', $request->supplierId);
        }

        $dateColumn = 'exp_date';
        if ($request->input('status') && in_array('pending', (array) $request->input('status'))) {
            $dateColumn = 'received_date';
        }

        if ($request->filled('startDate')) {
            $query->whereDate($dateColumn, '>=', $request->startDate);
        }
        if ($request->filled('endDate')) {
            $query->whereDate($dateColumn, '<=', $request->endDate);
        }

        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            if ($request->sortBy === 'supplier.name') {
                $query->join('suppliers', 'invoices.supplier_id', '=', 'suppliers.id')
                    ->orderBy('suppliers.name', $request->orderBy)
                    ->select('invoices.*');
            } else {
                $query->orderBy($request->sortBy, $request->orderBy);
            }
        } else {
            $query->orderBy('invoices.id', 'desc');
        }

        return $query;
    }

    public function getInvoiceDetails(Invoice $invoice): Collection
    {
        $invoice->load([
            'details.product.laboratory',
            'returns.product.laboratory',
            'supplier.autoOrders.details.productSupplier.product.laboratory'
        ]);

        $normalDetails = $invoice->details()
            ->with('product.laboratory')
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($detail) {
                $detail->is_return = false;
                return $detail;
            });

        $returnDetails = $invoice->returns->map(function ($returnItem) {
            $unitCostUSD = ($returnItem->quantity > 0)
                ? ($returnItem->amount_refunded / $returnItem->quantity)
                : 0;

            return (object) [
                'id' => 'return_' . $returnItem->id,
                'product_id' => $returnItem->product_id,
                'product' => $returnItem->product,
                'quantity' => $returnItem->quantity,
                'unit_cost' => $unitCostUSD,
                'total_cost' => $returnItem->amount_refunded,
                'lot_number' => $returnItem->lot_number,
                'expiration_date' => $returnItem->expiration_date,
                'location' => 'N/A',
                'tax_enabled' => $returnItem->product->iva,
                'is_return' => true,
            ];
        });


        // LOGIC REMOVED: Auto-hydration from AutoOrder is now handled at creation time.
        // This ensures that if a user deletes all products, they stay deleted.

        if ($normalDetails->isEmpty() && $returnDetails->isEmpty()) {
            $autoOrderDetails = collect();

            $selectableAutoOrders = $invoice->supplier->autoOrders()->where('status', 0)->get();

            foreach ($selectableAutoOrders as $autoOrder) {
                $selectableDetails = $autoOrder->details()->where('status', 0)->get();

                foreach ($selectableDetails as $autoOrderDetail) {
                    if ($autoOrderDetail->productSupplier && $autoOrderDetail->productSupplier->product) {
                        $product = $autoOrderDetail->productSupplier->product;

                        $autoOrderDetails->push((object) [
                            'id' => 'auto_' . $autoOrderDetail->id,
                            'product_id' => $product->id,
                            'product' => $product,
                            'quantity' => $autoOrderDetail->quantity,
                            'unit_cost' => $autoOrderDetail->unit_cost,
                            'total_cost' => $autoOrderDetail->subtotal,
                            'lot_number' => '',
                            'location' => 'Por Asignar',
                            'tax_enabled' => $product->iva ?? false,
                            'is_return' => false,
                            'expiration_date' => $autoOrderDetail->productSupplier->expiration,
                            'auto_order_detail_id' => $autoOrderDetail->id,
                        ]);
                    }
                }
            }

            return $autoOrderDetails;
        }

        $mergedArray = array_merge($normalDetails->all(), $returnDetails->all());
        return collect($mergedArray);
    }


    public function getSuggestedAndExistingDetails(Invoice $invoice): Collection
    {
        if ($invoice->details()->exists()) {

            return $invoice->details()->with(['product.laboratory'])->get();
        } else {

            $configuredProducts = SuppliersConfigProduct::query()
                ->where('supplier_id', $invoice->supplier_id)
                ->join('products', 'suppliers_config_products.barcode', '=', 'products.barcode')
                ->select(
                    'products.id as product_id',
                    'products.name as product_name',
                    'products.is_deleted',
                    'suppliers_config_products.price as supplier_price'
                )
                ->get();

            return $configuredProducts->map(function ($productData) {
                return [
                    'id' => 'new-' . $productData->product_id,
                    'invoice_id' => null,
                    'product' => [
                        'id' => $productData->product_id,
                        'name' => $productData->product_name,
                        'is_deleted' => (bool) $productData->is_deleted,
                    ],
                    'quantity' => 1,
                    'unit_cost' => $productData->supplier_price,
                    'lot_number' => null,
                    'expiration_date' => null,
                    'location' => null,
                    'total_cost' => $productData->supplier_price,
                ];
            });
        }
    }

    public function getInvoiceById(Invoice $invoice): Invoice
    {
        $invoice->load(['supplier', 'discountRule']);

        return $invoice;
    }
    public function calculateSupplierDebts(): float
    {
        $totalDebts = Invoice::where(function ($query) {
            $query->where('status_payment', 0)
                ->orWhereNull('status_payment');
        })
            ->where('total_usd', '>', 0)
            ->sum('total_usd');

        return (float) ($totalDebts ?? 0);
    }

    public function matchBarcodeWithAutoOrder(string $barcode, int $supplierId, int $autoOrderId): ?object
    {
        // 1. Buscar el producto por barcode
        $product = Product::where('barcode', $barcode)->first();

        if (!$product) {
            return null; // Producto no existe
        }

        // 2. Buscar si el producto pertenece a la orden mediante product_suppliers
        $autoOrderDetail = DB::table('auto_order_details')
            ->join('product_suppliers', 'auto_order_details.product_suppliers_id', '=', 'product_suppliers.id')
            ->where('auto_order_details.order_id', $autoOrderId)
            ->where('product_suppliers.product_id', $product->id)
            ->where('product_suppliers.supplier_id', $supplierId)
            ->select(
                'auto_order_details.id as auto_order_detail_id',
                'auto_order_details.quantity',
                'auto_order_details.unit_cost',
                'auto_order_details.subtotal',
                'product_suppliers.expiration',
                'product_suppliers.price as supplier_price'
            )
            ->first();

        if (!$autoOrderDetail) {
            return (object) [
                'error' => 'not_in_order',
                'product' => $product
            ]; // El producto existe pero no se pidió en esta orden
        }

        // 3. Devolver la estructura lista para InvoiceDetails en Frontend
        return (object) [
            'id' => 'auto_' . $autoOrderDetail->auto_order_detail_id,
            'product_id' => $product->id,
            'product' => $product,
            'quantity' => $autoOrderDetail->quantity,
            'unit_cost' => $autoOrderDetail->unit_cost,
            'total_cost' => $autoOrderDetail->subtotal,
            'lot_number' => '',
            'location' => 'Por Asignar',
            'tax_enabled' => $product->iva ?? false,
            'is_return' => false,
            'expiration_date' => $autoOrderDetail->expiration,
            'auto_order_detail_id' => $autoOrderDetail->auto_order_detail_id,
        ];
    }
}
