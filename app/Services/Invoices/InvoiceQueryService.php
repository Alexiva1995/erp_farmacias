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
        return $invoice->details()->with(['product.laboratory'])->get();
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
}
