<?php

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryDeepDiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'top_products' => collect($data['top_products'] ?? [])->map(fn ($p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'group_id' => $p->group_id ? (int) $p->group_id : null,
                'units' => (float) ($p->units ?? 0),
                'revenue' => (float) ($p->revenue ?? 0),
                'estimated_margin' => (float) ($p->estimated_margin ?? 0),
            ])->values(),
            'group_performance' => collect($data['group_performance'] ?? [])->map(fn ($g) => [
                'id' => (int) $g->id,
                'name' => (string) $g->name,
                'units' => (float) ($g->units ?? 0),
                'revenue' => (float) ($g->revenue ?? 0),
            ])->values(),
            'stats' => $data['stats'] ? [
                'avg_ticket' => (float) ($data['stats']->avg_ticket ?? 0),
                'avg_margin_percent' => (float) ($data['stats']->avg_margin_percent ?? 0),
            ] : null,
        ];
    }
}
