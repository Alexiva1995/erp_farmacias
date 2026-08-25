<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface DronenaEdiServiceInterface
{
    /**
     * Genera el contenido en texto plano del archivo de pedido con estructura D000, D001, D002, D003.
     */
    public function generateOrderContent(AutoOrder $autoOrder): string;

    /**
     * Conecta al servidor FTP de Dronena y sube el archivo FACTUXX al directorio correspondiente del cliente.
     *
     * @return array{success: bool, filename: string, message: string}
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array;
}
