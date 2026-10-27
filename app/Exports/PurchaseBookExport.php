<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\PurchaseBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseBookExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithEvents
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

    public function columnFormats(): array
    {
        $currencyFormat = '#,##0.00';

        $formats = [
            'G' => NumberFormat::FORMAT_TEXT, // Número de Factura
            'H' => NumberFormat::FORMAT_TEXT, // Número de Control
            'I' => NumberFormat::FORMAT_TEXT, // Número de Nota Débito
            'J' => NumberFormat::FORMAT_TEXT, // Número de Nota Crédito
            'K' => NumberFormat::FORMAT_TEXT, // Número de Factura Afectada
            'N' => $currencyFormat,           // Total Compras incluyendo IVA
            'O' => $currencyFormat,           // Compras sin derecho a Crédito
            'P' => $currencyFormat,           // Base Imponible
            'Q' => '0.00%',                   // % Alicuota
            'R' => $currencyFormat,           // IVA General
        ];

        if ($this->enableZoom) {
            $formats['S'] = $currencyFormat;  // Base Imponible Zoom
            $formats['T'] = '0.00%';          // % Alicuota Zoom
            $formats['U'] = $currencyFormat;  // IVA General Zoom
            $formats['V'] = $currencyFormat;  // Iva Retenido
            $formats['W'] = NumberFormat::FORMAT_TEXT; // Número de Comprobante
        } else {
            $formats['S'] = $currencyFormat;  // Iva Retenido
            $formats['T'] = NumberFormat::FORMAT_TEXT; // Número de Comprobante
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColLetter = $this->enableZoom ? 'V' : 'S';

        // Configuración de impresión: Hoja horizontal y ajustar a 1 página de ancho
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_PAGE_LAYOUT);

        // Combinar celdas del membrete superior para que no ensanchen la columna A
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->mergeCells("A3:{$lastColLetter}3");
        $sheet->mergeCells("A4:{$lastColLetter}4");
        $sheet->mergeCells("A5:{$lastColLetter}5");

        // Anchos de columna específicos y optimizados para impresión
        $columnWidths = [
            'A' => 6,   // Nro.
            'B' => 11,  // Fecha de Operación
            'C' => 12,  // R.I.F.
            'D' => 24,  // Nombre o Razon Social
            'E' => 11,  // C80 o C81
            'F' => 11,  // Expediente Importación
            'G' => 12,  // Número de Factura
            'H' => 12,  // Número de Control
            'I' => 10,  // Número de Nota Débito
            'J' => 10,  // Número de Nota Crédito
            'K' => 12,  // Número de Factura Afectada
            'L' => 9,   // Tipo de Documento
            'M' => 8,   // Tipo Transacción
            'N' => 14,  // Total Compras incluyendo IVA
            'O' => 12,  // Compras sin derecho a Crédito
            'P' => 13,  // Base Imponible
            'Q' => 8,   // % Alicuota
            'R' => 12,  // IVA General
        ];

        if ($this->enableZoom) {
            $columnWidths['S'] = 13; // Base Imponible Zoom
            $columnWidths['T'] = 8;  // % Alicuota Zoom
            $columnWidths['U'] = 12; // IVA General Zoom
            $columnWidths['V'] = 12; // Iva Retenido
            $columnWidths['W'] = 14; // Número de Comprobante
        } else {
            $columnWidths['S'] = 12; // Iva Retenido
            $columnWidths['T'] = 14; // Número de Comprobante
        }

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Altura de la fila de cabecera para permitir texto en varias líneas
        $sheet->getRowDimension(8)->setRowHeight(40);

        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
            2 => ['font' => ['bold' => true, 'size' => 10]],
            3 => ['font' => ['bold' => true, 'size' => 9]],
            4 => ['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003366']]],
            5 => ['font' => ['bold' => true, 'size' => 9]],
            8 => [
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
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
            // Ajustar texto y tamaño compacto para las filas de datos
            "A9:{$lastColLetter}500" => [
                'font' => ['size' => 8],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
            // Alineación a la derecha para Número de Factura (G) y Número de Control (H)
            "G9:H500" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            // Alineación centrada para códigos y comprobantes
            "I9:K500" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
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

                // Formato numérico para el cuadro de Resumen
                $currencyFormat = '#,##0.00';
                $sheet->getStyle("H" . ($headerRow + 1) . ":N{$r}")->getNumberFormat()->setFormatCode($currencyFormat);
            },
        ];
    }
}
