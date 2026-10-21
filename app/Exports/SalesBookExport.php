<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\SalesBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesBookExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected GeneralSetting $settings;
    protected bool $enableZoom;
    protected int $rowCount = 0;

    public function __construct(
        protected SalesBook $book
    ) {
        $this->settings = GeneralSetting::first() ?? new GeneralSetting();
        $this->enableZoom = $this->book->isClosed()
            ? (bool) $this->book->enable_zoom_snapshot
            : (bool) ($this->settings->enable_zoom ?? false);
    }

    public function collection()
    {
        return $this->book->entries()->orderBy('operation_number', 'asc')->get();
    }

    public function headings(): array
    {
        $taxpayerType = ($this->settings->special_taxpayer_status === 'activada' || $this->settings->enable_ce)
            ? 'CONTRIBUYENTE ESPECIAL'
            : 'CONTRIBUYENTE ORDINARIO';

        $periodText = "LIBRO DE VENTAS DESDE {$this->book->start_date->format('d-m-Y')} HASTA {$this->book->end_date->format('d-m-Y')}";

        $headings = [
            ['NOMBRE O RAZON SOCIAL: ' . ($this->settings->app_name ?: 'EMPRESA')],
            ['R.I.F.   ' . ($this->settings->app_rif ?: $this->settings->rif ?: 'J-00000000-0')],
            ['DIRECCIÓN: ' . ($this->settings->address ?: 'N/A')],
            [$periodText],
            [$taxpayerType],
            [],
        ];

        // Encabezado superior agrupado
        if ($this->enableZoom) {
            $headings[] = [
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'VENTAS INTERNAS GRAVADAS A CONTRIBUYENTES', '', '',
                'VENTAS INTERNAS GRAVADAS A NO CONTRIBUYENTES', '', '',
                '', '', '', ''
            ];
        } else {
            $headings[] = [
                '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'VENTAS INTERNAS GRAVADAS A CONTRIBUYENTES', '', '',
                '', '', '', ''
            ];
        }

        // Columnas principales
        $cols = [
            'Nro.',
            'FECHA',
            'CEDULA O R.I.F.',
            'NOMBRE O RAZÓN SOCIAL',
            'Nº PLANILLA DE EXPORTACIÓN',
            'TIPO DE DOCUMENTO',
            'Nº FACTURA',
            'Nº DE CONTROL',
            'NUMERO DE REPORTE Z',
            'Número de Factura inicial del Reporte Z',
            'Número de Factura Final del Reporte Z',
            'REG. MAQUINA',
            'Nº NOTA DE DEBITO',
            'Nº NOTA DE CREDITO',
            'Nº FACTURA AFECTADA',
            'TIPO DE TRANSACCION',
            'TOTAL VENTAS INCLUYENDO IVA',
            'VENTAS INTERNAS NO GRAVADAS',
            'BASE IMPONIBLE',
            '%IVA',
            'IVA',
        ];

        if ($this->enableZoom) {
            $cols[] = 'BASE IMPONIBLE';
            $cols[] = '%IVA';
            $cols[] = 'IVA';
        }

        $cols[] = 'IVA RETENIDO';
        $cols[] = 'Nº COMPROBANTE DE RETENCIÓN';
        $cols[] = 'FECHA DE RECEPCION COMPROBANTE DE RETENCIÓN';
        $cols[] = 'IGTF';

        $headings[] = $cols;

        return $headings;
    }

    public function map($entry): array
    {
        $this->rowCount++;

        $row = [
            $entry->operation_number,
            $entry->operation_date ? $entry->operation_date->format('Y-m-d') : '',
            $entry->identification ?? '0',
            $entry->client_name ?? 'CLIENTES VARIOS',
            $entry->export_planilla_number ?? '',
            $entry->document_type,
            $entry->invoice_number ?? '',
            $entry->control_number ?? '',
            $entry->z_report_number ?? '',
            $entry->z_first_invoice ?? '',
            $entry->z_last_invoice ?? '',
            $entry->machine_serial ?? '',
            $entry->debit_note_number ?? '',
            $entry->credit_note_number ?? '',
            $entry->affected_invoice_number ?? '',
            $entry->transaction_type,
            (float) $entry->total_sales_with_vat,
            (float) $entry->non_taxable_sales,
            (float) $entry->taxpayer_taxable_base,
            (float) $entry->taxpayer_aliquot,
            (float) $entry->taxpayer_vat,
        ];

        if ($this->enableZoom) {
            $row[] = (float) $entry->non_taxpayer_taxable_base;
            $row[] = (float) $entry->non_taxpayer_aliquot;
            $row[] = (float) $entry->non_taxpayer_vat;
        }

        $row[] = (float) $entry->vat_withheld;
        $row[] = $entry->withholding_receipt_number ?? '';
        $row[] = $entry->withholding_receipt_date ? $entry->withholding_receipt_date->format('Y-m-d') : '';
        $row[] = (float) $entry->igtf_amount;

        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true]],
            4 => ['font' => ['bold' => true, 'color' => ['rgb' => '003366']]],
            5 => ['font' => ['bold' => true]],
            8 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2B5797'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = 8 + $this->rowCount;
                $totalRow = $lastDataRow + 1;

                $lastColLetter = $this->enableZoom ? 'AB' : 'Y';

                // Fila de TOTALES
                $sheet->setCellValue("P{$totalRow}", 'TOTALES');
                $sheet->getStyle("P{$totalRow}")->getFont()->setBold(true);

                $sheet->setCellValue("Q{$totalRow}", (float) $this->book->total_sales_with_vat);
                $sheet->setCellValue("R{$totalRow}", (float) $this->book->total_non_taxable_sales);
                $sheet->setCellValue("S{$totalRow}", (float) $this->book->total_taxpayer_taxable_base);
                $sheet->setCellValue("U{$totalRow}", (float) $this->book->total_taxpayer_vat);

                if ($this->enableZoom) {
                    $sheet->setCellValue("V{$totalRow}", (float) $this->book->total_non_taxpayer_taxable_base);
                    $sheet->setCellValue("X{$totalRow}", (float) $this->book->total_non_taxpayer_vat);
                    $sheet->setCellValue("Y{$totalRow}", (float) $this->book->total_vat_withheld);
                    $sheet->setCellValue("AB{$totalRow}", (float) $this->book->total_igtf);

                    // Estilo amarillo en columnas Zoom
                    $sheet->getStyle("V8:X{$lastDataRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF2CC'],
                    ]);
                } else {
                    $sheet->setCellValue("V{$totalRow}", (float) $this->book->total_vat_withheld);
                    $sheet->setCellValue("Y{$totalRow}", (float) $this->book->total_igtf);
                }

                $sheet->getStyle("P{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                    ],
                ]);

                // Cuadro RESUMEN DE LIBRO DE VENTAS
                $summaryStart = $totalRow + 3;
                $sheet->setCellValue("C{$summaryStart}", 'RESUMEN DE LIBRO DE VENTAS');
                $sheet->getStyle("C{$summaryStart}")->getFont()->setBold(true)->setSize(11);

                $headerRow = $summaryStart + 1;
                $sheet->setCellValue("C{$headerRow}", 'Concepto');
                $sheet->setCellValue("H{$headerRow}", 'ITEM');
                $sheet->setCellValue("I{$headerRow}", 'Base Imponible');
                $sheet->setCellValue("M{$headerRow}", 'ITEM');
                $sheet->setCellValue("N{$headerRow}", 'Debito Fiscal');
                $sheet->setCellValue("Q{$headerRow}", 'IVA Retenido');
                $sheet->setCellValue("T{$headerRow}", 'I.G.T.F.');

                $sheet->getStyle("C{$headerRow}:T{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ]);

                $summaryData = [
                    ['Total Ventas Internas no Gravadas:', '40', (float) $this->book->total_non_taxable_sales, '-', 0, 0, 0],
                    ['Total Ventas Exportación Afectas solo Alicuota General:', '41', 0, '-', 0, 0, 0],
                    ['Total Ventas Internas Afectas solo Alicuota General:', '42', (float) $this->book->total_taxpayer_taxable_base, '43', (float) $this->book->total_taxpayer_vat, (float) $this->book->total_vat_withheld, 0],
                    ['Total Ventas Internas Afectas en Alicuota General + Adicional:', '442', 0, '452', 0, 0, 0],
                    ['Total Ventas Internas Afectas solo Alicuota Reducida:', '443', (float) $this->book->total_non_taxpayer_taxable_base, '453', (float) $this->book->total_non_taxpayer_vat, 0, 0],
                    ['Total Ajustes a Periodos Anteriores', '46', 0, '47', 0, 0, 0],
                ];

                $r = $headerRow + 1;
                foreach ($summaryData as $sRow) {
                    $sheet->setCellValue("C{$r}", $sRow[0]);
                    $sheet->setCellValue("H{$r}", $sRow[1]);
                    $sheet->setCellValue("I{$r}", $sRow[2]);
                    $sheet->setCellValue("M{$r}", $sRow[3]);
                    $sheet->setCellValue("N{$r}", $sRow[4]);
                    $sheet->setCellValue("Q{$r}", $sRow[5]);
                    $sheet->setCellValue("T{$r}", $sRow[6]);
                    $r++;
                }

                // Fila Total Ventas y Debitos Fiscales del Período
                $sheet->setCellValue("C{$r}", 'Total Ventas y Debitos Fiscales del Período');
                $totBase = (float) $this->book->total_non_taxable_sales + (float) $this->book->total_taxpayer_taxable_base + (float) $this->book->total_non_taxpayer_taxable_base;
                $totDebit = (float) $this->book->total_taxpayer_vat + (float) $this->book->total_non_taxpayer_vat;
                $sheet->setCellValue("I{$r}", $totBase);
                $sheet->setCellValue("N{$r}", $totDebit);
                $sheet->setCellValue("Q{$r}", (float) $this->book->total_vat_withheld);
                $sheet->setCellValue("T{$r}", 0);

                $sheet->getStyle("C{$r}:T{$r}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Fila TOTAL I.G.T.F.
                $r++;
                $sheet->setCellValue("C{$r}", 'TOTAL I.G.T.F.');
                $sheet->setCellValue("T{$r}", (float) $this->book->total_igtf);
                $sheet->getStyle("C{$r}:T{$r}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                    ],
                ]);
            },
        ];
    }
}
