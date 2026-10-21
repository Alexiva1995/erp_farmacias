<?php

namespace App\Services\Zoom;

use App\Contracts\Repositories\ZoomProductRepositoryInterface;
use App\Contracts\Zoom\ZoomServiceInterface;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\Resources\ResourceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZoomService implements ZoomServiceInterface
{
    public function __construct(
        protected ZoomProductRepositoryInterface $zoomProductRepository,
        protected ResourceService $resourceService
    ) {}

    /**
     * Obtener el producto base de Zoom configurado.
     */
    public function getZoomProduct(): Product
    {
        return $this->zoomProductRepository->findOrCreateZoomProduct();
    }

    /**
     * Agregar un ítem de Zoom a la orden activa del vendedor con su descripción y monto sin IVA.
     */
    public function addZoomItemToOrder(array $data, int $sellerId): array
    {
        return DB::transaction(function () use ($data, $sellerId) {
            $zoomProduct = $this->zoomProductRepository->findOrCreateZoomProduct();

            // 1. Obtener orden activa o crear una nueva para el vendedor
            $order = Order::where('seller_id', $sellerId)
                ->where('status', Order::PENDING)
                ->first();

            if (!$order) {
                $clientId = $data['client_id'] ?? null;
                if (!$clientId) {
                    $genericClient = Client::where('identification', '99999999')->first();
                    $clientId = $genericClient ? $genericClient->id : Client::first()->id;
                }

                $orderCurrency = strtoupper($data['currency'] ?? 'USD');
                $order = Order::create([
                    'seller_id' => $sellerId,
                    'client_id' => $clientId,
                    'status' => Order::PENDING,
                    'currency' => $orderCurrency,
                    'total_amount' => 0.00,
                    'total_amount_usd' => 0.00,
                    'money_returns' => 0.00,
                    'total_cost' => 0.00,
                    'order_date' => Carbon::now(),
                ]);
            }

            $inputCurrency = strtoupper($data['currency'] ?? $order->currency ?? 'USD');
            $currentOrderCurrency = strtoupper($order->currency ?? 'USD');
            $amountNet = (float) $data['amount_net']; // Monto sin IVA ingresado

            $tasaBs = (float) ($this->resourceService->getExchangeRate('BS') ?: 1);
            $tasaCop = (float) ($this->resourceService->getExchangeRate('COP') ?: 1);

            // Si la orden estaba en otra moneda, actualizar la orden y sus ítems previos a la moneda escogida en Zoom
            if ($currentOrderCurrency !== $inputCurrency) {
                $order->currency = $inputCurrency;
                $order->save();

                $pricingStrategy = \App\Services\Order\Strategies\PricingStrategyFactory::make($inputCurrency);
                $existingDetails = $order->details()->with('product')->get();
                foreach ($existingDetails as $item) {
                    if ($item->dish_id || $item->court_id) {
                        $usdPrice = (float) $item->unit_price_usd;
                        $priceToSet = match ($inputCurrency) {
                            'BS' => round($usdPrice * $tasaBs, 2),
                            'COP' => ceil(round($usdPrice * $tasaCop) / 100) * 100,
                            default => $usdPrice,
                        };
                    } elseif ($item->product) {
                        $priceToSet = $pricingStrategy->calculatePrice($item->product, $item);
                    } else {
                        $priceToSet = (float) $item->price;
                    }

                    if ($item->discount_percentage > 0 && $item->discount_type) {
                        $priceToSet = $priceToSet * (1 - ($item->discount_percentage / 100));
                    }

                    $item->price = $priceToSet;
                    $item->save();
                }
            }

            $orderCurrency = $inputCurrency;

            // 2. Normalizar el monto neto a USD como moneda base y calcular equivalencias exactas
            $amountNetUsd = match ($inputCurrency) {
                'BS' => ($tasaBs > 0) ? ($amountNet / $tasaBs) : $amountNet,
                'COP' => ($tasaCop > 0) ? ($amountNet / $tasaCop) : $amountNet,
                default => $amountNet,
            };

            $priceBsExact = ($inputCurrency === 'BS')
                ? round($amountNet, 2)
                : round($amountNetUsd * $tasaBs, 2);

            // 3. Calcular el precio unitario en la moneda de la orden preservando el monto exacto ingresado
            $priceAtOrder = match ($orderCurrency) {
                'BS' => $priceBsExact,
                'COP' => ($inputCurrency === 'COP') ? round($amountNet, 2) : (ceil(($amountNetUsd * $tasaCop) / 100) * 100),
                default => ($inputCurrency === 'USD') ? round($amountNet, 2) : round($amountNetUsd, 4),
            };

            $description = trim($data['description']);

            // 4. Crear el detalle del pedido para Zoom con su nota y montos
            $orderDetail = OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $zoomProduct->id,
                'quantity' => 1,
                'price' => $priceAtOrder,
                'unit_cost' => 0.00,
                'unit_price_usd' => round($amountNetUsd, 4),
                'price_before_discount' => $priceAtOrder,
                'price_bs' => $priceBsExact,
                'price_before_discount_bs' => $priceBsExact,
                'notes' => $description,
                'product_type' => 'product',
            ]);

            // 5. Recalcular totales de la orden
            $order->updateTotals();

            // 6. Cargar relaciones requeridas por el frontend
            $order->load([
                'seller',
                'client',
                'details.product' => function ($q) {
                    $q->with('laboratory')->withSum('lots', 'quantity');
                },
                'details.dish.category',
                'details.court',
            ]);

            return [
                'order_detail' => $orderDetail,
                'order' => $order,
                'product' => $zoomProduct,
            ];
        });
    }
}
