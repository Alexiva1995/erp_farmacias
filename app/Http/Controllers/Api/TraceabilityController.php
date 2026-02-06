<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductDistribution;
use App\Models\ReturnEntry;
use App\Services\Traceability\TraceabilityQueryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TraceabilityController extends Controller
{
    public function __construct(
        private TraceabilityQueryService $salesReportQueryService
    ) {}

    public function index(Request $request)
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        // For purchase movements without invoice_id, try to find invoice through product_lot
        $items = collect($paginatedResult->items())->map(function ($item) {
            if (($item->movement_type === 'Compra' || $item->getAttributes()['movement_type'] === 'purchase') 
                && !$item->invoice_id 
                && $item->product_lot_id 
                && !$item->relationLoaded('invoice')) {
                
                // Load product lot
                $item->load('productLot');
                
                if ($item->productLot) {
                    // Try to find invoice through InvoiceDetail
                    $invoiceDetail = \App\Models\InvoiceDetail::where('product_id', $item->product_id)
                        ->where('lot_number', $item->productLot->lot_number)
                        ->where('expiration_date', $item->productLot->expiration_date)
                        ->with('invoice.supplier')
                        ->first();
                    
                    if ($invoiceDetail && $invoiceDetail->invoice) {
                        $item->setRelation('invoice', $invoiceDetail->invoice);
                    }
                }
            }
            return $item;
        })->all();

        return response()->json([
            'data' => $items,
            'total' => $paginatedResult->total(),
        ]);
    }

    public function filterByPsychotropics(Request $request)
    {
        $query = $this->salesReportQueryService->getFilteredQueryByPsychotropics($request);

        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $format = $request->input('format', 'xlsx');
        $fileName = 'reporte-ventas-' . now()->format('Y-m-d') . '.' . $format;

        return Excel::download(new TraceabilityExport($query), $fileName);
    }

    public function getMovementDetails(InventoryMovement $movement)
    {
        $movement->load(['product', 'user.employee', 'order.seller.employee', 'order.client', 'order' => function($query) {
            $query->select('id', 'url_recipe', 'seller_id', 'client_id');
        }, 'invoice.supplier', 'supplier']);

        $details = [
            'movement' => $movement,
            'type' => $movement->getAttributes()['movement_type'], // Get raw value
            'display_type' => $movement->movement_type, // Get formatted value
        ];

        // Handle different movement types
        switch ($movement->getAttributes()['movement_type']) {
            case 'return':
                // For returns, get the ReturnEntry related to the order
                $returnEntry = ReturnEntry::where('order_id', $movement->order_id)
                    ->where('product_id', $movement->product_id)
                    ->with(['order.seller', 'order.client'])
                    ->first();

                if ($returnEntry) {
                    $details['return_entry'] = $returnEntry;
                    $details['original_order'] = $returnEntry->order;
                    $processedBy = $returnEntry->generated_by_id ? \App\Models\User::with('employee')->find($returnEntry->generated_by_id) : null;
                    $details['processed_by'] = $processedBy;
                    $details['status'] = $returnEntry->status;
                }
                break;

            case 'sale':
                // For sales, the order and seller are already loaded
                $details['order'] = $movement->order;
                $details['seller'] = $movement->order?->seller;
                break;

            case 'purchase':
                // For purchases, load invoice with supplier relationship
                $invoice = null;
                
                if ($movement->invoice_id) {
                    // Load invoice directly if invoice_id exists
                    $invoice = \App\Models\Invoice::with('supplier')->find($movement->invoice_id);
                } elseif ($movement->product_lot_id) {
                    // If no invoice_id but has product_lot_id, try to find invoice through the lot
                    $productLot = $movement->productLot;
                    if ($productLot) {
                        // Try to find invoice through InvoiceDetail matching product and lot
                        $invoiceDetail = \App\Models\InvoiceDetail::where('product_id', $movement->product_id)
                            ->where('lot_number', $productLot->lot_number)
                            ->where('expiration_date', $productLot->expiration_date)
                            ->with('invoice.supplier')
                            ->first();
                        
                        if ($invoiceDetail && $invoiceDetail->invoice) {
                            $invoice = $invoiceDetail->invoice;
                            $invoice->load('supplier');
                        }
                    }
                }
                
                $details['invoice'] = $invoice;
                $details['supplier'] = $invoice?->supplier ?? $movement->supplier;
                break;

            case 'adjustment':
            case 'loss':
                // For adjustments and losses, find related ProductCount via ProductDistribution
                // The movement is created when a ProductCount is approved and lot quantities are updated
                // So we search for ProductCounts approved near the movement date
                if ($movement->product_lot_id) {
                    $movementDate = Carbon::parse($movement->movement_date);
                    $startDate = $movementDate->copy()->subHours(2); // 2 hours before movement
                    $endDate = $movementDate->copy()->addHours(2); // 2 hours after movement

                    $productDistribution = ProductDistribution::where('product_lot_id', $movement->product_lot_id)
                        ->whereHas('productCount', function ($query) use ($movement, $startDate, $endDate) {
                            $query->where('status', 'approved')
                                ->where('product_id', $movement->product_id)
                                ->whereBetween('updated_at', [$startDate, $endDate])
                                ->orderBy('updated_at', 'desc');
                        })
                        ->with(['productCount.user', 'productCount.supervisor'])
                        ->first();

                    // Fallback: get the most recent approved ProductCount for this lot and product
                    if (!$productDistribution) {
                        $productDistribution = ProductDistribution::where('product_lot_id', $movement->product_lot_id)
                            ->whereHas('productCount', function ($query) use ($movement) {
                                $query->where('status', 'approved')
                                    ->where('product_id', $movement->product_id)
                                    ->orderBy('updated_at', 'desc');
                            })
                            ->with(['productCount.user', 'productCount.supervisor'])
                            ->first();
                    }

                    if ($productDistribution && $productDistribution->productCount) {
                        $productCount = $productDistribution->productCount;
                        $details['product_count'] = $productCount;
                        $countedBy = $productCount->user;
                        if ($countedBy) {
                            $countedBy->load('employee');
                        }
                        $details['counted_by'] = $countedBy;
                        $approvedBy = $productCount->supervisor;
                        if ($approvedBy) {
                            $approvedBy->load('employee');
                        }
                        $details['approved_by'] = $approvedBy;
                    }
                }
                break;

            case 'expired':
                // For expired products, the user_id in the movement is who expired it
                $details['expired_by'] = $movement->user;
                break;
        }

        return response()->json(['data' => $details]);
    }

    /**
     * Registra un ajuste inicial por producto para trazabilidad: Stock A = 0, Stock F = suma de lotes.
     * Un registro por producto, realizado por el admin. Para dejar la trazabilidad lista con el stock actual.
     */
    public function registerBaselineAdjustments(Request $request)
    {
        $user = Auth::user();
        if (!$user || (int) $user->role_id !== 1) {
            return response()->json(['message' => 'No autorizado. Solo el administrador puede ejecutar esta acción.'], 403);
        }

        $now = Carbon::now();

        $products = Product::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->withSum('lots as total_stock', 'quantity')
            ->get();

        $created = 0;
        DB::beginTransaction();
        try {
            foreach ($products as $product) {
                $stock = (int) ($product->total_stock ?? 0);
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_lot_id' => null,
                    'movement_type' => 'adjustment',
                    'quantity' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'user_id' => $user->id,
                    'movement_date' => $now,
                ]);
                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar los ajustes: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "Se registraron {$created} ajustes iniciales (Stock A=0, Stock F=stock actual). Realizado por administrador.",
            'created' => $created,
        ]);
    }
}
