<?php

namespace App\Contracts;

use App\Models\Order;

interface SplitCheckServiceInterface
{
    /**
     * Divide una orden por asientos o lista específica de ítems creando órdenes hijas.
     *
     * @param Order $order
     * @param array $splits Array de grupos: [['seat_number' => 1, 'detail_ids' => [1, 2]], ...]
     * @return array Array de órdenes resultantes
     */
    public function splitBySeatsOrItems(Order $order, array $splits): array;

    /**
     * Divide el saldo de una orden en N partes iguales creando comprobantes/sub-órdenes.
     *
     * @param Order $order
     * @param int $parts
     * @return array
     */
    public function splitEqually(Order $order, int $parts): array;
}
