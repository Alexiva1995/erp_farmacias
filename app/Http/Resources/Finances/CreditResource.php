<?php

namespace App\Http\Resources\Finances;

use App\Http\Resources\ClientResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditResource extends JsonResource
{
    /**
     * Transforma el crédito agrupado por cliente en un arreglo para el frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creditIds = [];
        if (is_array($this->credit_ids)) {
            $creditIds = array_map('intval', $this->credit_ids);
        } elseif (is_string($this->credit_ids) && !empty($this->credit_ids)) {
            $creditIds = array_map('intval', explode(',', $this->credit_ids));
        }

        $today = Carbon::today();
        $daysOverdue = 0;
        if (!empty($this->earliest_due_date)) {
            $dueDate = Carbon::parse($this->earliest_due_date)->startOfDay();
            if ($dueDate->lt($today)) {
                $daysOverdue = (int) $dueDate->diffInDays($today);
            }
        }

        $daysSinceOldest = 0;
        if (!empty($this->oldest_credit_date)) {
            $oldestDate = Carbon::parse($this->oldest_credit_date)->startOfDay();
            if ($oldestDate->lte($today)) {
                $daysSinceOldest = (int) $oldestDate->diffInDays($today);
            }
        }

        return [
            'client_id' => $this->client_id,
            'total_pending_amount' => round((float) $this->total_pending_amount, 2),
            'credit_ids' => $creditIds,
            'status' => (int) $this->status,
            'credit_date' => $this->credit_date,
            'oldest_credit_date' => $this->oldest_credit_date ?? $this->credit_date,
            'earliest_due_date' => $this->earliest_due_date ?? null,
            'active_credits_count' => (int) ($this->active_credits_count ?? count($creditIds)),
            'days_overdue' => $daysOverdue,
            'days_since_oldest' => $daysSinceOldest,
            'client' => new ClientResource($this->whenLoaded('client')),
        ];
    }
}
