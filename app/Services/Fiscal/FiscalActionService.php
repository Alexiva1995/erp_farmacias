<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalCommandRepositoryInterface;
use App\Models\FiscalCommand;
use App\Models\FiscalHistory;
use Illuminate\Support\Collection;

class FiscalActionService
{
    public function __construct(
        protected FiscalCommandRepositoryInterface $repository
    ) {}

    /**
     * Enqueue a new fiscal command.
     */
    public function enqueueCommand(string $command, ?array $payload = null): FiscalCommand
    {
        return $this->repository->create([
            'command' => $command,
            'payload' => $payload
        ]);
    }

    /**
     * Get next command for the bridge.
     */
    public function getNextCommand(): ?FiscalCommand
    {
        return $this->repository->getNextPending();
    }

    /**
     * Confirm command execution.
     */
    public function confirmCommand(int $id, array $data): bool
    {
        return $this->repository->update($id, [
            'status' => $data['status'] ?? 'success',
            'response' => $data['response'] ?? null
        ]);
    }

    /**
     * Get recent command history merged with invoice printing history.
     */
    public function getHistory(int $limit = 20): Collection
    {
        $commands = $this->repository->getHistory($limit);
        
        $invoices = FiscalHistory::select(['id', 'order_id', 'invoice_number', 'is_queued', 'business_name', 'created_at', 'updated_at'])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        // Transformar facturas al formato de comando para la UI
        $mappedInvoices = $invoices->map(function ($inv) {
            $hasInvoice = !empty($inv->invoice_number);
            $isQueued = (bool) $inv->is_queued;

            $status = $hasInvoice ? 'success' : ($isQueued ? 'pending' : 'error');
            $response = $hasInvoice
                ? "Factura #{$inv->invoice_number}" . ($inv->business_name ? " ({$inv->business_name})" : "")
                : ($isQueued ? "En espera de impresión..." : "Pendiente / Sin emitir");

            return (object) [
                'id' => 'inv-' . $inv->id,
                'command' => 'PRINT_INVOICE',
                'payload' => ['order_id' => $inv->order_id, 'invoice_number' => $inv->invoice_number],
                'status' => $status,
                'response' => $response,
                'created_at' => $inv->created_at?->toDateTimeString(),
                'updated_at' => $inv->updated_at?->toDateTimeString(),
            ];
        });

        // Combinar, ordenar y limitar en memoria
        return $commands->concat($mappedInvoices)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }

    /**
     * Check if the fiscal bridge script is active (interacted in last 120 seconds).
     */
    public function isBridgeActive(int $thresholdSeconds = 120): array
    {
        $lastSeen = \Illuminate\Support\Facades\Cache::get('fiscal_bridge_last_seen');
        $lastInteraction = $this->repository->getLastInteractionTime();
        
        // Determinar cuál fue la fecha más reciente de interacción
        $mostRecent = null;
        if ($lastSeen && $lastInteraction) {
            $mostRecent = $lastSeen->greaterThan($lastInteraction) ? $lastSeen : $lastInteraction;
        } else {
            $mostRecent = $lastSeen ?? $lastInteraction;
        }

        $isAlive = $mostRecent ? $mostRecent->diffInSeconds(now()) <= $thresholdSeconds : false;

        return [
            'is_connected' => $isAlive,
            'last_seen' => $mostRecent?->toDateTimeString(),
            'seconds_ago' => $mostRecent ? $mostRecent->diffInSeconds(now()) : null,
        ];
    }
}
