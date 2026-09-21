<?php

namespace App\Http\Resources\Fiscal;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalCommandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at;
        if ($createdAt instanceof \DateTimeInterface) {
            $formattedCreated = $createdAt->format('d/m/Y h:i A');
        } elseif (is_string($createdAt) && !empty($createdAt)) {
            $formattedCreated = Carbon::parse($createdAt)->format('d/m/Y h:i A');
        } else {
            $formattedCreated = null;
        }

        $updatedAt = $this->updated_at;
        if ($updatedAt instanceof \DateTimeInterface) {
            $formattedUpdated = $updatedAt->format('d/m/Y h:i A');
        } elseif (is_string($updatedAt) && !empty($updatedAt)) {
            $formattedUpdated = Carbon::parse($updatedAt)->format('d/m/Y h:i A');
        } else {
            $formattedUpdated = null;
        }

        return [
            'id' => $this->id,
            'command' => $this->command,
            'payload' => $this->payload,
            'status' => $this->status,
            'response' => $this->response,
            'created_at' => $formattedCreated,
            'updated_at' => $formattedUpdated,
        ];
    }
}
