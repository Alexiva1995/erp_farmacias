<?php

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
        
        $invoices = FiscalHistory::where('is_queued', true)
            ->with('order')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Transformar facturas al formato de comando para la UI
        $mappedInvoices = $invoices->map(function($inv) {
            return (object) [
                'id' => 'inv-' . $inv->id,
                'command' => 'PRINT_INVOICE',
                'payload' => ['order_id' => $inv->order_id, 'invoice_number' => $inv->invoice_number],
                'status' => $inv->invoice_number ? 'success' : 'pending',
                'response' => $inv->invoice_number ? "Factura #{$inv->invoice_number}" : "En espera...",
                'created_at' => $inv->created_at,
                'updated_at' => $inv->updated_at,
            ];
        });

        // Combinar, ordenar y limitar
        return $commands->concat($mappedInvoices)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
    }
}
