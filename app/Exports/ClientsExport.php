<?php

namespace App\Exports;

use App\Models\Client;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with('company'); // Carga la relación company
    }

    public function headings(): array
    {
        return [
            '#',
            'Identificación',
            'Tipo ID',
            'Nombre Completo',
            'Teléfono',
            'Email',
            'Empresa',
            'Dirección',
            'Fecha Nacimiento'
        ];
    }

    public function map($client): array
    {
        $fecha = $client->birthdate;
        $formato = "N/A";
        if ($fecha) {
            $fecha = new DateTime($fecha);
            $formato = $fecha->format("d/m/Y");
        }

        return [
            $client->id,
            $client->identification,
            $this->formatIdentificationType($client->identification_type),
            $client->last_name
                ? $client->name . ' ' . $client->last_name
                : $client->name,
            $client->phone ?? 'N/A',
            $client->email ?? 'N/A',
            $client->company->name ?? 'N/A',
            $client->address ?? 'N/A',
            $formato,
        ];
    }

    private function formatIdentificationType($type): string
    {
        $types = [
            Client::IDENTIFICATION_TYPE_VENEZOLANO => 'V-',
            Client::IDENTIFICATION_TYPE_JURIDICO => 'J-',
            Client::IDENTIFICATION_TYPE_GOBIERNO => 'G-',
            Client::IDENTIFICATION_TYPE_EXTRANJERO => 'E-'
        ];

        return $types[$type] ?? $type;
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
            // Ajustar texto en todas las columnas (A-I)
            'A:I' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ],
            // Opcional: Formato para fechas
            'I' => [
                'numberFormat' => [
                    'formatCode' => 'dd/mm/yyyy'
                ]
            ]
        ];
    }
}
