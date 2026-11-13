<?php

namespace App\Contracts;

use App\Models\KitchenWaste;
use Illuminate\Http\UploadedFile;

interface KitchenWasteServiceInterface
{
    /**
     * Lista las mermas registradas paginadas con métricas de resumen.
     */
    public function listWastes(array $filters = []): array;

    /**
     * Registra un reporte de merma individual utilizando el costo unificado de la tabla products.
     */
    public function recordWaste(array $data, int $userId, ?UploadedFile $photo = null): KitchenWaste;

    /**
     * Registra múltiples renglones de merma (Hoja de Merma de turno) en una sola transacción.
     *
     * @return array<int, KitchenWaste>
     */
    public function recordBatchWaste(array $items, int $userId, ?UploadedFile $photo = null, ?int $defaultStationId = null): array;

    /**
     * Aprueba una merma pendiente, descuenta del inventario y registra el movimiento como pérdida.
     */
    public function approveWaste(KitchenWaste $waste, int $approverId): KitchenWaste;

    /**
     * Rechaza una merma pendiente sin afectar el inventario.
     */
    public function rejectWaste(KitchenWaste $waste, int $approverId, ?string $reason = null): KitchenWaste;
}
