<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayslipsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $query;

    private $total = 0;

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
            'ID',
            'Nombres',
            'Apellidos',
            'CI',
            'Primera quincena',
            'Segunda quincena',
            'Bono de alimentación',
            'Monto mensual'
        ];
    }

    public function map($record): array
    {
        $this->total += $record->total;

        return [
            $record->id,
            $record->name,
            $record->last_name,
            $record->identification,
            $record->first_salary,
            $record->last_salary,
            $record->voucher,
            $record->total
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2979FF']
                ]
            ],
            'A:H' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $lastRow = $sheet->getHighestRow() + 1;

                $sheet->setCellValue("A{$lastRow}", 'TOTAL');
                $sheet->setCellValue("H{$lastRow}", $this->total);

                $sheet->getStyle("A{$lastRow}:H{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);
            },
        ];
    }
}
