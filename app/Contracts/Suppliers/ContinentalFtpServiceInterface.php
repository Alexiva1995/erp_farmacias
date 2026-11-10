<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface ContinentalFtpServiceInterface
{
    /**
     * Genera el contenido plano del pedido en formato TXT para Droguería Intercontinental.
     * Estructura por línea: codigo;descripcion;cantidad;precio_total
     */
    public function generateOrderContent(AutoOrder $autoOrder): string;

    /**
     * Transmite el pedido automático al servidor FTP de Droguería Intercontinental en la carpeta 'Pedidos'.
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array;
}
