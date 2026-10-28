<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\SalesBook;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesBookExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
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
            // Fila 6 eliminada (no necesaria)
        ];

        // Fila 6: Encabezado superior agrupado
        if ($this->enableZoom) {
            $headings[] = [
                'DATOS GENERALES DE LA TRANSACCIÓN', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'TOTALES', '',
                'VENTAS GRAVADAS (16%)', '', '',
                'VENTAS A NO CONTRIBUYENTES (ZOOM 8%)', '', '',
                'RETENCIONES', '', '',
                'IGTF'
            ];
        } else {
            $headings[] = [
                'DATOS GENERALES DE LA TRANSACCIÓN', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'TOTALES', '',
                'VENTAS GRAVADAS (16%)', '', '',
                'RETENCIONES', '', '',
                'IGTF'
            ];
        }

        // Fila 7: Columnas principales
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
            1 => ['font' => ['bold' => true, 'size' => 11]],
            2 => ['font' => ['bold' => true, 'size' => 9]],
            3 => ['font' => ['bold' => true, 'size' => 9]],
            4 => ['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '003366']]],
            5 => ['font' => ['bold' => true, 'size' => 9]],
            6 => [
                'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1A365D'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            7 => [
                'font' => ['bold' => true, 'size' => 7.5, 'color' => ['rgb' => 'FFFFFF']],
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
                $lastDataRow = 7 + $this->rowCount;
                $totalRow = $lastDataRow + 1;

                $lastColLetter = $this->enableZoom ? 'AB' : 'Y';

                // Configuración de página para impresión en 1 sola hoja de ancho horizontal
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_NORMAL);
                $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.3)->setRight(0.3);

                // Combinar celdas del membrete superior a lo ancho de la tabla
                for ($row = 1; $row <= 5; $row++) {
                    $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Combinar celdas del encabezado de agrupaciones (fila 6)
                $sheet->mergeCells('A6:P6');
                $sheet->mergeCells('Q6:R6');

                if ($this->enableZoom) {
                    $sheet->mergeCells('S6:U6');
                    $sheet->mergeCells('V6:X6');
                    $sheet->mergeCells('Y6:AA6');
                } else {
                    $sheet->mergeCells('S6:U6');
                    $sheet->mergeCells('V6:X6');
                }

                // Ajustar altura de filas de encabezado
                $sheet->getRowDimension(6)->setRowHeight(22);
                $sheet->getRowDimension(7)->setRowHeight(38);

                // Anchos fijos y compactos por columna para formato imprimible y texto arriba/abajo (wrapText)
                $columnWidths = [
                    'A' => 6,   // Nro.
                    'B' => 10,  // FECHA
                    'C' => 12,  // CEDULA O R.I.F.
                    'D' => 24,  // NOMBRE O RAZÓN SOCIAL
                    'E' => 11,  // Nº PLANILLA EXPORTACIÓN
                    'F' => 11,  // TIPO DE DOCUMENTO
                    'G' => 11,  // Nº FACTURA
                    'H' => 11,  // Nº DE CONTROL
                    'I' => 10,  // NUMERO DE REPORTE Z
                    'J' => 10,  // Factura inicial Z
                    'K' => 10,  // Factura Final Z
                    'L' => 12,  // REG. MAQUINA
                    'M' => 10,  // Nº NOTA DE DEBITO
                    'N' => 10,  // Nº NOTA DE CREDITO
                    'O' => 10,  // Nº FACTURA AFECTADA
                    'P' => 12,  // TIPO DE TRANSACCION
                    'Q' => 13,  // TOTAL VENTAS INCLUYENDO IVA
                    'R' => 12,  // VENTAS INTERNAS NO GRAVADAS
                    'S' => 12,  // BASE IMPONIBLE
                    'T' => 7,   // %IVA
                    'U' => 11,  // IVA
                ];

                if ($this->enableZoom) {
                    $columnWidths['V'] = 12; // BASE IMPONIBLE ZOOM
                    $columnWidths['W'] = 7;  // %IVA ZOOM
                    $columnWidths['X'] = 11; // IVA ZOOM
                    $columnWidths['Y'] = 11; // IVA RETENIDO
                    $columnWidths['Z'] = 13; // Nº COMPROBANTE DE RETENCIÓN
                    $columnWidths['AA'] = 11; // FECHA DE RECEPCION COMPROBANTE
                    $columnWidths['AB'] = 11; // IGTF
                } else {
                    $columnWidths['V'] = 11; // IVA RETENIDO
                    $columnWidths['W'] = 13; // Nº COMPROBANTE DE RETENCIÓN
                    $columnWidths['X'] = 11; // FECHA DE RECEPCION COMPROBANTE
                    $columnWidths['Y'] = 11; // IGTF
                }

                foreach ($columnWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // Aplicar wrap text y fuente compacta (7.5pt) al rango de datos
                $sheet->getStyle("A7:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                    'font' => ['size' => 7.5],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Bordes completos de cuadrícula (contorno e internos) para encabezados y datos
                $sheet->getStyle("A6:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D0D0D0'],
                        ],
                    ],
                ]);

                // Formato numérico profesional con separador de miles por coma y decimales por punto (#,##0.00)
                $numberFormat = '#,##0.00';
                $numCols = $this->enableZoom ? ['Q', 'R', 'S', 'U', 'V', 'X', 'Y', 'AB'] : ['Q', 'R', 'S', 'U', 'V', 'Y'];
                foreach ($numCols as $c) {
                    $sheet->getStyle("{$c}8:{$c}{$totalRow}")->getNumberFormat()->setFormatCode($numberFormat);
                }

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

                    // Encabezado Zoom (fila 6 y 7): tono más oscuro con texto blanco para legibilidad óptima
                    $sheet->getStyle("V6:X6")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D97706'], // Ámbar oscuro
                    ]);
                    $sheet->getStyle("V6:X6")->getFont()->getColor()->setRGB('FFFFFF');

                    $sheet->getStyle("V7:X7")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'B45309'], // Ámbar encabezado columna
                    ]);
                    $sheet->getStyle("V7:X7")->getFont()->getColor()->setRGB('FFFFFF');

                    // Celdas de datos Zoom (filas 8 en adelante): fondo amarillo claro con números visibles
                    if ($lastDataRow >= 8) {
                        $sheet->getStyle("V8:X{$lastDataRow}")->getFill()->applyFromArray([
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF2CC'],
                        ]);
                    }
                } else {
                    $sheet->setCellValue("V{$totalRow}", (float) $this->book->total_vat_withheld);
                    $sheet->setCellValue("Y{$totalRow}", (float) $this->book->total_igtf);
                }

                $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 8],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'B0B0B0'],
                        ],
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
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
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

                    $sheet->getStyle("C{$r}:T{$r}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D0D0D0'],
                            ],
                        ],
                    ]);
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
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D0D0D0'],
                        ],
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
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D0D0D0'],
                        ],
                    ],
                ]);

                // Formatos numéricos para el resumen
                $sheet->getStyle("I" . ($headerRow + 1) . ":I{$r}")->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle("N" . ($headerRow + 1) . ":N{$r}")->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle("Q" . ($headerRow + 1) . ":Q{$r}")->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle("T" . ($headerRow + 1) . ":T{$r}")->getNumberFormat()->setFormatCode($numberFormat);
            },
        ];
    }
}
