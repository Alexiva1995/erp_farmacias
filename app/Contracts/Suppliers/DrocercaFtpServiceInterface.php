<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface DrocercaFtpServiceInterface
{
    /**
     * Genera el contenido del archivo plano de pedido .txt según el protocolo de Drocerca:
     * Separador de campos: ;
     * Estructura: Codigo;Descripcion;Cantidad;Precio;Sede;Cod_cli
     */
    public function generateOrderContent(AutoOrder $autoOrder, ?string $clientCode = null, int $sede = 1): string;

    /**
     * Conecta al servidor FTP de Drocerca y sube el archivo de pedido a la carpeta 'pedidos'.
     */
    public function sendOrderFtp(AutoOrder $autoOrder, int $sede = 1): array;

    /**
     * Descarga y parsea el archivo de inventario consolidado 'inventario.txt' desde la raíz del FTP.
     */
    public function fetchInventoryFtp(?string $host = null, ?string $user = null, ?string $password = null): array;
}
