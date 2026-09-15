<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface CristmedicalsApiServiceInterface
{
    /**
     * Transmite el pedido automático a la API REST Enterprise de Cristmedicals.
     *
     * @param AutoOrder $autoOrder
     * @return array
     */
    public function sendOrderApi(AutoOrder $autoOrder): array;
}