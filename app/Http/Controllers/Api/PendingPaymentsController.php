<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ExchangeRate;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PendingPaymentsController extends Controller
{
    /**
     * Obtener facturas pendientes de pago agrupadas por proveedor y fecha
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['supplier'])
                ->whereIn('status', ['loaded', 'to_order'])
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 'paid');
                })
                ->orderBy('payment_date', 'asc');

            // Aplicar filtros si existen
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('payment_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('payment_date', '<=', $request->end_date);
            }

            $invoices = $query->get();

            // Log temporal para debugging
            Log::info('Facturas encontradas en PendingPayments:', [
                'total_facturas' => $invoices->count(),
                'facturas' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'supplier_id' => $invoice->supplier_id,
                        'supplier_name' => $invoice->supplier->name ?? 'N/A',
                        'status' => $invoice->status,
                        'payment_date' => $invoice->payment_date,
                        'currency' => $invoice->currency,
                        'total_amount' => $invoice->total_amount
                    ];
                })
            ]);

            // Agrupar por proveedor y fecha de pago
            $groupedInvoices = $invoices->groupBy(function ($invoice) {
                return $invoice->supplier_id . '_' . $invoice->payment_date;
            })->map(function ($group) {
                $firstInvoice = $group->first();

                // Calcular total en USD para el grupo
                $totalAmountUSD = $group->sum('total_usd');

                // Calcular total en la moneda original del grupo
                $totalAmountOriginal = $group->sum('total_amount');

                return [
                    'supplier_id' => $firstInvoice->supplier_id,
                    'supplier_name' => $firstInvoice->supplier->name,
                    'payment_date' => $firstInvoice->payment_date,
                    'currency' => $firstInvoice->currency,
                    'total_amount' => $totalAmountOriginal, // Monto en moneda original
                    'total_amount_usd' => $totalAmountUSD, // Monto en USD
                    'invoice_count' => $group->count(),
                    'invoices' => $group->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => $invoice->total_amount,
                            'total_amount_usd' => $invoice->total_usd,
                            'currency' => $invoice->currency,
                            'exchange_rate' => $invoice->exchange_rate,
                            'exp_date' => $invoice->exp_date,
                        ];
                    })
                ];
            })->values();

            return ApiResponse::success([
                'pending_payments' => $groupedInvoices,
                'total_groups' => $groupedInvoices->count(),
                'total_amount' => $invoices->sum('total_usd') // Usar total_usd en lugar de total_amount
            ], 'Facturas pendientes obtenidas exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener las facturas pendientes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener facturas pendientes de un proveedor específico
     */
    public function getSupplierInvoices(Request $request, int $supplierId): JsonResponse
    {
        try {
            $invoices = Invoice::with(['supplier'])
                ->where('supplier_id', $supplierId)
                ->whereIn('status', ['loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 'paid');
                })
                ->orderBy('payment_date', 'asc')
                ->get();

            if ($invoices->isEmpty()) {
                return ApiResponse::error('No se encontraron facturas pendientes para este proveedor', 404);
            }

            return ApiResponse::success([
                'supplier' => $invoices->first()->supplier,
                'invoices' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'control_number' => $invoice->control_number,
                        'total_amount' => $invoice->total_amount,
                        'currency' => $invoice->currency,
                        'exp_date' => $invoice->exp_date,
                        'payment_date' => $invoice->payment_date,
                        'status' => $invoice->status,
                    ];
                }),
                'total_amount' => $invoices->sum('total_amount')
            ], 'Facturas del proveedor obtenidas exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener las facturas del proveedor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Procesar pago de facturas
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'invoice_ids' => 'required|array',
                'invoice_ids.*' => 'exists:invoices,id',
                'payment_currency' => 'required|in:VES,USD,COP',
                'payment_amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'photo_url' => 'nullable|string',
                'notes' => 'nullable|string|max:500'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ProcessPayment - Validation Error:', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);
            return ApiResponse::error('Datos de validación incorrectos', 400, $e->errors());
        }

        try {
            // Log de debug
            Log::info('ProcessPayment - Datos recibidos:', [
                'invoice_ids' => $request->invoice_ids,
                'payment_currency' => $request->payment_currency,
                'payment_amount' => $request->payment_amount,
                'payment_date' => $request->payment_date
            ]);

            // 1. Verificar que las facturas no estén ya pagadas
            $invoices = Invoice::with(['supplier'])
                ->whereIn('id', $request->invoice_ids)
                ->get();

            Log::info('ProcessPayment - Facturas encontradas:', [
                'total_facturas' => $invoices->count(),
                'facturas' => $invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'status_payment' => $invoice->status_payment,
                        'supplier_name' => $invoice->supplier->name ?? 'N/A'
                    ];
                })
            ]);

            foreach ($invoices as $invoice) {
                if ($invoice->status_payment === 'paid') {
                    return ApiResponse::error(
                        "La factura {$invoice->invoice_number} ya está pagada",
                        400
                    );
                }
            }

            // 2. Verificar duplicados (mismo monto, misma fecha, mismas facturas)
            $duplicatePayment = InvoicePayment::where('amount', $request->payment_amount)
                ->where('payment_date', $request->payment_date)
                ->where('payment_method', $request->payment_currency)
                ->whereHas('invoices', function ($query) use ($request) {
                    $query->whereIn('id', $request->invoice_ids);
                })
                ->first();

            if ($duplicatePayment) {
                return ApiResponse::error(
                    'Ya existe un pago idéntico registrado para estas facturas. Por favor, verifica los datos.',
                    400
                );
            }

            DB::beginTransaction();

            // 3. Filtrar facturas válidas para pago
            $invoices = $invoices->filter(function ($invoice) {
                return in_array($invoice->status, ['loaded', 'to_order']) &&
                    (is_null($invoice->status_payment) || $invoice->status_payment !== 'paid');
            });

            if ($invoices->isEmpty()) {
                return ApiResponse::error('No se encontraron facturas válidas para procesar', 404);
            }

            // 2. Normalizar código de moneda y obtener tasa de cambio
            $normalizedCurrency = $this->normalizeCurrencyCode($request->payment_currency);
            $exchangeRate = ExchangeRate::where('currency_code', $normalizedCurrency)->first();
            if (!$exchangeRate) {
                return ApiResponse::error('No se encontró tasa de cambio para la moneda seleccionada', 400);
            }

            // 3. Calcular monto en USD
            if ($normalizedCurrency === 'USD') {
                $amountUSD = $request->payment_amount;
            } else {
                // Para otras monedas, dividir por la tasa (1 USD = X moneda)
                // El usuario ingresa el monto en la moneda local, lo convertimos a USD
                // Redondear a 2 decimales
                $amountUSD = round($request->payment_amount / $exchangeRate->rate, 2);
            }
            $totalInvoiceAmount = $invoices->sum('total_usd');

            // 4. Crear registro en invoice_payments usando campos existentes
            // Crear JSON compacto para el campo reference (máximo 100 caracteres)
            $referenceData = [
                'usd' => round($amountUSD, 2),
                'rate' => round($exchangeRate->rate, 4),
                'total' => round($totalInvoiceAmount, 2),
                'notes' => $request->notes ? substr($request->notes, 0, 20) : null
            ];

            // Asegurar que el JSON no exceda 100 caracteres
            $referenceJson = json_encode($referenceData);
            if (strlen($referenceJson) > 100) {
                // Si excede, reducir las notas
                $referenceData['notes'] = $request->notes ? substr($request->notes, 0, 10) : null;
                $referenceJson = json_encode($referenceData);
            }

            $payment = InvoicePayment::create([
                'payment_date' => $request->payment_date,
                'amount' => $request->payment_amount,
                'payment_method' => $normalizedCurrency, // Usar moneda normalizada
                'reference' => $referenceJson, // JSON compacto
                'status' => 'paid',
                'payment_by' => 1, // TODO: Obtener ID del usuario autenticado
                'photo_url' => $request->photo_url,
            ]);

            // 5. Crear relaciones en tabla pivot
            foreach ($request->invoice_ids as $invoiceId) {
                DB::table('invoice_payment_invoice')->insert([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                ]);
            }

            // 6. Actualizar facturas
            $paymentStatus = $this->determinePaymentStatus($request->invoice_ids, $amountUSD, $totalInvoiceAmount);
            // El campo status solo acepta: 'pending', 'loaded', 'to_order', 'ordered'
            // Usamos 'ordered' para facturas completamente pagadas
            $newStatus = $paymentStatus === 'paid' ? 'ordered' : 'to_order';

            Invoice::whereIn('id', $request->invoice_ids)->update([
                'status' => $newStatus,
                'status_payment' => $paymentStatus,
                'payment_date' => $request->payment_date,
                'updated_at' => now(),
            ]);

            // 7. Crear expense
            $this->createExpense($invoices, $payment, $amountUSD);

            DB::commit();

            return ApiResponse::success([
                'payment_id' => $payment->id,
                'processed_invoices' => $request->invoice_ids,
                'amount_paid' => $request->payment_amount,
                'currency' => $request->payment_currency,
                'amount_usd' => $amountUSD,
                'exchange_rate' => $exchangeRate->rate,
                'payment_status' => $paymentStatus,
                'total_invoice_amount' => $totalInvoiceAmount
            ], 'Pago procesado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al procesar el pago: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener historial de pagos realizados
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        try {
            $query = InvoicePayment::with(['invoices.supplier', 'user'])
                ->orderBy('created_at', 'desc');

            // Aplicar filtros si existen
            if ($request->filled('supplier_id')) {
                $query->whereHas('invoices', function ($q) use ($request) {
                    $q->where('supplier_id', $request->supplier_id);
                });
            }

            if ($request->filled('currency')) {
                $query->whereHas('invoices', function ($q) use ($request) {
                    $q->where('currency', $request->currency);
                });
            }

            if ($request->filled('start_date')) {
                $query->whereDate('payment_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('payment_date', '<=', $request->end_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('invoices', function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $payments = $query->paginate(15);

            // Agregar información adicional a cada pago
            $payments->getCollection()->transform(function ($payment) {
                $firstInvoice = $payment->invoices->first();
                // Usar la moneda del pago (payment_method) en lugar de la moneda de la factura
                $payment->currency = $payment->payment_method;
                // Calcular el equivalente en USD desde el JSON del reference
                $referenceData = json_decode($payment->reference, true);
                $payment->amount_usd = $referenceData['usd'] ?? 0;
                return $payment;
            });

            return ApiResponse::success($payments);
        } catch (\Exception $e) {
            Log::error('Error al obtener historial de pagos: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener el historial de pagos', 500);
        }
    }

    /**
     * Obtener estadísticas de pagos pendientes
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $baseQuery = Invoice::whereIn('status', ['loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 'paid');
                });

            $totalPending = $baseQuery->count();
            $totalAmount = $baseQuery->sum('total_amount');

            $byCurrency = $baseQuery
                ->select('currency', DB::raw('SUM(total_amount) as total'))
                ->groupBy('currency')
                ->get();

            $bySupplier = Invoice::with('supplier')
                ->whereIn('status', ['loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 'paid');
                })
                ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('supplier_id')
                ->get();

            return ApiResponse::success([
                'total_pending_invoices' => $totalPending,
                'total_amount_pending' => $totalAmount,
                'by_currency' => $byCurrency,
                'by_supplier' => $bySupplier
            ], 'Estadísticas obtenidas exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Subir comprobante de pago
     */
    public function uploadReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:2048'
        ]);

        try {
            $file = $request->file('file');
            $filename = 'payment_receipt_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('payment_receipts', $filename, 'public');

            return ApiResponse::success([
                'url' => Storage::url($path),
                'filename' => $filename
            ], 'Comprobante subido exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al subir el comprobante: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Determinar estado de pago basado en el monto pagado
     */
    private function determinePaymentStatus($invoiceIds, $currentPaymentUSD, $totalAmountUSD): string
    {
        // Obtener el total pagado anteriormente en USD para estas facturas
        $previousPaymentsUSD = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->sum('amount'); // amount ya está en USD según la lógica del controlador

        // Calcular el total pagado incluyendo el pago actual
        $totalPaidUSD = $previousPaymentsUSD + $currentPaymentUSD;

        if ($totalPaidUSD >= $totalAmountUSD) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    /**
     * Crear registro en expenses
     */
    private function createExpense($invoices, $payment, $amountUSD): void
    {
        // Crear o obtener categoría
        $category = ExpenseCategory::firstOrCreate([
            'name' => 'Pagos de Facturas'
        ]);

        // Crear expense
        Expense::create([
            'name' => "Pago Factura # {$invoices->first()->invoice_number} Proveedor {$invoices->first()->supplier->name}",
            'category_id' => $category->id,
            'amount' => $payment->amount,
            'amount_usd' => $amountUSD,
            'currency' => $payment->payment_method, // Ya está normalizada
            'expense_date' => $payment->payment_date,
            'user_id' => $payment->payment_by,
            'has_invoice' => true,
            'is_deductible' => true,
        ]);
    }

    /**
     * Obtener proveedores para el filtro del módulo Por Pagar
     */
    public function getSuppliers(): JsonResponse
    {
        try {
            $suppliers = Supplier::select('id', 'name')
                ->where('is_deleted', 0)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $suppliers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar proveedores',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalizar códigos de moneda para consistencia
     */
    private function normalizeCurrencyCode(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        // Mapeo de códigos inconsistentes a códigos estándar
        $currencyMap = [
            'BS' => 'BS',   // Bolívares venezolanos
            'Bs' => 'BS',   // Bolívares venezolanos (minúscula)
            'VES' => 'BS',  // Mapear VES a BS (como está en la BD)
            'USD' => 'USD', // Dólares americanos
            'COP' => 'COP', // Pesos colombianos
        ];

        return $currencyMap[$normalized] ?? $normalized;
    }
}
