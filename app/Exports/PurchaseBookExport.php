<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\PurchaseBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseBookExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected GeneralSetting $settings;
    protected bool $enableZoom;
    protected int $rowCount = 0;

    public function __construct(
        protected PurchaseBook $book
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

        $periodText = "LIBRO DE COMPRAS DESDE {$this->book->start_date->format('d-m-Y')} HASTA {$this->book->end_date->format('d-m-Y')}";

        $headings = [
            ['NOMBRE O RAZON SOCIAL: ' . ($this->settings->app_name ?: 'EMPRESA')],
            ['R.I.F.   ' . ($this->settings->app_rif ?: $this->settings->rif ?: 'J-00000000-0')],
            ['DIRECCIÓN: ' . ($this->settings->address ?: 'N/A')],
            [$periodText],
            [$taxpayerType],
            [], // Fila vacía
        ];

        // Encabezado superior agrupado
        if ($this->enableZoom) {
            $headings[] = [
                '', '', '', '', '', '', '', '', '', '', '', '', '',
                'COMPRAS INTERNAS O IMPORTACIONES', '', '',
                'COMPRAS INTERNAS O IMPORTACIONES (ZOOM 8%)', '', '',
                '', ''
            ];
        } else {
            $headings[] = [
                '', '', '', '', '', '', '', '', '', '', '', '', '',
                'COMPRAS INTERNAS O IMPORTACIONES', '', '',
                '', ''
            ];
        }

        // Columnas principales
        $cols = [
            'Nro.',
            'Fecha de Operación',
            'R.I.F.',
            'Nombre o Razon Social',
            'Número Planilla Importación (C80 o C81)',
            'Número Expediente Importación',
            'Número de Factura',
            'Número de Control',
            'Número de Nota Débito',
            'Número de Nota Crédito',
            'Número de Factura Afectada',
            'Tipo de Documento',
            'Tipo Transacción',
            'Total Compras incluyendo IVA',
            'Compras sin derecho a Crédito',
            'Base Imponible',
            '% Alicuota',
            'IVA General',
        ];

        if ($this->enableZoom) {
            $cols[] = 'Base Imponible';
            $cols[] = '% Alicuota';
            $cols[] = 'IVA General';
        }

        $cols[] = 'Iva Retenido';
        $cols[] = 'Número de Comprobante';

        $headings[] = $cols;

        return $headings;
    }

    public function map($entry): array
    {
        $this->rowCount++;

        $row = [
            $entry->operation_number,
            $entry->operation_date ? $entry->operation_date->format('Y-m-d') : '',
            $entry->rif,
            $entry->supplier_name,
            $entry->import_planilla_number ?? '',
            $entry->import_dossier_number ?? '',
            $entry->invoice_number ?? '',
            $entry->control_number ?? '',
            $entry->debit_note_number ?? '',
            $entry->credit_note_number ?? '',
            $entry->affected_invoice_number ?? '',
            $entry->document_type,
            $entry->transaction_type,
            (float) $entry->total_purchases_with_vat,
            (float) $entry->exempt_purchases,
            (float) $entry->general_taxable_base,
            (float) $entry->general_aliquot,
            (float) $entry->general_vat,
        ];

        if ($this->enableZoom) {
            $row[] = (float) $entry->reduced_taxable_base;
            $row[] = (float) $entry->reduced_aliquot;
            $row[] = (float) $entry->reduced_vat;
        }

        $row[] = (float) $entry->vat_withheld;
        $row[] = $entry->withholding_receipt_number ?? '';

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

                $lastColLetter = $this->enableZoom ? 'V' : 'S';

                // Fila de TOTAL
                $sheet->setCellValue("M{$totalRow}", 'TOTAL');
                $sheet->getStyle("M{$totalRow}")->getFont()->setBold(true);

                // Fórmulas de suma o valores acumulados del modelo
                $sheet->setCellValue("N{$totalRow}", (float) $this->book->total_purchases_with_vat);
                $sheet->setCellValue("O{$totalRow}", (float) $this->book->total_exempt_purchases);
                $sheet->setCellValue("P{$totalRow}", (float) $this->book->total_general_taxable_base);
                $sheet->setCellValue("R{$totalRow}", (float) $this->book->total_general_vat);

                if ($this->enableZoom) {
                    $sheet->setCellValue("S{$totalRow}", (float) $this->book->total_reduced_taxable_base);
                    $sheet->setCellValue("U{$totalRow}", (float) $this->book->total_reduced_vat);
                    $sheet->setCellValue("V{$totalRow}", (float) $this->book->total_vat_withheld);

                    // Estilo amarillo en columnas Zoom
                    $sheet->getStyle("S8:U{$lastDataRow}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF2CC'],
                    ]);
                } else {
                    $sheet->setCellValue("S{$totalRow}", (float) $this->book->total_vat_withheld);
                }

                $sheet->getStyle("M{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                    ],
                ]);

                // Cuadro RESUMEN LIBRO DE COMPRAS
                $summaryStart = $totalRow + 3;
                $sheet->setCellValue("C{$summaryStart}", 'RESUMEN LIBRO DE COMPRAS');
                $sheet->getStyle("C{$summaryStart}")->getFont()->setBold(true)->setSize(11);

                $headerRow = $summaryStart + 1;
                $sheet->setCellValue("C{$headerRow}", 'Concepto');
                $sheet->setCellValue("H{$headerRow}", 'Base Imponible');
                $sheet->setCellValue("J{$headerRow}", 'Crédito Fiscal');
                $sheet->setCellValue("L{$headerRow}", 'Retenciones IVA');
                $sheet->setCellValue("N{$headerRow}", 'Anticipo IVA');

                if ($this->enableZoom) {
                    $sheet->setCellValue("P{$headerRow}", 'ESTA ES CUANDO ZOOM FACTURA POR EL 8%');
                }

                $sheet->getStyle("C{$headerRow}:N{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0E0E0'],
                    ],
                ]);

                $summaryData = [
                    ['Total Compras Exentas y/o Sin Derecho a Crédito Fiscal:', (float) $this->book->total_exempt_purchases, 0, 0, 0],
                    ['Total Compras Importación Afectas solo Alicuota General:', 0, 0, 0, 0],
                    ['Total Compras Importación Afectas en Alicuota General + Adicional:', 0, 0, 0, 0],
                    ['Total Compras Importación Afectas solo Alicuota Reducida:', 0, 0, 0, 0],
                    ['Total Compras Internas Afectas solo Alicuota General:', (float) $this->book->total_general_taxable_base, (float) $this->book->total_general_vat, (float) $this->book->total_vat_withheld, 0],
                    ['Total Compras Internas Afectas en Alicuota General + Adicional:', 0, 0, 0, 0],
                    ['Total Compras Internas Afectas solo Alicuota Reducida:', (float) $this->book->total_reduced_taxable_base, (float) $this->book->total_reduced_vat, 0, 0],
                ];

                $r = $headerRow + 1;
                foreach ($summaryData as $sRow) {
                    $sheet->setCellValue("C{$r}", $sRow[0]);
                    $sheet->setCellValue("H{$r}", $sRow[1]);
                    $sheet->setCellValue("J{$r}", $sRow[2]);
                    $sheet->setCellValue("L{$r}", $sRow[3]);
                    $sheet->setCellValue("N{$r}", $sRow[4]);
                    $r++;
                }

                // Fila TOTALES Resumen
                $sheet->setCellValue("C{$r}", 'TOTALES');
                $totalBase = (float) $this->book->total_exempt_purchases + (float) $this->book->total_general_taxable_base + (float) $this->book->total_reduced_taxable_base;
                $totalCredit = (float) $this->book->total_general_vat + (float) $this->book->total_reduced_vat;
                $sheet->setCellValue("H{$r}", $totalBase);
                $sheet->setCellValue("J{$r}", $totalCredit);
                $sheet->setCellValue("L{$r}", (float) $this->book->total_vat_withheld);
                $sheet->setCellValue("N{$r}", 0);

                $sheet->getStyle("C{$r}:N{$r}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                    ],
                ]);
            },
        ];
    }
}
