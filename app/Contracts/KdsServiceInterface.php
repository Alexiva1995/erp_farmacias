<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\KitchenStatusEnum;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Database\Eloquent\Collection;

interface KdsServiceInterface
{
    /**
     * Comandar platos de una orden hacia la cocina (draft -> queued).
     */
    public function sendOrderToKitchen(Order $order, ?array $detailIds = null): array;

    /**
     * Obtener comandas y platos activos en KDS filtrados opcionalmente por estación.
     */
    public function getActiveTickets(?int $stationId = null): Collection;

    /**
     * Actualizar el estado de preparación de un plato en cocina.
     */
    public function updateItemStatus(OrderDetail $detail, KitchenStatusEnum $status): OrderDetail;

    /**
     * Despachar (Bump) todos los ítems activos de una orden en una estación determinada.
     */
    public function bumpTicket(Order $order, ?int $stationId = null): Order;
}
