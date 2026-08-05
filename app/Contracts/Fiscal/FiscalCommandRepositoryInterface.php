<?php

namespace App\Contracts\Fiscal;

use App\Models\FiscalCommand;
use Illuminate\Support\Collection;

interface FiscalCommandRepositoryInterface
{
    /**
     * Create a new fiscal command.
     */
    public function create(array $data): FiscalCommand;

    /**
     * Get the next pending fiscal command.
     */
    public function getNextPending(): ?FiscalCommand;

    /**
     * Update a fiscal command status/response.
     */
    public function update(int $id, array $data): bool;

    /**
     * Get recent history of commands.
     */
    public function getHistory(int $limit = 20): Collection;

    /**
     * Get timestamp of last bridge interaction.
     */
    public function getLastInteractionTime(): ?\Illuminate\Support\Carbon;
}
