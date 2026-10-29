<?php

declare(strict_types=1);

namespace App\Services\Wholesale;

use App\Enums\B2BOrderStatusEnum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductFailure;
use App\Models\ProductLot;
use App\Services\Credits\WholesaleCreditRuleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WholesaleOrderService
{
    public function __construct(
        protected WholesaleCreditRuleService $creditRuleService
    ) {}

    /**
     * Procesa la creación de un pedido mayorista B2B aplicando la Opción A:
     * Si los productos tienen diferentes plazos de crédito (ej. 0 días contado, 7 días, 15 días),
     * divide automáticamente el pedido en sub-facturas/sub-órdenes por cada plazo de vencimiento.
     *
     * @param array $payload [
     *     'client_id' => int,
     *     'items' => [ ['product_id' => int, 'quantity' => float], ... ],
     *     'notes' => ?string,
     *     'delivery_route_id' => ?int,
     * ]
     * @return array Resumen con las órdenes generadas y fallas detectadas
     */
    public function createWholesaleOrder(array $payload): array
    {
        $clientId = (int) $payload['client_id'];
        $client = Client::findOrFail($clientId);

        if (empty($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'El pedido debe contener al menos un producto.',
            ]);
        }

        // 1. Validar topes de unidades y cuotas para cada producto
        foreach ($payload['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $this->creditRuleService->validateProductQuota($product, $client, (float) $item['quantity']);
        }

        // 2. Agrupar ítems según días de crédito (Opción A: División por plazo)
        $groupedItems = $this->creditRuleService->groupItemsByCreditTerm($payload['items'], $client);

        $now = Carbon::now();
        $bcvRate = (float) (ExchangeRate::where('currency_code', 'BS')->latest()->value('rate') ?: 1.0);

        return DB::transaction(function () use ($client, $groupedItems, $payload, $now, $bcvRate) {
            $generatedOrders = [];
            $allFailures = [];
            $sellerId = Auth::id() ?: $client->sales_rep_id ?: 1;

            $parentOrderId = null;
            $hasMultipleTerms = $groupedItems->count() > 1;

            foreach ($groupedItems as $creditTermDays => $termItems) {
                $creditTermDays = (int) $creditTermDays;

                // Crear la orden de venta para este plazo
                $order = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $sellerId,
                    'order_date' => $now,
                    'currency' => 'USD',
                    'status' => Order::PENDING,
                    'b2b_workflow_status' => B2BOrderStatusEnum::DRAFT->value,
                    'credit_term_days' => $creditTermDays,
                    'parent_b2b_order_id' => $hasMultipleTerms ? $parentOrderId : null,
                    'delivery_route_id' => $payload['delivery_route_id'] ?? $client->delivery_route_id,
                    'payment_methods' => [],
                    'taxable_base' => 0,
                    'total_amount' => 0,
                    'total_amount_usd' => 0,
                    'total_cost' => 0,
                ]);

                if ($parentOrderId === null && $hasMultipleTerms) {
                    $parentOrderId = $order->id;
                }

                $orderTotalUSD = 0.0;
                $orderTotalCost = 0.0;
                $orderTaxableBase = 0.0;

                // Procesar cada ítem: asignación FEFO de lote y registro de fallas
                foreach ($termItems as $itemData) {
                    $productId = (int) $itemData['product_id'];
                    $requestedQty = (float) $itemData['quantity'];
                    $product = Product::with('laboratory')->findOrFail($productId);

                    // Buscar el lote con stock más próximo a vencer (FEFO)
                    $lot = ProductLot::where('product_id', $productId)
                        ->where('quantity', '>', 0)
                        ->orderBy('expiration_date', 'asc')
                        ->first();

                    $allocatedQty = 0.0;

                    if ($lot) {
                        $allocatedQty = min($requestedQty, (float) $lot->quantity);
                    }

                    // Si hay unidades que no se pudieron cubrir por falta de stock, registrar falla
                    $missingQty = $requestedQty - $allocatedQty;
                    if ($missingQty > 0) {
                        ProductFailure::create([
                            'product_id' => $product->id,
                            'quantity' => $missingQty,
                            'reason' => 'Sin stock suficiente en droguería al emitir pedido B2B',
                        ]);

                        $allFailures[] = [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'requested' => $requestedQty,
                            'allocated' => $allocatedQty,
                            'missing' => $missingQty,
                        ];
                    }

                    if ($allocatedQty <= 0) {
                        continue;
                    }

                    // Descontar stock del lote seleccionado
                    $lot->quantity -= $allocatedQty;
                    $lot->save();

                    // Descontar stock general del producto
                    $product->stock = max(0, (float) $product->stock - $allocatedQty);
                    $product->save();

                    // Cálculo de precios con descuento comercial de la farmacia
                    $basePrice = (float) $product->sale_price;
                    $commercialDiscountPct = (float) ($client->commercial_discount_pct ?: 0);
                    $finalUnitPrice = round($basePrice * (1 - ($commercialDiscountPct / 100)), 2);

                    $lineTotal = round($finalUnitPrice * $allocatedQty, 2);
                    $lineCost = round(((float) $product->unit_cost) * $allocatedQty, 2);

                    $isTaxable = (bool) $product->iva;
                    if ($isTaxable) {
                        $orderTaxableBase += $lineTotal;
                    }

                    $orderTotalUSD += $lineTotal;
                    $orderTotalCost += $lineCost;

                    // Crear detalle de orden vinculado al lote FEFO
                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_lot_id' => $lot->id,
                        'product_type' => 'iva',
                        'quantity' => $allocatedQty,
                        'picked_quantity' => 0,
                        'checked_quantity' => 0,
                        'price' => $finalUnitPrice,
                        'unit_price_usd' => $finalUnitPrice,
                        'unit_cost' => (float) $product->unit_cost,
                        'price_before_discount' => $basePrice,
                        'discount_percentage' => $commercialDiscountPct,
                        'discount_type' => $commercialDiscountPct > 0 ? 'Commercial B2B' : null,
                        'credit_days_applied' => $creditTermDays,
                    ]);
                }

                // Actualizar totales de la orden
                $order->update([
                    'taxable_base' => $orderTaxableBase,
                    'total_amount' => $orderTotalUSD,
                    'total_amount_usd' => $orderTotalUSD,
                    'total_cost' => $orderTotalCost,
                ]);

                // 3. Evaluar límites de crédito y mora para esta sub-orden
                $creditEval = $this->creditRuleService->evaluateClientCreditLimit($client, $orderTotalUSD);

                $workflowStatus = $creditEval['requires_credit_approval']
                    ? B2BOrderStatusEnum::PENDING_CREDIT->value
                    : B2BOrderStatusEnum::APPROVED_CREDIT->value;

                $order->update([
                    'b2b_workflow_status' => $workflowStatus,
                ]);

                // 4. Si el plazo es a crédito (> 0 días), crear el registro en Cuentas por Cobrar (Credit)
                if ($creditTermDays > 0) {
                    $dueDate = (clone $now)->addDays($creditTermDays);
                    $earlyPaymentDays = min(7, (int) ceil($creditTermDays / 2));
                    $earlyPaymentExpiry = (clone $now)->addDays($earlyPaymentDays);

                    Credit::create([
                        'client_id' => $client->id,
                        'order_id' => $order->id,
                        'credit_amount' => $orderTotalUSD,
                        'pending_amount' => $orderTotalUSD,
                        'credit_date' => $now,
                        'next_due_date' => $dueDate->toDateString(),
                        'status' => 'Active',
                        'credit_type' => 'drogueria_b2b',
                        'invoice_number' => 'FAC-' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                        'exchange_rate_issue' => $bcvRate,
                        'is_indexed' => true,
                        'early_payment_discount_pct' => (float) ($client->early_payment_discount_pct ?: 0),
                        'early_payment_expires_at' => $earlyPaymentExpiry->toDateString(),
                        'sales_rep_id' => $client->sales_rep_id,
                        'collector_user_id' => $client->collector_user_id,
                    ]);
                }

                $generatedOrders[] = [
                    'order_id' => $order->id,
                    'credit_term_days' => $creditTermDays,
                    'term_label' => $creditTermDays === 0 ? 'Contado' : "Crédito a {$creditTermDays} días",
                    'total_usd' => $orderTotalUSD,
                    'workflow_status' => $workflowStatus,
                    'requires_credit_approval' => $creditEval['requires_credit_approval'],
                    'warnings' => $creditEval['warnings'],
                ];
            }

            return [
                'success' => true,
                'orders_count' => count($generatedOrders),
                'orders' => $generatedOrders,
                'failures' => $allFailures,
            ];
        });
    }

    /**
     * Aprueba el crédito de una orden retenida por el Analista de Cobranzas.
     */
    public function approveCreditOrder(int $orderId, int $approverUserId): Order
    {
        $order = Order::findOrFail($orderId);

        if ($order->b2b_workflow_status !== B2BOrderStatusEnum::PENDING_CREDIT->value) {
            throw ValidationException::withMessages([
                'order' => 'La orden no se encuentra en estado pendiente por crédito.',
            ]);
        }

        $order->update([
            'b2b_workflow_status' => B2BOrderStatusEnum::APPROVED_CREDIT->value,
            'status' => Order::PENDING, // Listo para que Almacén inicie Picking
        ]);

        return $order;
    }
}
