<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface DromegaFtpServiceInterface
{
    /**
     * Genera el contenido plano del pedido en formato TXT para DROMEGA.
     * Estructura: codigo_producto(6);descripcion;cantidad;precio_unitario
     */
    public function generateOrderContent(AutoOrder $autoOrder): string;

    /**
     * Transmite el pedido automático al servidor FTP de DROMEGA en la carpeta 'Pedidos'.
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array;

    /**
     * Descarga y parsea el archivo de inventario consolidado 'inventariomerida.txt'.
     */
    public function fetchInventoryFtp(?string $host = null, ?string $user = null, ?string $password = null): array;
}
