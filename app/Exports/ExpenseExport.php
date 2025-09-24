<?php

namespace App\Exports;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with(['category', 'user']);
    }

    public function headings(): array
    {
        return [
            '#',
            'ID',
            'Nombre',
            'Categoría',
            'Monto',
            'Monto USD',
            'Moneda',
            'Cuenta',
            'Deducible',
            'Estado',
            'Fecha',
            'Usuario'
        ];
    }

    public function map($expense): array
    {
        return [
            '', // Este valor se completará automáticamente con el índice en el array
            $expense->id,
            $expense->last_name
                ? $expense->name . ' ' . $expense->last_name
                : $expense->name,
            $expense->category->name ?? 'N/A',
            $expense->amount ?? 'N/A',
            $expense->amount_usd ?? 'N/A',
            $expense->currency ?? 'N/A',
            $expense->count ?? 'N/A',
            $this->formatDeductible($expense->is_deductible),
            $expense->status ?? 'N/A',
            $expense->created_at ? $expense->created_at->format('d/m/Y') : 'N/A',
            $expense->user->username ?? 'N/A'
        ];
    }

    private function formatDeductible($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value == "1") {
            return "Si";
        }

        if ($value == "0") {
            return "No";
        }

        return 'N/A';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado (fila 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2979FF'] // Azul corporativo
                ]
            ],
            // Ajustar texto en todas las columnas
            'A:L' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ],
            // Centrar columnas numéricas y de estado
            'A' => ['alignment' => ['horizontal' => 'center']],
            'B' => ['alignment' => ['horizontal' => 'center']],
            'E' => ['alignment' => ['horizontal' => 'center']],
            'F' => ['alignment' => ['horizontal' => 'center']],
            'G' => ['alignment' => ['horizontal' => 'center']],
            'H' => ['alignment' => ['horizontal' => 'center']],
            'I' => ['alignment' => ['horizontal' => 'center']],
            'J' => ['alignment' => ['horizontal' => 'center']],
            'K' => ['alignment' => ['horizontal' => 'center']],
        ];
    }
}
