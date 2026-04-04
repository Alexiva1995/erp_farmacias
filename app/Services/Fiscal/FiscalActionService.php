<?php

namespace App\Services\Fiscal;

use App\Contracts\Fiscal\FiscalCommandRepositoryInterface;
use App\Models\FiscalCommand;
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
     * Get recent command history.
     */
    public function getHistory(int $limit = 20): Collection
    {
        return $this->repository->getHistory($limit);
    }
}
