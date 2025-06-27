<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Database\Eloquent\Builder;

class ProductsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct(Builder $query)
    {
        // Recibimos la consulta ya filtrada desde el controlador
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Producto',
            'Principio Activo',
            'Laboratorio',
            'Origen',
            'Precio Venta',
            'Stock Válido',
            'Próximo Vencimiento',
        ];
    }

    /**
     * @param Product $product
     * @return array
     */
    public function map($product): array
    {
        return [
            $product->id,
            $product->name,
            $product->active_ingredient,
            $product->laboratory->name ?? 'N/A',
            $product->origin->name ?? 'N/A',
            $product->sale_price,
            $this->calculateValidStock($product),
            $this->nextExpirationDate($product),
        ];
    }

    private function calculateValidStock($product): int
    {
        return $product->lots
            ->where('expiration_date', '>=', now()->startOfDay())
            ->sum('quantity');
    }

    private function nextExpirationDate($product): string
    {
        $nextLot = $product->lots
            ->where('expiration_date', '>=', now()->startOfDay())
            ->sortBy('expiration_date')
            ->first();

        return $nextLot ? $nextLot->expiration_date->format('Y-m-d') : 'N/A';
    }
}
