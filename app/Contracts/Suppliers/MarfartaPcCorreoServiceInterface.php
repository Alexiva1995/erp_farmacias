<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\Models\AutoOrder;

interface MarfartaPcCorreoServiceInterface
{
    /**
     * Genera el contenido del archivo de pedido bajo el protocolo PC-CORREO.
     */
    public function generateOrderContent(AutoOrder $autoOrder, ?string $clientCode = null, ?string $supplierCode = '003'): string;

    /**
     * Genera el nombre de archivo normalizado según el protocolo PC-CORREO:
     * PC{cod_proveedor}{cod_cliente}{num_odc}.txt
     */
    public function generateFilename(AutoOrder $autoOrder, ?string $clientCode = null, ?string $supplierCode = '003'): string;

    /**
     * Conecta al servidor FTP / SFTP de Mafarta y sube el archivo de pedido plano.
     *
     * @return array{success: bool, filename: string, remote_path: string, message: string}
     */
    public function sendOrderFtp(AutoOrder $autoOrder): array;

    /**
     * Transmite el pedido a la API REST de Droguerías Cobeca / Mafarta (comparadores).
     *
     * @return array{success: bool, data?: mixed, message: string}
     */
    public function sendOrderApi(AutoOrder $autoOrder): array;
}

