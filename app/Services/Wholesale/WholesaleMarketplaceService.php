<?php

declare(strict_types=1);

namespace App\Services\Wholesale;

use App\Enums\PaymentVerificationStatusEnum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\CreditPaymentAllocation;
use App\Models\ExchangeRate;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WholesaleMarketplaceService
{
    /**
     * Catálogo de productos mayoristas para el Marketplace B2B de Farmacias.
     */
    public function getMarketplaceCatalog(Client $client, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['laboratory:id,name', 'category:id,name', 'lots' => function ($q) {
                $q->where('quantity', '>', 0)->orderBy('expiration_date', 'asc');
            }])
            ->where('is_active', true)
            ->where('stock', '>', 0);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('active_ingredient', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['laboratory_id'])) {
            $query->where('laboratory_id', $filters['laboratory_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['requires_cold_chain']) && $filters['requires_cold_chain']) {
            $query->where('requires_cold_chain', true);
        }

        $bcvRate = (float) (ExchangeRate::where('currency_code', 'BS')->latest()->value('rate') ?: 1.0);
        $commercialDiscountPct = (float) ($client->commercial_discount_pct ?: 0);

        $paginator = $query->orderBy('name')->paginate($perPage);

        // Transformar los productos para presentar datos mayoristas
        $paginator->getCollection()->transform(function ($product) use ($bcvRate, $commercialDiscountPct, $client) {
            $basePriceUSD = (float) $product->sale_price;
            $discountedPriceUSD = round($basePriceUSD * (1 - ($commercialDiscountPct / 100)), 2);

            $creditDays = $product->credit_days !== null
                ? (int) $product->credit_days
                : ($product->laboratory?->default_credit_days ?? $client->default_credit_days ?? 0);

            $nextLot = $product->lots->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'active_ingredient' => $product->active_ingredient,
                'laboratory_name' => $product->laboratory?->name,
                'barcode' => $product->barcode,
                'presentation' => $product->presentation,
                'unit_of_measure' => $product->unit_of_measure,
                'stock' => (float) $product->stock,
                'next_expiration' => $nextLot?->expiration_date?->format('Y-m-d'),
                'next_lot_number' => $nextLot?->lot_number,
                'requires_cold_chain' => (bool) $product->requires_cold_chain,
                'sanitary_registration' => $product->sanitary_registration,
                'credit_days' => $creditDays,
                'credit_term_label' => $creditDays === 0 ? 'Contado' : "{$creditDays} días",
                'base_price_usd' => $basePriceUSD,
                'discount_percentage' => $commercialDiscountPct,
                'final_price_usd' => $discountedPriceUSD,
                'final_price_bs' => round($discountedPriceUSD * $bcvRate, 2),
                'max_units_per_order' => $product->max_units_per_order,
                'min_wholesale_units' => $product->min_wholesale_units ?: 1,
            ];
        });

        return $paginator;
    }

    /**
     * Consulta el estado de cuenta y facturas pendientes de la farmacia.
     */
    public function getAccountStatement(Client $client): array
    {
        $today = Carbon::today();
        $bcvRate = (float) (ExchangeRate::where('currency_code', 'BS')->latest()->value('rate') ?: 1.0);

        $credits = Credit::where('client_id', $client->id)
            ->where('status', 'Active')
            ->where('pending_amount', '>', 0)
            ->orderBy('next_due_date', 'asc')
            ->get();

        $totalDebtUSD = 0.0;
        $totalOverdueUSD = 0.0;
        $totalEarlyPaymentSavingsUSD = 0.0;
        $invoiceList = [];

        foreach ($credits as $credit) {
            $pendingUSD = (float) $credit->pending_amount;
            $totalDebtUSD += $pendingUSD;

            $dueDate = $credit->next_due_date ? Carbon::parse($credit->next_due_date) : null;
            $isOverdue = $dueDate ? $dueDate->isPast() : false;
            $daysOverdue = $isOverdue && $dueDate ? (int) $today->diffInDays($dueDate) : 0;

            if ($isOverdue) {
                $totalOverdueUSD += $pendingUSD;
            }

            // Ahorro por pronto pago si está dentro de la fecha límite
            $earlyExpiry = $credit->early_payment_expires_at ? Carbon::parse($credit->early_payment_expires_at) : null;
            $canEarlyPay = $earlyExpiry && !$earlyExpiry->isPast() && (float) $credit->early_payment_discount_pct > 0;
            $earlyDiscountAmount = $canEarlyPay
                ? round($pendingUSD * (((float) $credit->early_payment_discount_pct) / 100), 2)
                : 0.0;

            $totalEarlyPaymentSavingsUSD += $earlyDiscountAmount;

            $invoiceList[] = [
                'credit_id' => $credit->id,
                'order_id' => $credit->order_id,
                'invoice_number' => $credit->invoice_number,
                'control_number' => $credit->control_number,
                'issue_date' => $credit->credit_date?->format('Y-m-d'),
                'due_date' => $dueDate?->format('Y-m-d'),
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'credit_amount_usd' => (float) $credit->credit_amount,
                'pending_amount_usd' => $pendingUSD,
                'pending_amount_bs' => round($pendingUSD * $bcvRate, 2),
                'bcv_rate_today' => $bcvRate,
                'early_payment_eligible' => $canEarlyPay,
                'early_payment_expires_at' => $earlyExpiry?->format('Y-m-d'),
                'early_payment_savings_usd' => $earlyDiscountAmount,
                'amount_to_pay_with_early_discount_usd' => max(0, $pendingUSD - $earlyDiscountAmount),
            ];
        }

        $creditLimitUSD = (float) $client->credit_limit_usd;
        $availableCreditUSD = max(0, $creditLimitUSD - $totalDebtUSD);

        return [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'identification' => $client->identification,
            'credit_limit_usd' => $creditLimitUSD,
            'total_debt_usd' => $totalDebtUSD,
            'total_debt_bs' => round($totalDebtUSD * $bcvRate, 2),
            'total_overdue_usd' => $totalOverdueUSD,
            'available_credit_usd' => $availableCreditUSD,
            'early_payment_savings_total_usd' => $totalEarlyPaymentSavingsUSD,
            'bcv_rate' => $bcvRate,
            'invoices' => $invoiceList,
        ];
    }

    /**
     * Registra un abono/pago o retención desde el portal de autogestión de la farmacia.
     */
    public function submitCustomerPayment(Client $client, array $payload): CreditPayment
    {
        $amountOriginal = (float) $payload['amount'];
        $currency = strtoupper($payload['currency'] ?? 'USD');
        $bcvRate = (float) (ExchangeRate::where('currency_code', 'BS')->latest()->value('rate') ?: 1.0);

        $amountUSD = match ($currency) {
            'BS', 'VES' => round($amountOriginal / $bcvRate, 2),
            default => $amountOriginal,
        };

        if ($amountUSD <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'El monto del pago debe ser mayor a 0.',
            ]);
        }

        return DB::transaction(function () use ($client, $payload, $currency, $amountOriginal, $amountUSD, $bcvRate) {
            $payment = CreditPayment::create([
                'client_id' => $client->id,
                'seller_id' => $client->sales_rep_id ?: 1,
                'payment_date' => Carbon::now(),
                'method_Payment' => [
                    [
                        'method' => $payload['payment_method'] ?? 'bank_transfer',
                        'amount' => $amountOriginal,
                        'currency' => $currency,
                        'reference' => $payload['reference_number'] ?? null,
                    ]
                ],
                'status' => PaymentVerificationStatusEnum::PENDING_VERIFICATION->value,
                'reference_number' => $payload['reference_number'] ?? null,
                'bank_name' => $payload['bank_name'] ?? null,
                'receipt_photo_url' => $payload['receipt_photo_url'] ?? null,
                'exchange_rate_applied' => $bcvRate,
                'amount_usd_equivalent' => $amountUSD,
                'source_channel' => 'marketplace_b2b',
                'withheld_iva_amount' => (float) ($payload['withheld_iva_amount'] ?? 0),
                'withheld_islr_amount' => (float) ($payload['withheld_islr_amount'] ?? 0),
                'retention_voucher_number' => $payload['retention_voucher_number'] ?? null,
                'retention_voucher_photo_url' => $payload['retention_voucher_photo_url'] ?? null,
            ]);

            // Asignar a las facturas indicadas si se suministraron
            if (!empty($payload['invoice_allocations']) && is_array($payload['invoice_allocations'])) {
                foreach ($payload['invoice_allocations'] as $allocation) {
                    $creditId = (int) $allocation['credit_id'];
                    $allocUSD = (float) $allocation['amount_usd'];

                    CreditPaymentAllocation::create([
                        'credit_payment_id' => $payment->id,
                        'credit_id' => $creditId,
                        'amount_allocated_usd' => $allocUSD,
                        'amount_allocated_original' => $allocUSD,
                        'currency' => 'USD',
                        'early_payment_discount_applied' => (float) ($allocation['early_discount_applied'] ?? 0),
                    ]);
                }
            }

            return $payment;
        });
    }
}
