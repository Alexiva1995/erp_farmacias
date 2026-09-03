<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Traceability\TraceabilityIndexRequest;
use App\Http\Resources\Traceability\TraceabilityResource;
use App\Models\InventoryMovement;
use App\Models\InvoiceDetail;
use App\Models\Product;
use App\Models\ReturnEntry;
use App\Models\User;
use App\Services\Traceability\TraceabilityQueryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TraceabilityController extends Controller
{
    public function __construct(
        private TraceabilityQueryService $salesReportQueryService
    ) {}

    /**
     * Listado paginado de movimientos de trazabilidad.
     */
    public function index(TraceabilityIndexRequest $request): JsonResponse
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        $items = collect($paginatedResult->items());

        // Carga diferida optimizada para compras sin invoice_id directo
        $purchaseItems = $items->filter(function ($item) {
            $rawType = $item->getAttributes()['movement_type'] ?? null;
            return ($item->movement_type === 'Compra' || $rawType === 'purchase')
                && !$item->invoice_id
                && $item->product_lot_id
                && !$item->relationLoaded('invoice');
        });

        if ($purchaseItems->isNotEmpty()) {
            $purchaseItems->load('productLot');

            $queryConditions = [];
            foreach ($purchaseItems as $item) {
                if ($item->productLot) {
                    $queryConditions[] = [
                        'product_id' => $item->product_id,
                        'lot_number' => $item->productLot->lot_number,
                        'expiration_date' => $item->productLot->expiration_date,
                    ];
                }
            }

            if (!empty($queryConditions)) {
                $invoiceDetails = InvoiceDetail::query()
                    ->with('invoice.supplier')
                    ->where(function ($q) use ($queryConditions) {
                        foreach ($queryConditions as $cond) {
                            $q->orWhere(function ($subQ) use ($cond) {
                                $subQ->where('product_id', $cond['product_id'])
                                     ->where('lot_number', $cond['lot_number'])
                                     ->where('expiration_date', $cond['expiration_date']);
                            });
                        }
                    })->get();

                foreach ($purchaseItems as $item) {
                    if ($item->productLot) {
                        $match = $invoiceDetails->first(function ($detail) use ($item) {
                            return $detail->product_id === $item->product_id
                                && $detail->lot_number === $item->productLot->lot_number
                                && $detail->expiration_date === $item->productLot->expiration_date;
                        });

                        if ($match && $match->invoice) {
                            $item->setRelation('invoice', $match->invoice);
                        }
                    }
                }
            }
        }

        return response()->json([
            'data' => TraceabilityResource::collection($items),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Listado filtrado por psicotrópicos.
     */
    public function filterByPsychotropics(TraceabilityIndexRequest $request): JsonResponse
    {
        $query = $this->salesReportQueryService->getFilteredQueryByPsychotropics($request);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => TraceabilityResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
    }

    /**
     * Exportación de reporte a Excel/CSV.
     */
    public function export(TraceabilityIndexRequest $request)
    {
        $query = $this->salesReportQueryService->getFilteredQuery($request);

        $format = $request->input('format', 'xlsx');
        $fileName = 'reporte-trazabilidad-' . now()->format('Y-m-d') . '.' . $format;

        return Excel::download(new TraceabilityExport($query), $fileName);
    }

    /**
     * Detalles extendidos de un movimiento específico.
     */
    public function getMovementDetails(InventoryMovement $movement): JsonResponse
    {
        $movement->load([
            'product.laboratory',
            'productLot',
            'user.employee',
            'order.seller.employee',
            'order.client',
            'order' => function ($query) {
                $query->select('id', 'url_recipe', 'seller_id', 'client_id');
            },
            'invoice.supplier',
            'supplier'
        ]);

        $rawType = $movement->getAttributes()['movement_type'] ?? null;

        $details = [
            'movement' => $movement,
            'type' => $rawType,
            'display_type' => $movement->movement_type,
        ];

        switch ($rawType) {
            case 'return':
                $returnEntry = ReturnEntry::where('order_id', $movement->order_id)
                    ->where('product_id', $movement->product_id)
                    ->with(['order.seller', 'order.client'])
                    ->first();

                if ($returnEntry) {
                    $details['return_entry'] = $returnEntry;
                    $details['original_order'] = $returnEntry->order;
                    $processedBy = $returnEntry->generated_by_id ? User::with('employee')->find($returnEntry->generated_by_id) : null;
                    $details['processed_by'] = $processedBy;
                    $details['status'] = $returnEntry->status;
                }
                break;

            case 'sale':
                $details['order'] = $movement->order;
                $details['seller'] = $movement->order?->seller;
                break;

            case 'purchase':
                $invoice = null;
                if ($movement->invoice_id) {
                    $invoice = \App\Models\Invoice::with('supplier')->find($movement->invoice_id);
                } elseif ($movement->product_lot_id && $movement->productLot) {
                    $productLot = $movement->productLot;
                    $invoiceDetail = InvoiceDetail::where('product_id', $movement->product_id)
                        ->where('lot_number', $productLot->lot_number)
                        ->where('expiration_date', $productLot->expiration_date)
                        ->with('invoice.supplier')
                        ->first();

                    if ($invoiceDetail && $invoiceDetail->invoice) {
                        $invoice = $invoiceDetail->invoice;
                    }
                }

                $details['invoice'] = $invoice;
                $details['supplier'] = $invoice?->supplier ?? $movement->supplier;
                break;

            case 'adjustment':
            case 'loss':
            case 'verification':
                $countRecord = null;
                if ($movement->product_count_id) {
                    $countRecord = \App\Models\ProductCount::with(['user.employee', 'supervisor.employee'])->find($movement->product_count_id);
                }

                if (!$countRecord && $movement->product_lot_id) {
                    $expiredLog = \App\Models\ExpiredLog::where('lot_id', $movement->product_lot_id)
                        ->where('created_at', '>=', Carbon::parse($movement->created_at ?? $movement->movement_date)->subMinutes(10))
                        ->first();
                    if ($expiredLog) {
                        $details['type'] = 'expired';
                        $details['display_type'] = 'Caducado';
                        $details['expired_by'] = $movement->user;
                        break;
                    }
                }

                if ($countRecord) {
                    $isAutoApproved = is_null($countRecord->supervisor_id) && ((float) $countRecord->discrepancy === 0.0);
                    $details['counted_by'] = $countRecord->user;
                    $details['approved_by'] = $countRecord->supervisor;
                    $details['is_auto_approved'] = $isAutoApproved;
                    $details['count_date'] = $countRecord->created_at;
                    $details['approval_date'] = $countRecord->updated_at ?? $movement->movement_date;
                    $details['counted_quantity'] = (float) $countRecord->counted_quantity;
                    $details['system_quantity'] = (float) $countRecord->system_quantity;
                    $details['audited_quantity'] = (float) $countRecord->counted_quantity;
                    $details['discrepancy'] = (float) $countRecord->discrepancy;
                    $details['product_count'] = $countRecord;
                } else {
                    $details['type'] = 'general';
                    $details['counted_by'] = $movement->user;
                    $details['approved_by'] = $movement->user;
                    $details['count_date'] = $movement->movement_date;
                    $details['approval_date'] = $movement->movement_date;
                }
                break;

            case 'expired':
                $details['expired_by'] = $movement->user;
                $details['counted_by'] = $movement->user;
                $details['approved_by'] = $movement->user;
                break;
        }

        return response()->json(['data' => $details]);
    }

    /**
     * Registra un ajuste inicial por producto para trazabilidad.
     */
    public function registerBaselineAdjustments(Request $request): JsonResponse
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
