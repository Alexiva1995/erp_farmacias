<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CycleDetailProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? (object) $this->resource : $this->resource;

        $user = $item->user ?? null;
        $supervisor = $item->supervisor ?? null;

        $userUsername = null;
        $userEmail = null;
        $userName = null;
        $userLastName = null;

        if ($user) {
            $userUsername = is_array($user) ? ($user['username'] ?? null) : ($user->username ?? null);
            $userEmail = is_array($user) ? ($user['email'] ?? null) : ($user->email ?? null);
            $userName = is_array($user)
                ? ($user['employee_name'] ?? data_get($user, 'employee.name'))
                : ($user->employee_name ?? $user->employee?->name);
            $userLastName = is_array($user)
                ? ($user['employee_last_name'] ?? data_get($user, 'employee.last_name'))
                : ($user->employee_last_name ?? $user->employee?->last_name);
        }

        $supervisorUsername = null;
        $supervisorEmail = null;
        $supervisorName = null;
        $supervisorLastName = null;

        if ($supervisor) {
            $supervisorUsername = is_array($supervisor) ? ($supervisor['username'] ?? null) : ($supervisor->username ?? null);
            $supervisorEmail = is_array($supervisor) ? ($supervisor['email'] ?? null) : ($supervisor->email ?? null);
            $supervisorName = is_array($supervisor)
                ? ($supervisor['employee_name'] ?? data_get($supervisor, 'employee.name'))
                : ($supervisor->employee_name ?? $supervisor->employee?->name);
            $supervisorLastName = is_array($supervisor)
                ? ($supervisor['employee_last_name'] ?? data_get($supervisor, 'employee.last_name'))
                : ($supervisor->employee_last_name ?? $supervisor->employee?->last_name);
        }

        return [
            'id' => $item->id ?? null,
            'source_type' => $item->source_type ?? 'product_count',
            'product_id' => (int) ($item->product_id ?? 0),
            'system_quantity' => (float) ($item->system_quantity ?? 0),
            'final_quantity' => (float) ($item->final_quantity ?? $item->counted_quantity ?? 0),
            'counted_quantity' => (float) ($item->counted_quantity ?? 0),
            'discrepancy' => (float) ($item->discrepancy ?? 0),
            'created_at' => $item->created_at ?? null,
            'product' => isset($item->product) ? [
                'id' => $item->product['id'] ?? $item->product->id ?? null,
                'name' => $item->product['name'] ?? $item->product->name ?? '',
                'photo_url' => $item->product['photo_url'] ?? $item->product->photo_url ?? null,
                'unit_cost' => (float) ($item->product['unit_cost'] ?? $item->product->unit_cost ?? 0),
                'sale_price' => (float) ($item->product['sale_price'] ?? $item->product->sale_price ?? 0),
                'psychotropic' => (bool) ($item->product['psychotropic'] ?? $item->product->psychotropic ?? false),
                'is_colombian_origin' => (int) ($item->product['is_colombian_origin'] ?? $item->product->is_colombian_origin ?? 0),
                'active_ingredient' => $item->product['active_ingredient'] ?? $item->product->active_ingredient ?? null,
                'laboratory' => isset($item->product['laboratory']) ? [
                    'name' => $item->product['laboratory']['name'] ?? $item->product->laboratory->name ?? ''
                ] : null
            ] : null,
            'user' => $user ? [
                'email' => $userEmail ?? '',
                'username' => $userUsername ?? '',
                'employee_name' => $userName ?? '',
                'employee_last_name' => $userLastName ?? '',
            ] : null,
            'supervisor' => $supervisor ? [
                'email' => $supervisorEmail ?? '',
                'username' => $supervisorUsername ?? '',
                'employee_name' => $supervisorName ?? '',
                'employee_last_name' => $supervisorLastName ?? '',
            ] : null,
        ];
    }
}
