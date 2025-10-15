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
     * Calcular monto en USD usando la tabla exchange_rates
     */
    private function calculateUSD($amount, $currency)
    {
        if ($currency === 'USD') {
            return (float) $amount;
        }

        // Mapear moneda para buscar en exchange_rates
        $currencyCode = $currency === 'Bs' ? 'BS' : $currency;

        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (!$exchangeRate) {
            return 0;
        }

        return round((float) $amount / (float) $exchangeRate->rate, 2);
    }

    /**
     * Obtener facturas pendientes de pago agrupadas por proveedor y fecha
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['supplier'])
                ->whereIn('status', ['pending', 'loaded', 'to_order'])
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                });

            // ORDENAMIENTO FIJO: Ignorar parámetros de ordenamiento del frontend
            // Prioridad: to_order primero, luego pending, ordenados por fecha de pago
            $query->orderByRaw('CASE 
                WHEN status = "to_order" THEN 0 
                WHEN status = "pending" THEN 1 
                ELSE 2 
            END')
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


            // Filtro de facturas vencidas
            if ($request->filled('show_overdue_only') && $request->boolean('show_overdue_only')) {
                $query->where(function ($q) {
                    $q->whereDate('payment_date', '<', Carbon::now())
                        ->orWhereDate('exp_date', '<', Carbon::now());
                });
            } else {
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

            // Agregar total_usd calculado dinámicamente a cada factura
            $invoices->transform(function ($invoice) {
                $invoice->total_amount_usd = $this->calculateUSD($invoice->total_amount, $invoice->currency);
                return $invoice;
            });

            $groupedInvoices = $invoices->groupBy(function ($invoice) {
                return $invoice->supplier_id . '_' . $invoice->payment_date;
            })->map(function ($group) {
                $firstInvoice = $group->first();

                // Calcular total en USD para el grupo usando el nuevo campo calculado
                $totalAmountUSD = $group->sum('total_amount_usd');

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
                        $invoiceRemainingUSD = $invoice->total_amount_usd; // Usar el campo calculado dinámicamente
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

                            $invoiceRemainingUSD = max(0, $invoice->total_amount_usd - $totalPaidUSD);

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

            // Calcular totales por moneda
            $invoicesBs = $invoices->where('currency', 'Bs');
            $invoicesUsd = $invoices->where('currency', 'USD');
            $invoicesCop = $invoices->where('currency', 'COP');

            $totalBs = $invoicesBs->sum('total_amount');
            $totalUsd = $invoicesUsd->sum('total_amount');
            $totalCop = $invoicesCop->sum('total_amount');
            $totalUsdConverted = $invoices->sum('total_amount_usd');

            return ApiResponse::success([
                'pending_payments' => $groupedInvoices,
                'total_groups' => $groupedInvoices->count(),
                'total_suppliers' => $groupedInvoices->unique('supplier_id')->count(),
                'total_amount' => $totalUsdConverted,
                'totals_by_currency' => [
                    'bs' => [
                        'amount' => $totalBs,
                        'count' => $invoicesBs->count()
                    ],
                    'usd' => [
                        'amount' => $totalUsd,
                        'count' => $invoicesUsd->count()
                    ],
                    'cop' => [
                        'amount' => $totalCop,
                        'count' => $invoicesCop->count()
                    ],
                    'usd_converted' => $totalUsdConverted
                ]
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
                ->whereIn('status', ['pending', 'loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
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

        // Validación adicional específica para payment_type
        if (empty($request->payment_type) || !in_array($request->payment_type, ['full', 'partial'])) {
            Log::error('ProcessPayment - Payment Type Missing:', [
                'payment_type_received' => $request->payment_type,
                'all_request_data' => $request->all()
            ]);
            return ApiResponse::error('El tipo de pago es requerido y debe ser "Pago Completo" o "Pago Parcial"', 400);
        }

        try {

            // 1. Verificar que las facturas no estén ya pagadas
            $invoices = Invoice::with(['supplier'])
                ->whereIn('id', $request->invoice_ids)
                ->get();


            foreach ($invoices as $invoice) {
                if ($invoice->status_payment === 1) {
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
                return in_array($invoice->status, ['pending', 'loaded', 'to_order']) &&
                    (is_null($invoice->status_payment) || $invoice->status_payment !== 1);
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

            // Calcular el total de las facturas en USD usando cálculo dinámico
            $totalInvoiceAmount = 0;
            foreach ($invoices as $invoice) {
                $totalInvoiceAmount += $this->calculateUSD($invoice->total_amount, $invoice->currency);
            }

            // 3. Calcular monto en USD según la moneda de pago
            if ($normalizedCurrency === 'USD') {
                // Si el pago es en USD, usar directamente el monto del formulario
                $amountUSD = $request->payment_amount;
            } else {
                // Si el pago es en otra moneda, convertir a USD usando la tasa de cambio
                $amountUSD = round($request->payment_amount / $exchangeRate->rate, 2);
            }

            // LOG TEMPORAL PARA DEBUGGING
            Log::info('ProcessPayment Debug:', [
                'payment_currency' => $request->payment_currency,
                'normalized_currency' => $normalizedCurrency,
                'payment_amount' => $request->payment_amount,
                'amountUSD' => $amountUSD,
                'totalInvoiceAmount' => $totalInvoiceAmount,
                'payment_type' => $request->payment_type,
                'exchange_rate' => $exchangeRate->rate ?? 'N/A'
            ]);

            // 4. Validaciones específicas según el tipo de pago
            if ($request->payment_type === 'partial') {
                // PAGO PARCIAL: Validar que el monto sea menor o igual al total
                // Permitir el monto completo en el primer pago parcial
                if ($amountUSD > $totalInvoiceAmount) {
                    return ApiResponse::error(
                        'El monto no puede exceder el total de la factura (máximo: USD ' . number_format($totalInvoiceAmount, 2) . ')',
                        400
                    );
                }

                // Validar que el monto sea mayor a 0.01
                if ($amountUSD < 0.01) {
                    return ApiResponse::error(
                        'El monto mínimo para un pago parcial es USD 0.01',
                        400
                    );
                }
            } else {
                // PAGO COMPLETO: Validar que el monto sea exactamente igual al total
                $tolerance = 0.01; // Tolerancia de 1 centavo para diferencias de redondeo

                if (abs($amountUSD - $totalInvoiceAmount) > $tolerance) {
                    return ApiResponse::error(
                        'Para un pago completo, el monto debe ser exactamente igual al total de la factura. Monto recibido: USD ' . number_format($amountUSD, 2) . ', Total requerido: USD ' . number_format($totalInvoiceAmount, 2),
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
            $newStatus = $paymentStatus === 1 ? 'ordered' : 'to_order';

            Invoice::whereIn('id', $request->invoice_ids)->update([
                'status' => $newStatus,
                'status_payment' => $paymentStatus,
                'payment_date' => $request->payment_date,
                'updated_at' => now(),
            ]);

            // 7. Crear expense
            $this->createExpense($invoices, $payment, $amountUSD, false);

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
                $totalInvoiceAmount = 0;
                foreach ($payment->invoices as $invoice) {
                    $totalInvoiceAmount += $this->calculateUSD($invoice->total_amount, $invoice->currency);
                }
                $payment->payment_type = $payment->amount_usd >= $totalInvoiceAmount ? 'full' : 'partial';

                // Agregar el total de la factura en USD
                $payment->invoice_total_usd = $totalInvoiceAmount;

                // Agregar total_amount_usd a cada factura individual
                $payment->invoices->transform(function ($invoice) {
                    $invoice->total_amount_usd = $this->calculateUSD($invoice->total_amount, $invoice->currency);
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
            $baseQuery = Invoice::whereIn('status', ['pending', 'loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                });

            $totalPending = $baseQuery->count();
            $totalAmount = $baseQuery->sum('total_amount');

            // Obtener facturas con cálculo dinámico de USD
            $invoices = $baseQuery->get();
            $invoices->transform(function ($invoice) {
                $invoice->total_amount_usd = $this->calculateUSD($invoice->total_amount, $invoice->currency);
                return $invoice;
            });

            // Calcular totales por moneda con USD dinámico
            $byCurrency = $invoices->groupBy('currency')->map(function ($group, $currency) {
                return [
                    'currency' => $currency,
                    'total' => $group->sum('total_amount'),
                    'total_usd' => $group->sum('total_amount_usd'),
                    'count' => $group->count()
                ];
            })->values();

            $bySupplier = Invoice::with('supplier')
                ->whereIn('status', ['pending', 'loaded', 'to_order'])
                ->whereNotNull('payment_date')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })
                ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('supplier_id')
                ->get();

            // Calcular facturas vencidas
            $overdueInvoices = Invoice::whereIn('status', ['pending', 'loaded', 'to_order'])
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })
                ->where(function ($q) {
                    $q->whereDate('payment_date', '<', Carbon::now())
                        ->orWhereDate('exp_date', '<', Carbon::now());
                })
                ->count();

            // Calcular totales por moneda para el frontend
            $totalsByCurrency = [
                'bs' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
                'usd' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
                'cop' => ['amount' => 0, 'count' => 0, 'total_usd' => 0],
                'usd_converted' => 0
            ];

            foreach ($invoices as $invoice) {
                $currency = strtolower($invoice->currency);
                if (isset($totalsByCurrency[$currency])) {
                    $totalsByCurrency[$currency]['amount'] += $invoice->total_amount;
                    $totalsByCurrency[$currency]['count']++;
                    $totalsByCurrency[$currency]['total_usd'] += $invoice->total_amount_usd;
                }
                $totalsByCurrency['usd_converted'] += $invoice->total_amount_usd;
            }

            return ApiResponse::success([
                'total_pending_invoices' => $totalPending,
                'total_amount_pending' => $totalAmount,
                'overdue_invoices' => $overdueInvoices,
                'by_currency' => $byCurrency,
                'by_supplier' => $bySupplier,
                'totals_by_currency' => $totalsByCurrency
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


        if ($totalPaidUSD >= $totalAmountUSD) {
            return 1; // paid
        } else {
            return 0; // pending
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
            'iva' => $iva ?? false
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

            // Obtener el total de las facturas en USD usando cálculo dinámico
            $invoices = Invoice::whereIn('id', $invoiceIds)->get();
            $totalInvoiceUSD = 0;
            foreach ($invoices as $invoice) {
                $totalInvoiceUSD += $this->calculateUSD($invoice->total_amount, $invoice->currency);
            }

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

            // Calcular 16% del amount_bs para cada gasto con IVA (en bolívares)
            $creditoFiscal = 0;
            foreach ($expensesWithIva as $expense) {
                $creditoFiscal += $expense->amount_bs * 0.16;
            }

            return ApiResponse::success([
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'credito_fiscal' => round($creditoFiscal, 2),
                'detalle_credito' => [
                    'total_expenses_with_iva' => $expensesWithIva->count(),
                    'total_amount_expenses' => $expensesWithIva->sum('amount_bs'),
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
                // Calcular el IVA (16% del amount_bs en lugar de amount_usd)
                $ivaAmount = $expense->amount_bs * 0.16;

                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'category_name' => $expense->category->name ?? 'Sin categoría',
                    'amount_bs' => (float) $expense->amount_bs,
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

            // Calcular totales para la página actual usando amount_bs
            $pageTotals = [
                'total_amount' => $formattedRecords->sum('amount_bs'),
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
