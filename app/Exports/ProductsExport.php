<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProductsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
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
            'Producto (ID - Nombre - Lab)',
            'Principio Activo',
            'Stock',
            'PVP',
        ];
    }

    /**
     * @param Product $product
     * @return array
     */
    public function map($product): array
    {
        $lab = $product->laboratory->name ?? 'N/A';
        
        return [
            $product->id . ' - ' . $product->name . ' (' . $lab . ')',
            $product->active_ingredient,
            $product->stock_calculado ?? 0,
            $product->sale_price,
        ];
    }

    /**
     * Configuración de estilos y orientación
     */
    public function styles(Worksheet $sheet)
    {
        // Establecer orientación horizontal para PDF
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

        return [
            // Negrita para el encabezado
            1 => ['font' => ['bold' => true]],
        ];
    }
}
