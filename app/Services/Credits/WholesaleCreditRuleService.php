<?php

declare(strict_types=1);

namespace App\Services\Credits;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WholesaleCreditRuleService
{
    /**
     * Resuelve los días de crédito de un producto aplicando la jerarquía de droguería:
     * 1. Producto individual
     * 2. Laboratorio fabricante
     * 3. Cliente / Farmacia compradora
     * 4. 0 días (Contado)
     */
    public function resolveProductCreditDays(Product $product, Client $client): int
    {
        if ($product->credit_days !== null) {
            return max(0, (int) $product->credit_days);
        }

        if ($product->laboratory && $product->laboratory->default_credit_days !== null && $product->laboratory->default_credit_days > 0) {
            return (int) $product->laboratory->default_credit_days;
        }

        if ($client->default_credit_days !== null && $client->default_credit_days > 0) {
            return (int) $client->default_credit_days;
        }

        return 0;
    }

    /**
     * Valida el estado de crédito, mora y cupos de la farmacia.
     * Retorna array con:
     * - 'can_proceed_to_picking': bool (true si no requiere aprobación crediticia manual)
     * - 'requires_credit_approval': bool
     * - 'warnings': array
     * - 'blocked_reason': ?string (si el cliente está totalmente bloqueado)
     */
    public function evaluateClientCreditLimit(Client $client, float $newOrderAmountUSD): array
    {
        $now = Carbon::now();
        $warnings = [];

        // 1. Validación de Permiso Sanitario SACS
        if (!empty($client->sacs_permit_expires_at)) {
            $sacsExpiry = Carbon::parse($client->sacs_permit_expires_at);
            if ($sacsExpiry->isPast()) {
                return [
                    'can_proceed_to_picking' => false,
                    'requires_credit_approval' => true,
                    'warnings' => ['El permiso sanitario SACS de la farmacia está vencido desde ' . $sacsExpiry->format('d/m/Y')],
                    'blocked_reason' => 'Permiso sanitario SACS vencido',
                ];
            }
        }

        // 2. Validación de Facturas Vencidas (Mora)
        $hasOverdueCredits = Credit::where('client_id', $client->id)
            ->where('status', 'Active')
            ->where('pending_amount', '>', 0)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<', $now->toDateString())
            ->exists();

        if ($hasOverdueCredits && $client->block_on_overdue) {
            return [
                'can_proceed_to_picking' => false,
                'requires_credit_approval' => true,
                'warnings' => ['La farmacia posee facturas de crédito vencidas pendientes de pago'],
                'blocked_reason' => 'Bloqueo por morosidad',
            ];
        }

        // 3. Validación de Límite de Crédito en USD
        $currentDebtUSD = (float) Credit::where('client_id', $client->id)
            ->where('status', 'Active')
            ->sum('pending_amount');

        $creditLimitUSD = (float) $client->credit_limit_usd;
        $projectedDebtUSD = $currentDebtUSD + $newOrderAmountUSD;

        $exceedsLimit = $creditLimitUSD > 0 && $projectedDebtUSD > $creditLimitUSD;
        if ($exceedsLimit) {
            $warnings[] = sprintf(
                'El pedido excede el límite de crédito disponible. Límite: $%.2f | Deuda actual: $%.2f | Pedido: $%.2f',
                $creditLimitUSD,
                $currentDebtUSD,
                $newOrderAmountUSD
            );
        }

        // 4. Validación de Tope Mensual de Compra de la Farmacia
        if ($client->max_monthly_purchase_limit_usd !== null && (float) $client->max_monthly_purchase_limit_usd > 0) {
            $monthlyPurchasesUSD = (float) Credit::where('client_id', $client->id)
                ->whereMonth('credit_date', $now->month)
                ->whereYear('credit_date', $now->year)
                ->sum('credit_amount');

            if (($monthlyPurchasesUSD + $newOrderAmountUSD) > (float) $client->max_monthly_purchase_limit_usd) {
                $warnings[] = 'El pedido supera el tope de compras mensual asignado a esta farmacia';
            }
        }

        $requiresApproval = $exceedsLimit || !empty($warnings);

        return [
            'can_proceed_to_picking' => !$requiresApproval,
            'requires_credit_approval' => $requiresApproval,
            'warnings' => $warnings,
            'blocked_reason' => null,
            'current_debt_usd' => $currentDebtUSD,
            'available_credit_usd' => max(0, $creditLimitUSD - $currentDebtUSD),
        ];
    }

    /**
     * Valida los topes de unidades del producto para la farmacia (Control de escasez / cupos diarios y mensuales).
     */
    public function validateProductQuota(Product $product, Client $client, float $requestedQty): void
    {
        // Tope por pedido individual
        if ($product->max_units_per_order !== null && $requestedQty > $product->max_units_per_order) {
            throw ValidationException::withMessages([
                'quantity' => "El producto {$product->name} tiene un tope máximo de {$product->max_units_per_order} unidades por pedido.",
            ]);
        }

        // Mínimo de venta mayorista
        if ($product->min_wholesale_units !== null && $requestedQty < $product->min_wholesale_units) {
            throw ValidationException::withMessages([
                'quantity' => "El producto {$product->name} requiere un mínimo de compra de {$product->min_wholesale_units} unidades.",
            ]);
        }

        $today = Carbon::today();

        // Tope diario por farmacia
        if ($product->max_units_per_client_day !== null) {
            $todayPurchased = (float) \App\Models\OrderDetail::whereHas('order', function ($q) use ($client, $today) {
                $q->where('client_id', $client->id)
                  ->whereDate('order_date', $today);
            })->where('product_id', $product->id)->sum('quantity');

            if (($todayPurchased + $requestedQty) > $product->max_units_per_client_day) {
                $availableToday = max(0, $product->max_units_per_client_day - $todayPurchased);
                throw ValidationException::withMessages([
                    'quantity' => "Cupo diario excedido para {$product->name}. Máximo diario: {$product->max_units_per_client_day} und. Disponible hoy: {$availableToday} und.",
                ]);
            }
        }

        // Tope mensual por farmacia
        if ($product->max_units_per_client_month !== null) {
            $startOfMonth = Carbon::now()->startOfMonth();
            $monthPurchased = (float) \App\Models\OrderDetail::whereHas('order', function ($q) use ($client, $startOfMonth) {
                $q->where('client_id', $client->id)
                  ->where('order_date', '>=', $startOfMonth);
            })->where('product_id', $product->id)->sum('quantity');

            if (($monthPurchased + $requestedQty) > $product->max_units_per_client_month) {
                $availableMonth = max(0, $product->max_units_per_client_month - $monthPurchased);
                throw ValidationException::withMessages([
                    'quantity' => "Cupo mensual excedido para {$product->name}. Máximo mensual: {$product->max_units_per_client_month} und. Disponible este mes: {$availableMonth} und.",
                ]);
            }
        }
    }

    /**
     * Agrupa los ítems del carrito según sus días de crédito (Opción A: División por plazo).
     * Retorna una colección agrupada por plazo de crédito (ej. [0 => [...], 7 => [...], 15 => [...]]).
     */
    public function groupItemsByCreditTerm(array $items, Client $client): Collection
    {
        return collect($items)->groupBy(function ($item) use ($client) {
            $product = Product::with('laboratory')->find($item['product_id']);
            if (!$product) {
                return 0;
            }
            return $this->resolveProductCreditDays($product, $client);
        });
    }
}
