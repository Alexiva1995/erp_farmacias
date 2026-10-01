<?php

declare(strict_types=1);

namespace App\Contracts\Fiscal;

interface FactoryFiscalServiceInterface
{
    /**
     * Verificar conectividad con la impresora The Factory HKA a través del socket TCP.
     *
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function checkConnection(?string $ip = null, ?int $port = null): array;

    /**
     * Imprimir factura fiscal en la impresora The Factory HKA.
     *
     * @param int $fiscalHistoryId
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function printInvoice(int $fiscalHistoryId, ?string $ip = null, ?int $port = null): array;

    /**
     * Imprimir nota de crédito fiscal en The Factory HKA.
     *
     * @param array $data
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function printCreditNote(array $data, ?string $ip = null, ?int $port = null): array;

    /**
     * Imprimir reporte X en The Factory HKA.
     *
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function printReportX(?string $ip = null, ?int $port = null): array;

    /**
     * Imprimir reporte Z en The Factory HKA y actualizar contadores.
     *
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function printReportZ(?string $ip = null, ?int $port = null): array;

    /**
     * Obtener el estado S1 de la impresora The Factory HKA.
     *
     * @param string|null $ip
     * @param int|null $port
     * @return array
     */
    public function getStatusS1(?string $ip = null, ?int $port = null): array;
}
