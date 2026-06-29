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
use App\Models\Transaction;
use App\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

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
     * Calcular monto en BS usando la tabla exchange_rates
     */
    private function calculateBS($amount, $currency)
    {
        if ($currency === 'Bs') {
            return (float) $amount;
        }

        $exchangeRate = ExchangeRate::where('currency_code', $currency)->first();

        if (!$exchangeRate) {
            return 0;
        }

        return round((float) $amount / (float) $exchangeRate->rate, 2);
    }

    private function getBsExchange($currency)
    {
        $exchangeRate = ExchangeRate::where('currency_code', $currency)->first();

        if (!$exchangeRate) {
            return 0;
        }

        return round((float) $exchangeRate->rate, 2);
    }

    /**
     * Obtener facturas pendientes de pago agrupadas por proveedor y fecha
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Ampliar consulta: Todas las facturas no pagadas (status_payment != 1 o nulo)
            // Independientemente de su status (pending, loaded, to_order, ordered, etc.)
            $query = Invoice::with(['supplier'])
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                });

            // Filtrar desde el año en curso (2026) en adelante por defecto
            $currentYearStart = Carbon::now()->startOfYear();
            $query->whereDate('payment_date', '>=', $currentYearStart);

            // ORDENAMIENTO: Prioridad por fecha de pago
            $query->orderBy('payment_date', 'asc');

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('payment_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('payment_date', '<=', $request->end_date);
            }

            if ($request->filled('q')) {
                $search = $request->input('q');
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Filtro de facturas vencidas
            // CORRECCIÓN ISSUE #1: Fecha de vencimiento es payment_date - 1 día
            if ($request->filled('show_overdue_only') && $request->boolean('show_overdue_only')) {
                $query->where(function ($q) {
                    // Fecha de vencimiento = payment_date - 1 día
                    $dueDate = Carbon::now()->subDay();
                    $q->whereDate('payment_date', '<=', $dueDate)
                        ->orWhereDate('exp_date', '<', Carbon::now());
                });
            } else {
            }

            $invoices = $query->get();

            // Log removido para pruebas

            // CORRECCIÓN CRÍTICA: No recalcular total_usd, usar el de la BD
            // $invoices ya tiene el total_usd correcto desde la base de datos

            $groupedInvoices = $invoices->groupBy(function ($invoice) {
                return $invoice->supplier_id . '_' . $invoice->payment_date;
            })->map(function ($group) {
                $firstInvoice = $group->first();

                // Calcular total en USD para el grupo usando el nuevo campo calculado
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

                $totalPaidUSD = 0;
                if ($payments->count() > 0) {
                    // Calcular total pagado en USD
                    foreach ($payments as $payment) {
                        if ($payment->payment_method === 'USD') {
                            $totalPaidUSD += $payment->amount;
                        } else {
                            $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                            $exchangeRate = ExchangeRate::where('currency_code', $rateCurrency)->first();
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

                // ISSUE #4: Calcular total en moneda del proveedor
                $supplierPreferredCurrency = $this->getSupplierPreferredCurrency($firstInvoice->supplier);
                $totalInSupplierCurrency = $this->calculateTotalInSupplierCurrency($group, $supplierPreferredCurrency);

                return [
                    'supplier_id' => $firstInvoice->supplier_id,
                    'supplier_name' => $firstInvoice->supplier->name,
                    'payment_date' => $firstInvoice->payment_date,
                    'currency' => $firstInvoice->currency,
                    'total_amount' => $remainingAmountOriginal, // Monto restante en moneda original
                    'total_usd' => $totalAmountUSD, // CORRECCIÓN: Usar total original, no restante
                    'remainingAmountUSD' => $remainingAmountUSD, // Alias para compatibilidad
                    'total_in_supplier_currency' => $totalInSupplierCurrency, // ISSUE #4: Total en moneda del proveedor
                    'supplier_preferred_currency' => $supplierPreferredCurrency, // ISSUE #4: Moneda preferida del proveedor
                    'invoice_count' => $group->count(),
                    'invoices' => $group->map(function ($invoice) use ($totalInSupplierCurrency, $totalAmountUSD) {
                        // ISSUE #3: Calcular montos indexados si aplica
                        $indexedData = $this->calculateIndexedAmount($invoice);

                        // CORRECCIÓN CRÍTICA: Para facturas indexadas, mantener USD fijo y calcular Bs dinámicamente
                        if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                            // Para facturas indexadas: USD siempre fijo, Bs se calcula dinámicamente
                            $invoiceRemainingUSD = $invoice->total_usd; // USD fijo
    
                            // Calcular Bs usando la tasa BCV actual
                            $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
                            if ($bcvRate) {
                                $invoiceRemainingOriginal = round($invoice->total_usd * $bcvRate->rate, 2);
                            } else {
                                $invoiceRemainingOriginal = $invoice->total_amount; // Fallback
                            }
                        } else {
                            // Para facturas no indexadas: cálculo normal
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
                                        $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                                        $exchangeRate = ExchangeRate::where('currency_code', $rateCurrency)->first();
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
                        }

                        // ISSUE #3: Si la factura está indexada, usar el monto indexado para mostrar
                        $displayAmount = $indexedData['is_indexed'] ? $indexedData['indexed_amount'] : $invoiceRemainingOriginal;
                        $displayOriginalAmount = $indexedData['is_indexed'] ? $indexedData['indexed_amount'] : $invoice->total_amount;

                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'total_amount' => $displayAmount, // Monto restante (indexado si aplica)
                            'total_usd' => $invoice->total_usd, // CORRECCIÓN: Mantener USD original fijo
                            'invoiceRemainingUSD' => $invoiceRemainingUSD, // Alias para compatibilidad
                            'remaining_amount' => $this->calculateRemainingAmountForInvoice($invoice), // Monto restante real
                            'remaining_amount_usd' => $this->calculateRemainingAmountUSDForInvoice($invoice), // Monto restante USD real
                            'original_amount' => $displayOriginalAmount, // Monto original (indexado si aplica)
                            'original_amount_usd' => $invoice->total_usd, // Monto original en USD
                            'currency' => $invoice->currency,
                            'is_indexed' => $invoice->is_indexed ?? false, // ISSUE #3: Campo para facturas indexadas
                            'indexed_data' => $indexedData, // ISSUE #3: Datos completos de indexación
                            'exchange_rate' => $invoice->exchange_rate,
                            'exp_date' => $invoice->exp_date,
                            'supplier_total_bs' => $totalInSupplierCurrency,
                            'supplier_total_usd' => $totalAmountUSD
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
            $totalUsdConverted = $invoices->sum('total_usd');

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
            \Log::info('Pending payments', [$e->getMessage()]);
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
            $allowedMethods = match ($request->payment_currency) {
                'USD' => ['CASH', 'BINANCE', 'PAYPAL', 'CREDIT'],
                'VES', 'BS' => ['CASH', 'CARD', 'MOBILE', 'TRANSFER'],
                'COP' => ['CASH', 'TRANSFER'],
                default => [],
            };

            $request->validate([
                'invoice_ids' => 'required|array',
                'invoice_ids.*' => 'exists:invoices,id',
                'payment_type' => 'required|in:full,partial', // Nuevo campo
                'payment_currency' => 'required|in:VES,USD,COP',
                'payment_amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'payment_method' => ['required', Rule::in($allowedMethods)],
                'reference' => 'nullable|string|max:100',
                'photo_url' => 'nullable|string',
                'notes' => 'nullable|string|max:500',
                'has_iva' => 'nullable|boolean',
            ], [
                'payment_method.required' => 'El método de pago es necesario',
                'payment_method.in' => 'El método de pago seleccionado no es válido para la moneda seleccionada',
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

            // 2. Verificar duplicados (versión mejorada para pagos parciales)
            // CORRECCIÓN: Para pagos parciales, ser más permisivo
            if ($request->payment_type === 'partial') {
                // Solo bloquear si tiene la misma referencia Y es exactamente el mismo pago
                if (!empty($request->reference)) {
                    $duplicatePayment = InvoicePayment::where('amount', $request->payment_amount)
                        ->where('payment_date', $request->payment_date)
                        ->where('payment_method', $request->payment_currency)
                        ->where('reference', $request->reference)
                        ->whereHas('invoices', function ($query) use ($request) {
                            $query->whereIn('id', $request->invoice_ids);
                        })
                        ->first();

                    if ($duplicatePayment) {
                        return ApiResponse::error(
                            'Ya existe un pago idéntico registrado para estas facturas con la misma referencia. Por favor, usa una referencia diferente.',
                            400
                        );
                    }
                }
                // Si no hay referencia, permitir el pago (pagos parciales legítimos)
            } else {
                // Para pagos completos, mantener validación estricta
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
            }

            DB::beginTransaction();

            // 3. Filtrar facturas válidas para pago (cualquiera no pagada completamente)
            $invoices = $invoices->filter(function ($invoice) {
                return (is_null($invoice->status_payment) || $invoice->status_payment !== 1);
            });

            if ($invoices->isEmpty()) {
                return ApiResponse::error('No se encontraron facturas válidas para procesar', 404);
            }

            // 2. Normalizar código de moneda y obtener tasa de cambio
            $normalizedCurrency = $this->normalizeCurrencyCode($request->payment_currency);
            $rateCurrency = ($normalizedCurrency === 'COP') ? 'COPC' : $normalizedCurrency;
            $exchangeRate = ExchangeRate::where('currency_code', $rateCurrency)->first();
            if (!$exchangeRate && $normalizedCurrency !== 'USD') {
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



            // CORRECCIÓN ISSUE #2: Validaciones flexibles para montos
            // El usuario puede pagar cualquier monto (más o menos que el total)
            // Solo validamos que sea un monto positivo válido

            if ($amountUSD <= 0) {
                return ApiResponse::error(
                    'El monto debe ser mayor a 0',
                    400
                );
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
                'payment_by' => auth()->id(),
                'photo_url' => $request->photo_url,
                'method' => $request->payment_method
            ]);

            // 5. Crear relaciones en tabla pivot
            foreach ($request->invoice_ids as $invoiceId) {
                DB::table('invoice_payment_invoice')->insert([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                ]);
            }

            // 6. Determinar estado de pago (Respetar la decisión del usuario en el checkbox)
            if ($request->payment_type === 'full') {
                $paymentStatus = 1; // Pago Completo: se liquida la factura
            } else {
                $paymentStatus = $this->determinePaymentStatusCorrected($request->invoice_ids, $amountUSD, $totalInvoiceAmount);
            }

            // Preparar datos de actualización
            $updateData = [
                'status' => 'ordered', // Mantener status compatible con query Por Pagar
                'status_payment' => $paymentStatus,
                'updated_at' => now(),
            ];

            // SOLO actualizar la fecha de pago de la factura si el pago es COMPLETO
            if ($paymentStatus === 1) {
                $updateData['payment_date'] = $request->payment_date;
            }

            Invoice::whereIn('id', $request->invoice_ids)->update($updateData);

            // 7. Crear expense
            $this->createExpense($invoices, $payment, false);

            // 8. Guardar pago para mostrar en cierre de caja (truncando descripción para evitar error de longitud en la BD)
            Transaction::create([
                'user_id' => auth()->id(),
                'category_id' => ExpenseCategory::firstOrCreate(['name' => 'Pagos de Facturas'])->id,
                'exchange_rate' => $exchangeRate->rate ?? 1,
                'description' => substr("Pago factura(s) # {$invoices->pluck('invoice_number')->join(', ')} {$invoices->first()->supplier->name}", 0, 1000),
                'currency' => $normalizedCurrency,
                'type' => $payment->method,
                'amount' => $request->payment_amount,
                'movement_type' => 'OUT',
                'transaction_date' => $request->payment_date,
            ]);

            DB::commit();

            return ApiResponse::success([
                'payment_id' => $payment->id,
                'processed_invoices' => $request->invoice_ids,
                'payment_type' => $request->payment_type, // Nuevo campo
                'amount_paid' => $request->payment_amount,
                'currency' => $request->payment_currency,
                'amount_usd' => $amountUSD,
                'exchange_rate' => $exchangeRate->rate ?? 1,
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
                $query->where(function ($groupedQuery) use ($search) {
                    $groupedQuery->whereHas('invoices', function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('control_number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                                $supplierQuery->where('name', 'like', "%{$search}%");
                            });
                    })
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->input('itemsPerPage', 15);
            if ($perPage === -1) {
                $perPage = $query->count() ?: 1;
            }

            $payments = $query->paginate($perPage);

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
                    // CORRECCIÓN CRÍTICA: Usar total_usd de la BD en lugar de recalcular
                    $totalInvoiceAmount += $invoice->total_usd;
                }
                $payment->payment_type = $payment->amount_usd >= $totalInvoiceAmount ? 'full' : 'partial';

                // Agregar el total de la factura en USD
                $payment->invoice_total_usd = $totalInvoiceAmount;

                // CORRECCIÓN CRÍTICA: No recalcular total_usd, usar el de la BD
                $payment->invoices->transform(function ($invoice) {
                    // Mantener el total_usd original de la base de datos
                    // $invoice->total_usd ya está correcto desde la BD
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
            $baseQuery = Invoice::where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })
                ->whereDate('payment_date', '>=', Carbon::now()->startOfYear());

            $totalPending = $baseQuery->count();
            $totalAmount = $baseQuery->sum('total_amount');

            // Obtener facturas con cálculo dinámico de USD
            $invoices = $baseQuery->get();
            $invoices->transform(function ($invoice) {
                $invoice->total_usd = $this->calculateUSD($invoice->total_amount, $invoice->currency);
                return $invoice;
            });

            // Calcular totales por moneda con USD dinámico
            $byCurrency = $invoices->groupBy('currency')->map(function ($group, $currency) {
                return [
                    'currency' => $currency,
                    'total' => $group->sum('total_amount'),
                    'total_usd' => $group->sum('total_usd'),
                    'count' => $group->count()
                ];
            })->values();

            $bySupplier = Invoice::with('supplier')
                ->where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })
                ->whereDate('payment_date', '>=', Carbon::now()->startOfYear())
                ->select('supplier_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('supplier_id')
                ->get();

            // Calcular facturas vencidas
            $overdueInvoices = Invoice::where(function ($q) {
                    $q->whereNull('status_payment')
                        ->orWhere('status_payment', '!=', 1);
                })
                ->whereDate('payment_date', '>=', Carbon::now()->startOfYear())
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
                    $totalsByCurrency[$currency]['total_usd'] += $invoice->total_usd;
                }
                $totalsByCurrency['usd_converted'] += $invoice->total_usd;
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
     * CORRECCIÓN: Garantizar que pagos parciales NUNCA marquen como pagada
     */
    private function determinePaymentStatusCorrected($invoiceIds, $currentPaymentUSD, $totalAmountUSD): int
    {
        // CORRECCIÓN CRÍTICA: Obtener solo pagos anteriores (excluyendo el actual)
        // Usar created_at < now() - 1 segundo para excluir el pago que se está procesando
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoiceIds) {
            $query->whereIn('id', $invoiceIds);
        })
            ->where('created_at', '<', now()->subSecond())
            ->get();

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

        // CORRECCIÓN CRÍTICA: Usar tolerancia muy pequeña para evitar problemas de redondeo
        // Solo marcar como pagada si el monto pagado es MAYOR que el total (con tolerancia mínima)
        $tolerance = 0.01; // 1 centavo de tolerancia para diferencias de redondeo

        if ($totalPaidUSD >= ($totalAmountUSD - $tolerance)) {
            return 1; // paid - La factura queda completamente pagada
        } else {
            return 0; // pending - La factura aún tiene saldo pendiente
        }
    }

    /**
     * Crear registro en expenses sumando los montos de todas las facturas involucradas
     */
    /**
     * Crear registro en expenses
     */
    /**
     * Crear registro en expenses
     */
    /**
     * Crear registro en expenses
     */
    private function createExpense($invoices, $payment, $iva): void
    {
        // Crear o obtener categoría
        $category = ExpenseCategory::firstOrCreate([
            'name' => 'Pagos de Facturas'
        ]);

        $invoice = $invoices->first();

        // === MAPEO DE MÉTODO DE PAGO (copiado de mapPaymentMethodToCount) ===
        $mapping = [
            'CASH' => 'Efectivo',
            'CARD' => 'Tarjeta',
            'MOBILE' => 'Pago Móvil',
            'TRANSFER' => 'Transferencia',
            'BINANCE' => 'Binance',
            'PAYPAL' => 'PayPal',
            'CREDIT' => 'Crédito',
        ];
        $countValue = $mapping[$payment->method] ?? null;
        // ===============================================================

        // ====== CONVERSION RATE - USAR LA MISMA LÓGICA QUE EN calculateUSD ======
        $conversionRate = null;

        if ($payment->payment_method === 'USD') {
            // Para USD, la tasa de conversión es 1
            $conversionRate = 1.0000;
        } else {
            // Mapear moneda para buscar en exchange_rates
            $currencyCode = $payment->payment_method === 'Bs' ? 'BS' : $payment->payment_method;

            $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

            if ($exchangeRate) {
                $conversionRate = (float) $exchangeRate->rate;
            } else {
                // Si no se encuentra la tasa, dejamos null
                \Log::warning("Tasa de cambio no encontrada para: {$payment->payment_method}");
            }
        }
        // =======================================================================

        // Calcular total_usd usando la misma lógica que en calculateUSD
        $totalUSD = $payment->amount;
        if ($payment->payment_method === 'USD') {
            $totalUSD = (float) $payment->amount;
        } elseif ($conversionRate && $conversionRate > 0) {
            $totalUSD = round((float) $payment->amount / (float) $conversionRate, 2);
        }



        $exchangeRate = $invoice->is_indexed ? $conversionRate : ($invoice->currency === 'USD' ? 1.0000 : $invoice->exchange_rate);
        $exemptAmount = $invoice->is_indexed ? ($invoice->exempt_amount / $invoice->exchange_rate) * $exchangeRate ?? 0 : ($invoice->exempt_amount ?? 0);
        $taxableBase = $invoice->is_indexed ? ($invoice->taxable_base / $invoice->exchange_rate) * $exchangeRate ?? 0 : ($invoice->taxable_base ?? 0);
        $taxAmount = $invoice->is_indexed ? ($invoice->tax_amount / $invoice->exchange_rate) * $exchangeRate ?? 0 : ($invoice->tax_amount ?? 0);

        // Crear expense con la estructura correcta
        Expense::create([
            'name' => "Pago Factura # {$invoice->invoice_number} - Proveedor: {$invoice->supplier->name}",
            'category_id' => $category->id,
            'amount' => $payment->amount,
            'conversion_rate' => $conversionRate, // <-- ESTE ES EL CAMPO IMPORTANTE
            'currency' => $payment->payment_method,
            'expense_date' => $payment->payment_date,
            'user_id' => $payment->payment_by,
            'has_invoice' => true,
            'is_deductible' => true,
            'tax_amount' => $taxAmount,
            'total_usd' => $totalUSD,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->created_invoice_date,
            'control_number' => $invoice->control_number,
            'type_of_expense' => 'Normal',
            'count' => $countValue,
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

    /**
     * ISSUE #4: Determinar la moneda preferida del proveedor
     */
    private function getSupplierPreferredCurrency($supplier): string
    {
        if (!$supplier) {
            return 'USD';
        }

        $supplierName = strtolower($supplier->name);

        // Cristalmedicals siempre es USD
        if (strpos($supplierName, 'cristalmedicals') !== false) {
            return 'USD';
        }

        // Para otros proveedores, determinar por las facturas pendientes
        $invoices = Invoice::where('supplier_id', $supplier->id)
            ->whereIn('status', ['pending', 'loaded', 'to_order'])
            ->where(function ($q) {
                $q->whereNull('status_payment')
                    ->orWhere('status_payment', '!=', 1);
            })
            ->get();

        if ($invoices->isEmpty()) {
            return 'USD'; // Default
        }

        // Contar facturas por moneda
        $currencyCounts = $invoices->groupBy('currency')->map->count();

        // Retornar la moneda más común
        return $currencyCounts->sortDesc()->keys()->first() ?? 'USD';
    }

    /**
     * ISSUE #4: Calcular total en moneda del proveedor considerando facturas indexadas
     */
    private function calculateTotalInSupplierCurrency($invoices, $supplierCurrency): float
    {
        $totalUSD = 0;

        foreach ($invoices as $invoice) {
            // Para facturas indexadas en Bs, usar el monto indexado
            if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
                if ($bcvRate) {
                    $indexedAmountBs = round($invoice->total_usd * $bcvRate->rate, 2);
                    // Convertir a USD para el cálculo total
                    $totalUSD += $invoice->total_usd; // USD fijo para facturas indexadas
                } else {
                    $totalUSD += $invoice->total_usd;
                }
            } else {
                // Para facturas no indexadas, usar el total_usd normal
                $totalUSD += $invoice->total_usd;
            }
        }

        // Si la moneda del proveedor es USD, retornar directamente
        if ($supplierCurrency === 'USD') {
            return round($totalUSD, 2);
        }

        // Convertir desde USD a la moneda del proveedor
        $currencyCode = $supplierCurrency === 'Bs' ? 'BS' : $supplierCurrency;
        $exchangeRate = ExchangeRate::where('currency_code', $currencyCode)->first();

        if (!$exchangeRate) {
            return round($totalUSD, 2); // Fallback a USD
        }

        return round($totalUSD * $exchangeRate->rate, 2);
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

            // CRÉDITO FISCAL: Gastos con IVA (manuales, no de facturas pagadas)
            $expensesWithIva = Expense::where('tax_amount', '>', 0)
                ->where('has_invoice', false)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->get();

            // CRÉDITO FISCAL: Facturas de proveedores con IVA
            $invoicesWithIva = Invoice::with('supplier')
                ->where('tax_amount', '>', 0)
                ->whereBetween('created_invoice_date', [$startDate, $endDate])
                ->get();

            $creditoFiscal = 0;
            $totalAmount = 0;

            foreach ($expensesWithIva as $expense) {
                if ($expense->tax_amount > 0) {
                    $creditoFiscal += $expense->tax_amount;
                } elseif ($expense->currency === 'BS' || $expense->currency === 'VES') {
                    $creditoFiscal += $expense->amount * 0.16;
                }
                $totalAmount += $expense->amount;
            }

            $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
            $currentBcvRate = $bcvRate ? $bcvRate->rate : 1;

            foreach ($invoicesWithIva as $invoice) {
                if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                    $creditoFiscal += ($invoice->tax_amount / $invoice->exchange_rate) * $currentBcvRate;
                    $totalAmount += ($invoice->total_amount / $invoice->exchange_rate) * $currentBcvRate;
                } else {
                    $creditoFiscal += $invoice->tax_amount;
                    $totalAmount += $invoice->total_amount;
                }
            }

            // RETENCIONES: 75% del crédito fiscal total
            $retencionesAmount = $creditoFiscal * 0.75;

            return ApiResponse::success([
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date'   => $endDate
                ],
                'credito_fiscal'    => round($creditoFiscal, 2),
                'retenciones'       => round($retencionesAmount, 2),
                'detalle_credito' => [
                    'total_expenses_with_iva'  => $expensesWithIva->count() + $invoicesWithIva->count(),
                    'total_amount_expenses'    => round($totalAmount, 2),
                    'iva_calculated'           => round($creditoFiscal, 2),
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

            // Gastos manuales con IVA
            $expensesQuery = Expense::with(['category'])
                ->where('tax_amount', '>', 0)
                ->where('has_invoice', false)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->get();

            // Facturas de proveedores
            $invoicesQuery = Invoice::with(['supplier'])
                ->where('tax_amount', '>', 0)
                ->whereBetween('created_invoice_date', [$startDate, $endDate])
                ->get();

            $formattedExpenses = $expensesQuery->map(function ($expense) {
                $ivaAmount = $expense->tax_amount > 0 ? $expense->tax_amount : (($expense->currency === 'BS' || $expense->currency === 'VES') ? $expense->amount * 0.16 : 0);
                $amountBs = ($expense->currency === 'BS' || $expense->currency === 'VES') ? $expense->amount : 0;

                return [
                    'id' => 'E' . $expense->id,
                    'original_id' => $expense->id,
                    'type' => 'expense',
                    'name' => $expense->name,
                    'category_name' => $expense->category->name ?? 'Gastos Manuales',
                    'amount_bs' => (float) $amountBs,
                    'amount_usd' => (float) $expense->total_usd,
                    'currency' => $expense->currency,
                    'expense_date' => $expense->expense_date,
                    'is_deductible' => (bool) $expense->is_deductible,
                    'has_invoice' => (bool) $expense->has_invoice,
                    'iva_amount' => round($ivaAmount, 2),
                    'exempt_amount' => (float) ($expense->exempt_amount ?? 0),
                    'taxable_base' => (float) ($expense->taxable_base ?? ($amountBs - round($ivaAmount, 2) - (float) ($expense->exempt_amount ?? 0))),
                    'supplier_name' => $expense->supplier_name ?? $expense->name,
                    'supplier_rif' => $expense->supplier_rif ?? null,
                    'supplier_business_name' => $expense->supplier_business_name ?? $expense->name,
                    'invoice_number' => $expense->invoice_number ?? 'N/A',
                    'created_at' => $expense->created_at,
                    'updated_at' => $expense->updated_at,
                    'sort_date' => $expense->expense_date,
                ];
            });

            $bcvRate = ExchangeRate::where('currency_code', 'BS')->first();
            $currentBcvRate = $bcvRate ? $bcvRate->rate : 1;

            $formattedInvoices = $invoicesQuery->map(function ($invoice) use ($currentBcvRate) {
                if ($invoice->is_indexed && $invoice->currency === 'Bs') {
                    $ivaAmountBs = ($invoice->tax_amount / $invoice->exchange_rate) * $currentBcvRate;
                    $amountBs = ($invoice->total_amount / $invoice->exchange_rate) * $currentBcvRate;
                    $exemptAmountBs = ($invoice->exempt_amount / $invoice->exchange_rate) * $currentBcvRate;
                } else {
                    $ivaAmountBs = $invoice->currency === 'Bs' ? $invoice->tax_amount : 0;
                    $amountBs = $invoice->currency === 'Bs' ? $invoice->total_amount : 0;
                    $exemptAmountBs = $invoice->currency === 'Bs' ? $invoice->exempt_amount : 0;
                }

                $supplier = $invoice->supplier;

                return [
                    'id' => 'I' . $invoice->id,
                    'original_id' => $invoice->id,
                    'type' => 'invoice',
                    'name' => "Factura Proveedor #{$invoice->invoice_number}",
                    'category_name' => 'Facturas de Proveedores',
                    'amount_bs' => (float) $amountBs,
                    'amount_usd' => (float) $invoice->total_usd,
                    'currency' => $invoice->currency,
                    'expense_date' => $invoice->created_invoice_date,
                    'is_deductible' => true,
                    'has_invoice' => true,
                    'iva_amount' => round($ivaAmountBs, 2),
                    'exempt_amount' => (float) $exemptAmountBs,
                    'taxable_base' => (float) ($invoice->taxable_base ?? ($amountBs - round($ivaAmountBs, 2) - $exemptAmountBs)),
                    'supplier_name' => $supplier ? $supplier->name : 'N/A',
                    'supplier_rif' => $supplier ? $supplier->rif : 'N/A',
                    'supplier_business_name' => $supplier ? $supplier->business_name : 'N/A',
                    'invoice_number' => $invoice->invoice_number,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                    'sort_date' => $invoice->created_invoice_date,
                ];
            });

            $allRecords = $formattedExpenses->concat($formattedInvoices)->sortByDesc(function ($item) {
                return $item['sort_date'] . '_' . $item['id'];
            })->values();

            $totalRecords = $allRecords->count();
            $offset = ($page - 1) * $itemsPerPage;
            $itemsForPage = $allRecords->slice($offset, $itemsPerPage)->values();

            $pageTotals = [
                'total_amount' => $itemsForPage->sum('amount_bs'),
                'total_iva' => $itemsForPage->sum('iva_amount'),
                'total_expenses' => $itemsForPage->count()
            ];

            return ApiResponse::success([
                'data' => $itemsForPage->toArray(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $itemsPerPage,
                    'total' => $totalRecords,
                    'last_page' => ceil($totalRecords / $itemsPerPage),
                    'from' => $totalRecords > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $itemsPerPage, $totalRecords)
                ],
                'totals' => $pageTotals,
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ], 'Registros de crédito fiscal combinados exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al obtener registros de gastos: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener registros de gastos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ISSUE #3: Calcular monto indexado para facturas indexadas
     * Para facturas indexadas: Bs = USD × Tasa BCV actual
     */
    private function calculateIndexedAmount($invoice): array
    {
        if (!$invoice->is_indexed || $invoice->currency !== 'Bs') {
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        // Obtener tasa BCV actual (BS)
        $exchangeRate = ExchangeRate::where('currency_code', 'BS')->first();
        if (!$exchangeRate) {
            // Si no hay tasa BCV, usar la tasa original de la factura
            return [
                'original_amount' => $invoice->total_amount,
                'original_amount_usd' => $invoice->total_usd,
                'is_indexed' => false
            ];
        }

        // Calcular monto indexado: USD × Tasa BCV actual
        $indexedAmountBs = round($invoice->total_usd * $exchangeRate->rate, 2);

        return [
            'original_amount' => $invoice->total_amount,
            'original_amount_usd' => $invoice->total_usd,
            'indexed_amount' => $indexedAmountBs,
            'indexed_amount_usd' => $invoice->total_usd, // El USD sigue siendo el mismo
            'is_indexed' => true,
            'bcv_rate' => $exchangeRate->rate,
            'rate_date' => $exchangeRate->updated_at
        ];
    }

    /**
     * ISSUE #3: Cambiar estado de indexación de una factura
     */
    public function toggleIndexedStatus(Request $request, $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);

            $isIndexed = $request->boolean('is_indexed');

            $invoice->update(['is_indexed' => $isIndexed]);

            return ApiResponse::success([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'is_indexed' => $invoice->is_indexed,
                'message' => $isIndexed ? 'Factura marcada como indexada' : 'Factura desmarcada como indexada'
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el estado de indexación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar la fecha de pago de una factura
     */
    public function updatePaymentDate(Request $request, int $invoiceId): JsonResponse
    {
        try {
            $request->validate([
                'payment_date' => 'required|date'
            ]);

            $invoice = Invoice::findOrFail($invoiceId);
            $invoice->update([
                'payment_date' => $request->payment_date
            ]);

            return ApiResponse::success($invoice, 'Fecha de pago actualizada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar la fecha: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Marcar una factura como pagada directamente (sin generar gastos)
     */
    public function markAsPaidDirectly(int $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $invoice->update([
                'status_payment' => 1,
                'status' => 'ordered'
            ]);

            return ApiResponse::success($invoice, 'Factura marcada como pagada (sin registro de gasto)');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al marcar factura como pagada: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calcular monto restante para una factura individual considerando pagos parciales
     */
    private function calculateRemainingAmountForInvoice(Invoice $invoice): float
    {
        // Obtener todos los pagos para esta factura
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
            $query->where('id', $invoice->id);
        })->get();

        $totalPaidUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $totalPaidUSD += $payment->amount;
            } else {
                $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                $exchangeRate = ExchangeRate::where('currency_code', $rateCurrency)->first();
                if ($exchangeRate) {
                    $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Calcular monto restante en USD
        $remainingAmountUSD = max(0, $invoice->total_usd - $totalPaidUSD);

        // Convertir a moneda original de la factura
        if ($invoice->currency === 'Bs') {
            $exchangeRate = ExchangeRate::where('currency_code', 'BS')->first();
            if ($exchangeRate) {
                return round($remainingAmountUSD * $exchangeRate->rate, 2);
            }
        } elseif ($invoice->currency === 'COP') {
            $exchangeRate = ExchangeRate::where('currency_code', 'COP')->first();
            if ($exchangeRate) {
                return round($remainingAmountUSD * $exchangeRate->rate, 2);
            }
        }

        return $remainingAmountUSD; // Para USD
    }

    /**
     * Calcular monto restante USD para una factura individual considerando pagos parciales
     */
    private function calculateRemainingAmountUSDForInvoice(Invoice $invoice): float
    {
        // Obtener todos los pagos para esta factura
        $payments = InvoicePayment::whereHas('invoices', function ($query) use ($invoice) {
            $query->where('id', $invoice->id);
        })->get();

        $totalPaidUSD = 0;
        foreach ($payments as $payment) {
            if ($payment->payment_method === 'USD') {
                $totalPaidUSD += $payment->amount;
            } else {
                $rateCurrency = ($payment->payment_method === 'COP') ? 'COPC' : $payment->payment_method;
                $exchangeRate = ExchangeRate::where('currency_code', $rateCurrency)->first();
                if ($exchangeRate) {
                    $totalPaidUSD += round($payment->amount / $exchangeRate->rate, 2);
                }
            }
        }

        // Retornar monto restante en USD
        return max(0, $invoice->total_usd - $totalPaidUSD);
    }
}
