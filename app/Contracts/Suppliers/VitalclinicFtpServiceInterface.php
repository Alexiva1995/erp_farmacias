<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface VitalclinicFtpServiceInterface
{
    /**
     * Genera el contenido en texto plano del archivo de pedido con formato delimitado por punto y coma (;).
     * Estructura: codigo_producto;descripcion_producto;cantidad;precio_unitario
     */
    public function generateOrderContent(AutoOrder $autoOrder): string;

    /**
     * Conecta al servidor FTP de Vitalclinic y sube el archivo de pedido a la carpeta 'Pedidos'.
     * Nombre: {codigo_cliente(4)}P{correlativo(6)}.txt
     *
     * @return array{success: bool, filename: string, remote_path: string, message: string}
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array;
}
