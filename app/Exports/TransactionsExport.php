<?php

declare(strict_types=1);

namespace App\Exports;

use App\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        // La query ya trae user_name y category_name como columnas directas (sin relaciones)
        return $this->query;
    }

    public function title(): string
    {
        return 'Flujo de Caja';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Usuario',
            'Descripción',
            'Movimiento',
            'Método',
            'Monto',
            'Moneda',
            'Tasa de Cambio',
            'Categoría',
        ];
    }

    public function map($row): array
    {
        $typeEnum = TransactionType::tryFrom((string) $row->type);

        return [
            $row->id,
            $row->transaction_date,
            $row->user_name ?? 'N/A',
            $row->description,
            strtoupper((string) $row->movement_type) === 'IN' ? 'ENTRADA (+)' : 'SALIDA (-)',
            $typeEnum?->label() ?? $row->type,
            (float) $row->amount,
            $row->currency,
            (float) ($row->exchange_rate ?? 1.0),
            $row->category_name ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Encabezado en negrita y con color de fondo suave
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => 'solid', 'color' => ['argb' => 'FF1565C0']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
