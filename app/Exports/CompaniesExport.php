<?php

namespace App\Exports;

use App\Models\Company;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompaniesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            '#',
            'Identificación',
            'Nombre',
            'Tipo',
            'Dirección',
            'Fecha Registro'
        ];
    }

    public function map($company): array
    {
        return [
            $company->id,
            $company->identification,
            $company->name,
            $this->formatType($company->type_company),
            $company->address ?? 'N/A',
            $company->created_at->format('d-m-Y')
        ];
    }

    private function formatType($type): string
    {
        return match ($type) {
            'company' => 'Empresa',
            'clinic' => 'Clínica',
            default => $type
        };
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
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
            // Ajustar texto en columnas
            'A:F' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ]
        ];
    }
}
