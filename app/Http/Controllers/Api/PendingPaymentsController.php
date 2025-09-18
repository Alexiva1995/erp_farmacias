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

            $groupedInvoices = $invoices->groupBy(function ($invoice) {
                return $invoice->supplier_id . '_' . $invoice->payment_date;
            })->map(function ($group) {
                $firstInvoice = $group->first();

                // Calcular total en USD para el grupo
                $totalAmountUSD = $group->sum('total_usd');

                // Calcular total en la moneda original del grupo
                $totalAmountOriginal = $group->sum('total_amount');

                // Calcular monto restante para facturas con pagos parciales
                $remainingAmountUSD = $totalAmountUSD;
                $remainingAmountOriginal = $totalAmountOriginal;

                // Verificar si hay pagos parciales para este grupo
                $invoiceIds = $group->pluck('id');
                $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
                    $query->whereIn('id', $invoiceIds);
                })->get();

                if ($payments->count() > 0) {
                    // Calcular total pagado en USD
                    $totalPaidUSD = 0;
                    foreach ($payments as $payment) {
                        if ($payment->payment_method === 'USD') {
                            $totalPaidUSD += $payment->amount;
                        } else {
                            $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                            if ($exchangeRate) {
                                $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                            }
                        }
                    }

                    // Calcular monto restante
                    $remainingAmountUSD = max(0, $totalAmountUSD - $totalPaidUSD);

                    // Convertir monto restante a moneda original
                    if ($firstInvoice->currency === 'Bs') {
                        $exchangeRate = ExchangeRate::where('currency_code', 'VES')->first();
                        if ($exchangeRate) {
                            $remainingAmountOriginal = round($remainingAmountUSD * $exchangeRate->rate, 2);
                        }
                    } elseif ($firstInvoice->currency === 'COP') {
                        $exchangeRate = ExchangeRate::where('currency_code', 'COP')->first();
                        if ($exchangeRate) {
                            $remainingAmountOriginal = round($remainingAmountUSD * $exchangeRate->rate, 2);
                        }
                    } else {
                        $remainingAmountOriginal = $remainingAmountUSD;
                    }
                }

                return [
                    'supplier_id' => $firstInvoice->supplier_id,
                    'supplier_name' => $firstInvoice->supplier->name,
                    'payment_date' => $firstInvoice->payment_date,
                    'currency' => $firstInvoice->currency,
                    'total_amount' => $remainingAmountOriginal, // Monto restante en moneda original
                    'total_amount_usd' => $remainingAmountUSD, // Monto restante en USD
                    'remainingAmountUSD' => $remainingAmountUSD, // Alias para compatibilidad
                    'invoice_count' => $group->count(),
                    'invoices' => $group->map(function ($invoice) use ($remainingAmountOriginal, $remainingAmountUSD, $totalAmountOriginal, $totalAmountUSD) {
                        // Calcular monto restante individual para esta factura
                        $invoiceRemainingUSD = $invoice->total_usd;
                        $invoiceRemainingOriginal = $invoice->total_amount;

                        // Si hay pagos parciales, calcular el monto restante individual
                        $invoicePayments = InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
                            $query->where('id', $invoice->id);
                        })->get();

                        if ($invoicePayments->count() > 0) {
                            $totalPaidUSD = 0;
                            foreach ($invoicePayments as $payment) {
                                if ($payment->payment_method === 'USD') {
                                    $totalPaidUSD += $payment->amount;
                                } else {
                                    $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                                    if ($exchangeRate) {
                                        $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                                    }
                                }
                            }

                            $invoiceRemainingUSD = max(0, $invoice->total_usd - $totalPaidUSD);

                            // Convertir a moneda original
                            if ($invoice->currency === 'Bs') {
                                $exchangeRate = ExchangeRate::where('currency_code', 'VES')->first();
                                if ($exchangeRate) {
                                    $invoiceRemainingOriginal = round($invoiceRemainingUSD * $exchangeRate->rate, 2);
                                }
                            } elseif ($invoice->currency === 'COP') {
                                $exchangeRate = ExchangeRate::where('currency_code', 'COP')->first();
                                if ($exchangeRate) {
                                    $invoiceRemainingOriginal = round($invoiceRemainingUSD * $exchangeRate->rate, 2);
                                }
                            } else {
                                $invoiceRemainingOriginal = $invoiceRemainingUSD;
                            }
                        }

                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => $invoiceRemainingOriginal, // Monto restante en moneda original
                            'total_amount_usd' => $invoiceRemainingUSD, // Monto restante en USD
                            'invoiceRemainingUSD' => $invoiceRemainingUSD, // Alias para compatibilidad
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
                'payment_type' => 'required|in:full,partial', // Nuevo campo
                'payment_currency' => 'required|in:VES,USD,COP',
                'payment_amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'reference' => 'nullable|string|max:100',
                'photo_url' => 'nullable|string',
                'notes' => 'nullable|string|max:500',
                'has_iva' => 'nullable|boolean',
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
                'payment_date' => $request->payment_date,
                'reference' => $request->reference,
                'all_data' => $request->all()
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

            // Validación específica para pagos parciales
            if ($request->payment_type === 'partial') {
                // Para pagos parciales, el monto debe ser menor al total
                if ($amountUSD >= $totalInvoiceAmount) {
                    return ApiResponse::error(
                        'Para un pago parcial, el monto debe ser menor al total de la factura',
                        400
                    );
                }
            } else {
                // Para pagos completos, validar que el monto sea razonable (entre 95% y 110% del total)
                $minAmount = $totalInvoiceAmount * 0.95; // 95% del total
                $maxAmount = $totalInvoiceAmount * 1.10; // 110% del total (permite 10% de sobrepago)

                if ($amountUSD < $minAmount) {
                    return ApiResponse::error(
                        "Para un pago completo, el monto debe ser al menos el 95% del total de la factura (mínimo: USD " . number_format($minAmount, 2) . ")",
                        400
                    );
                }

                if ($amountUSD > $maxAmount) {
                    return ApiResponse::error(
                        "El monto excede el 110% del total de la factura (máximo: USD " . number_format($maxAmount, 2) . "). Verifique el monto o considere un pago parcial.",
                        400
                    );
                }
            }

            // 4. Crear registro en invoice_payments usando campos existentes
            // El campo reference debe usarse solo para referencias bancarias/transferencias
            $reference = $request->reference ?? null;

            $payment = InvoicePayment::create([
                'payment_date' => $request->payment_date,
                'amount' => $request->payment_amount,
                'payment_method' => $normalizedCurrency, // Usar moneda normalizada
                'reference' => $reference, // Referencia bancaria/transferencia
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

            // 6. Determinar estado de pago considerando solo pagos anteriores
            $paymentStatus = $this->determinePaymentStatusCorrected($request->invoice_ids, $amountUSD, $totalInvoiceAmount);

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
            $this->createExpense($invoices, $payment, $amountUSD, $request->has_iva);

            DB::commit();

            return ApiResponse::success([
                'payment_id' => $payment->id,
                'processed_invoices' => $request->invoice_ids,
                'payment_type' => $request->payment_type, // Nuevo campo
                'amount_paid' => $request->payment_amount,
                'currency' => $request->payment_currency,
                'amount_usd' => $amountUSD,
                'exchange_rate' => $exchangeRate->rate,
                'payment_status' => $paymentStatus,
                'total_invoice_amount' => $totalInvoiceAmount,
                'remaining_amount' => $totalInvoiceAmount - $amountUSD // Monto restante
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

                // Calcular el equivalente en USD usando la tasa de cambio
                if ($payment->payment_method === 'USD') {
                    $payment->amount_usd = $payment->amount;
                } else {
                    // Para otras monedas, necesitamos obtener la tasa de cambio
                    $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                    if ($exchangeRate) {
                        $payment->amount_usd = round($payment->amount / $exchangeRate->rate, 2);
                    } else {
                        $payment->amount_usd = 0;
                    }
                }

                // Determinar si es pago completo o parcial basado en el monto
                $totalInvoiceAmount = $payment->invoices->sum('total_usd');
                $payment->payment_type = $payment->amount_usd >= $totalInvoiceAmount ? 'full' : 'partial';

                // Agregar el total de la factura en USD
                $payment->invoice_total_usd = $totalInvoiceAmount;

                // Agregar total_amount_usd a cada factura individual
                $payment->invoices->transform(function ($invoice) {
                    $invoice->total_amount_usd = $invoice->total_usd;
                    return $invoice;
                });

                // Calcular información de pagos para facturas con pagos parciales
                if ($payment->payment_type === 'partial') {
                    $invoiceIds = $payment->invoices->pluck('id');

                    // Obtener todos los pagos para estas facturas
                    $allPayments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
                        $query->whereIn('id', $invoiceIds);
                    })->get();

                    $totalPaidUSD = 0;
                    foreach ($allPayments as $p) {
                        if ($p->payment_method === 'USD') {
                            $totalPaidUSD += $p->amount;
                        } else {
                            $exchangeRate = ExchangeRate::where('currency_code', $p->payment_method)->first();
                            if ($exchangeRate) {
                                $totalPaidUSD += round($p->amount / $exchangeRate->rate, 2);
                            }
                        }
                    }

                    $payment->total_paid_usd = $totalPaidUSD;
                    $payment->remaining_amount_usd = max(0, $totalInvoiceAmount - $totalPaidUSD);
                    $payment->payment_percentage = $totalInvoiceAmount > 0 ? round(($totalPaidUSD / $totalInvoiceAmount) * 100, 2) : 0;
                }

                // El campo reference ya está disponible directamente

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
        // (excluyendo el pago actual que aún no se ha registrado)
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->get();

        $previousPaymentsUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $previousPaymentsUSD += $payment->amount;
            } else {
                // Para otras monedas, obtener la tasa de cambio y convertir a USD
                $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                if ($exchangeRate) {
                    $previousPaymentsUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Calcular el total pagado incluyendo el pago actual
        $totalPaidUSD = $previousPaymentsUSD + $currentPaymentUSD;

        // Log para debugging
        Log::info('Determinando estado de pago:', [
            'invoice_ids' => $invoiceIds,
            'previous_payments_usd' => $previousPaymentsUSD,
            'current_payment_usd' => $currentPaymentUSD,
            'total_paid_usd' => $totalPaidUSD,
            'total_amount_usd' => $totalAmountUSD,
            'will_be_paid' => $totalPaidUSD >= $totalAmountUSD
        ]);

        if ($totalPaidUSD >= $totalAmountUSD) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    /**
     * Determinar estado de pago considerando solo pagos anteriores (excluyendo el actual)
     */
    private function determinePaymentStatusCorrected($invoiceIds, $currentPaymentUSD, $totalAmountUSD): string
    {
        // Obtener el total pagado anteriormente en USD para estas facturas
        // (excluyendo el pago actual que aún no se ha registrado)
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })->get();

        $previousPaymentsUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $previousPaymentsUSD += $payment->amount;
            } else {
                // Para otras monedas, obtener la tasa de cambio y convertir a USD
                $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                if ($exchangeRate) {
                    $previousPaymentsUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Calcular el total pagado incluyendo el pago actual
        $totalPaidUSD = $previousPaymentsUSD + $currentPaymentUSD;

        // Log para debugging
        Log::info('Determinando estado de pago (corregido):', [
            'invoice_ids' => $invoiceIds,
            'previous_payments_usd' => $previousPaymentsUSD,
            'current_payment_usd' => $currentPaymentUSD,
            'total_paid_usd' => $totalPaidUSD,
            'total_amount_usd' => $totalAmountUSD,
            'will_be_paid' => $totalPaidUSD >= $totalAmountUSD
        ]);

        if ($totalPaidUSD >= $totalAmountUSD) {
            return 'paid';
        } else {
            return 'partial';
        }
    }

    /**
     * Crear registro en expenses
     */
    private function createExpense($invoices, $payment, $amountUSD, $iva): void
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
            'currency' => $payment->payment_method,
            'expense_date' => $payment->payment_date,
            'user_id' => $payment->payment_by,
            'has_invoice' => true,
            'is_deductible' => true,
            'iva' => $iva
        ]);
    }

    /**
     * Obtener monto ya pagado de facturas específicas
     */
    public function getPaidAmount(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'invoice_ids' => 'required|array',
                'invoice_ids.*' => 'exists:invoices,id'
            ]);

            $invoiceIds = $request->invoice_ids;

            // Obtener el total ya pagado en USD para estas facturas
            $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
                $query->whereIn('id', $invoiceIds);
            })->get();

            $totalPaidUSD = 0;
            foreach ($payments as $payment) {
                if ($payment->payment_method === 'USD') {
                    $totalPaidUSD += $payment->amount;
                } else {
                    // Para otras monedas, obtener la tasa de cambio y convertir a USD
                    $exchangeRate = ExchangeRate::where('currency_code', $payment->payment_method)->first();
                    if ($exchangeRate) {
                        $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                    }
                }
            }

            // Obtener el total de las facturas en USD
            $totalInvoiceUSD = Invoice::whereIn('id', $invoiceIds)->sum('total_usd');

            // Calcular monto restante
            $remainingAmount = max(0, $totalInvoiceUSD - $totalPaidUSD);

            // Determinar si hay pagos previos
            $hasPreviousPayments = $totalPaidUSD > 0;

            // Determinar el estado de pago
            $paymentStatus = 'unpaid';
            if ($totalPaidUSD >= $totalInvoiceUSD) {
                $paymentStatus = 'paid';
            } elseif ($totalPaidUSD > 0) {
                $paymentStatus = 'partial';
            }

            return ApiResponse::success([
                'total_invoice_usd' => $totalInvoiceUSD,
                'total_paid_usd' => $totalPaidUSD,
                'remaining_amount' => $remainingAmount,
                'has_previous_payments' => $hasPreviousPayments,
                'payment_status' => $paymentStatus,
                'payment_percentage' => $totalInvoiceUSD > 0 ? round(($totalPaidUSD / $totalInvoiceUSD) * 100, 2) : 0
            ], 'Información de pagos obtenida exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener información de pagos: ' . $e->getMessage(), 500);
        }
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

        $currencyMap = [
            'BS' => 'BS',
            'Bs' => 'BS',
            'VES' => 'BS',
            'USD' => 'USD',
            'COP' => 'COP',
        ];

        return $currencyMap[$normalized] ?? $normalized;
    }
    public function getCreditoFiscal(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->endOfMonth()->format('Y-m-d');

            // CRÉDITO FISCAL: Gastos con IVA (campo iva = 1)
            $expensesWithIva = Expense::where('iva', 1)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->get();

            // Calcular 16% del amount_usd para cada gasto con IVA
            $creditoFiscal = 0;
            foreach ($expensesWithIva as $expense) {
                $creditoFiscal += $expense->amount_usd * 0.16;
            }

            return ApiResponse::success([
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'credito_fiscal' => round($creditoFiscal, 2),
                'detalle_credito' => [
                    'total_expenses_with_iva' => $expensesWithIva->count(),
                    'total_amount_expenses' => $expensesWithIva->sum('amount_usd'),
                    'iva_calculated' => round($creditoFiscal, 2)
                ]
            ], 'Crédito fiscal obtenido exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al calcular crédito fiscal: ' . $e->getMessage());
            return ApiResponse::error('Error al calcular crédito fiscal: ' . $e->getMessage(), 500);
        }
    }
    public function getExpensesHistory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'page' => 'integer|min:1',
                'itemsPerPage' => 'integer|min:1|max:100'
            ]);

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->endOfMonth()->format('Y-m-d');
            $page = $request->page ?? 1;
            $itemsPerPage = $request->itemsPerPage ?? 10;

            // Query para gastos con IVA
            $query = Expense::with(['category'])
                ->where('iva', 1)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->orderBy('expense_date', 'desc')
                ->orderBy('id', 'desc');

            // Clonar query para el conteo total
            $totalQuery = clone $query;
            $totalRecords = $totalQuery->count();

            // Aplicar paginación
            $offset = ($page - 1) * $itemsPerPage;
            $records = $query
                ->skip($offset)
                ->take($itemsPerPage)
                ->get();

            // Formatear los registros para el frontend
            $formattedRecords = $records->map(function ($expense) {
                // Calcular el IVA (16% del amount_usd)
                $ivaAmount = $expense->amount_usd * 0.16;

                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'category_name' => $expense->category->name ?? 'Sin categoría',
                    'amount_usd' => (float) $expense->amount_usd,
                    'currency' => $expense->currency,
                    'expense_date' => $expense->expense_date,
                    'is_deductible' => (bool) $expense->is_deductible,
                    'has_invoice' => (bool) $expense->has_invoice,
                    'iva_amount' => round($ivaAmount, 2),
                    'exempt_amount' => 0, // Los gastos generalmente no tienen exención

                    // Campos para la tabla (algunos pueden ser null)
                    'supplier_name' => $expense->supplier_name ?? $expense->name,
                    'supplier_rif' => $expense->supplier_rif ?? null,
                    'supplier_business_name' => $expense->supplier_business_name ?? $expense->name,
                    'invoice_number' => $expense->invoice_number ?? 'N/A',

                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at
                ];
            });

            // Calcular totales para la página actual
            $pageTotals = [
                'total_amount' => $formattedRecords->sum('amount_usd'),
                'total_iva' => $formattedRecords->sum('iva_amount'),
                'total_expenses' => $formattedRecords->count()
            ];

            Log::info('Registros de gastos con IVA obtenidos:', [
                'periodo' => [$startDate, $endDate],
                'page' => $page,
                'items_per_page' => $itemsPerPage,
                'total_records' => $totalRecords,
                'records_in_page' => $formattedRecords->count(),
                'page_totals' => $pageTotals
            ]);

            return ApiResponse::success([
                'data' => $formattedRecords->toArray(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $itemsPerPage,
                    'total' => $totalRecords,
                    'last_page' => ceil($totalRecords / $itemsPerPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $itemsPerPage, $totalRecords)
                ],
                'totals' => $pageTotals,
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ], 'Registros de gastos con IVA obtenidos exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener registros de gastos: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener registros de gastos: ' . $e->getMessage(), 500);
        }
    }
}
