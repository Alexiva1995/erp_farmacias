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
    /**
     * Construye una consulta filtrada para las facturas.
     *
     * @param Request $request
     * @return Builder
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = Invoice::query()->with('supplier');

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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'Loaded');
        }

        if ($request->filled('startDate')) {
            $query->whereDate('exp_date', '>=', $request->startDate);
        }

        if ($request->filled('endDate')) {
            $query->whereDate('exp_date', '<=', $request->endDate);
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
    public function getForOrderQuery(Request $request): Builder
    {
        $query = Invoice::query()
            ->with('supplier')
            ->where('status', 'to_order');

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

        if ($request->filled('startDate')) {
            $query->whereDate('exp_date', '>=', $request->startDate);
        }

        if ($request->filled('endDate')) {
            $query->whereDate('exp_date', '<=', $request->endDate);
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
    public function getSuggestedAndExistingDetails(Invoice $invoice): Collection
    {
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
            ];
        });
    }
    public function getInvoiceById(Invoice $invoice): Invoice
    {
        $invoice->load(['supplier', 'discountRule']);

        return $invoice;
    }
}
