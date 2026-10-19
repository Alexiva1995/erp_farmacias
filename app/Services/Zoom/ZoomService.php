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
                    'total_cost' => 0.00,
                    'order_date' => Carbon::now(),
                ]);
            }

            $orderCurrency = strtoupper($order->currency ?? 'USD');
            $inputCurrency = strtoupper($data['currency'] ?? $orderCurrency);
            $amountNet = (float) $data['amount_net']; // Monto sin IVA ingresado

            $tasaBs = (float) ($this->resourceService->getExchangeRate('BS') ?: 1);
            $tasaCop = (float) ($this->resourceService->getExchangeRate('COP') ?: 1);

            // 2. Normalizar el monto neto a USD como moneda base
            $amountNetUsd = match ($inputCurrency) {
                'BS' => ($tasaBs > 0) ? ($amountNet / $tasaBs) : $amountNet,
                'COP' => ($tasaCop > 0) ? ($amountNet / $tasaCop) : $amountNet,
                default => $amountNet,
            };

            // 3. Calcular el precio unitario en la moneda de la orden
            $priceAtOrder = match ($orderCurrency) {
                'BS' => round($amountNetUsd * $tasaBs, 2),
                'COP' => ceil(($amountNetUsd * $tasaCop) / 100) * 100,
                default => round($amountNetUsd, 2),
            };

            $description = trim($data['description']);

            // 4. Crear el detalle del pedido para Zoom con su nota y montos
            $orderDetail = OrderDetail::create([
                'order_id' => $order->id,
                'product_id' => $zoomProduct->id,
                'quantity' => 1,
                'price' => $priceAtOrder,
                'unit_cost' => 0.00,
                'unit_price_usd' => round($amountNetUsd, 2),
                'price_before_discount' => $priceAtOrder,
                'price_bs' => round($amountNetUsd * $tasaBs, 2),
                'price_before_discount_bs' => round($amountNetUsd * $tasaBs, 2),
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
