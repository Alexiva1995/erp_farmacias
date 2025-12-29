<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\ProductDistribution;
use App\Models\ReturnEntry;
use App\Services\Traceability\TraceabilityQueryService;
use Illuminate\Http\Request;
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

        return response()->json([
            'data' => $paginatedResult->items(),
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
        $movement->load(['product', 'user', 'order.seller', 'order.client', 'invoice', 'supplier']);

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
                    $details['processed_by'] = $returnEntry->generated_by_id ? \App\Models\User::find($returnEntry->generated_by_id) : null;
                    $details['status'] = $returnEntry->status;
                }
                break;

            case 'sale':
                // For sales, the order and seller are already loaded
                $details['order'] = $movement->order;
                $details['seller'] = $movement->order?->seller;
                break;

            case 'purchase':
                // For purchases, the invoice is already loaded
                $details['invoice'] = $movement->invoice;
                break;

            case 'adjustment':
            case 'loss':
                // For adjustments and losses, find related ProductCount via ProductDistribution
                // Try to find the ProductCount approved closest to the movement date
                if ($movement->product_lot_id) {
                    $productDistribution = ProductDistribution::where('product_lot_id', $movement->product_lot_id)
                        ->whereHas('productCount', function ($query) use ($movement) {
                            $query->where('status', 'approved')
                                ->where('product_id', $movement->product_id)
                                ->whereDate('updated_at', '<=', $movement->movement_date)
                                ->orderBy('updated_at', 'desc');
                        })
                        ->with(['productCount.user', 'productCount.supervisor'])
                        ->first();

                    // Fallback: just get the first one if date-based search fails
                    if (!$productDistribution) {
                        $productDistribution = ProductDistribution::where('product_lot_id', $movement->product_lot_id)
                            ->whereHas('productCount', function ($query) use ($movement) {
                                $query->where('status', 'approved')
                                    ->where('product_id', $movement->product_id);
                            })
                            ->with(['productCount.user', 'productCount.supervisor'])
                            ->orderBy('created_at', 'desc')
                            ->first();
                    }

                    if ($productDistribution && $productDistribution->productCount) {
                        $productCount = $productDistribution->productCount;
                        $details['product_count'] = $productCount;
                        $details['counted_by'] = $productCount->user;
                        $details['approved_by'] = $productCount->supervisor;
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
}
