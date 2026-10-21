<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro de Ventas</title>
    <style>
        @page {
            margin: 15px 20px 20px 20px;
            size: landscape;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.5px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 8px;
        }
        .header h2 {
            margin: 0 0 2px 0;
            font-size: 11px;
            font-weight: bold;
            color: #1a365d;
        }
        .header p {
            margin: 1px 0;
            font-size: 8px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #aaa;
            padding: 2px 3px;
            text-align: left;
        }
        .table-data th {
            background-color: #2b5797;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 7px;
        }
        .table-data th.zoom-col, .table-data td.zoom-col {
            background-color: #fff2cc;
        }
        .table-data th.zoom-header {
            background-color: #d4a017;
            color: #fff;
        }
        .table-data .text-right {
            text-align: right;
        }
        .table-data .text-center {
            text-align: center;
        }
        .total-row td {
            font-weight: bold;
            background-color: #f0f4f8;
            border-top: 1px solid #000;
            border-bottom: 2px solid #000;
        }
        .summary-box {
            width: 80%;
            margin-top: 8px;
            border-collapse: collapse;
        }
        .summary-box th, .summary-box td {
            border: 0.5px solid #bbb;
            padding: 2.5px 5px;
            font-size: 7.5px;
        }
        .summary-box th {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: right;
        }
        .summary-box th.concept {
            text-align: left;
        }
        .summary-box .text-right {
            text-align: right;
        }
        .summary-title {
            font-size: 9px;
            font-weight: bold;
            margin: 6px 0 3px 0;
            color: #2b5797;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>NOMBRE O RAZÓN SOCIAL: {{ $settings->app_name ?: 'EMPRESA' }}</h2>
        <p><strong>R.I.F.:</strong> {{ $settings->app_rif ?: $settings->rif ?: 'J-00000000-0' }} &nbsp;&nbsp;|&nbsp;&nbsp; <strong>DIRECCIÓN:</strong> {{ $settings->address ?: 'N/A' }}</p>
        <p><strong>LIBRO DE VENTAS DESDE {{ $book->start_date->format('d/m/Y') }} HASTA {{ $book->end_date->format('d/m/Y') }}</strong> &nbsp;&nbsp;|&nbsp;&nbsp; 
           <strong>CONDICIÓN:</strong> {{ ($settings->special_taxpayer_status === 'activada' || $settings->enable_ce) ? 'CONTRIBUYENTE ESPECIAL' : 'CONTRIBUYENTE ORDINARIO' }}
        </p>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th colspan="16">DATOS GENERALES DE LA TRANSACCIÓN</th>
                <th colspan="2">TOTALES</th>
                <th colspan="3">VENTAS GRAVADAS A CONTRIBUYENTES (16%)</th>
                @if($enableZoom)
                    <th colspan="3" class="zoom-header">VENTAS A NO CONTRIBUYENTES (ZOOM 8%)</th>
                @endif
                <th colspan="3">RETENCIONES</th>
                <th>IGTF</th>
            </tr>
            <tr>
                <th>N°</th>
                <th>Fecha</th>
                <th>Cédula/RIF</th>
                <th>Nombre / Razón Social</th>
                <th>Planilla Exp.</th>
                <th>Tipo Doc.</th>
                <th>N° Fact.</th>
                <th>N° Ctrl.</th>
                <th>N° Z</th>
                <th>Fact. Ini.</th>
                <th>Fact. Fin.</th>
                <th>Reg. Máq.</th>
                <th>N° ND</th>
                <th>N° NC</th>
                <th>Fac. Afect.</th>
                <th>Trans.</th>
                <th>Total c/IVA</th>
                <th>No Gravadas</th>
                <th>Base Imp.</th>
                <th>%</th>
                <th>IVA</th>
                @if($enableZoom)
                    <th class="zoom-col">Base Imp.</th>
                    <th class="zoom-col">%</th>
                    <th class="zoom-col">IVA</th>
                @endif
                <th>IVA Retenido</th>
                <th>N° Comprob.</th>
                <th>F. Rec. Comprob.</th>
                <th>IGTF</th>
            </tr>
        </thead>
        <tbody>
            @forelse($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-center">{{ optional($entry->operation_date)->format('d/m/Y') }}</td>
                    <td>{{ $entry->identification }}</td>
                    <td>{{ Str::limit($entry->client_name, 24) }}</td>
                    <td>{{ $entry->export_planilla_number }}</td>
                    <td class="text-center">{{ Str::limit($entry->document_type, 14) }}</td>
                    <td>{{ $entry->invoice_number }}</td>
                    <td>{{ $entry->control_number }}</td>
                    <td class="text-center">{{ $entry->z_report_number }}</td>
                    <td class="text-center">{{ $entry->z_first_invoice }}</td>
                    <td class="text-center">{{ $entry->z_last_invoice }}</td>
                    <td>{{ $entry->machine_serial }}</td>
                    <td>{{ $entry->debit_note_number }}</td>
                    <td class="text-center">{{ $entry->credit_note_number }}</td>
                    <td class="text-center">{{ $entry->affected_invoice_number }}</td>
                    <td class="text-center">{{ $entry->transaction_type }}</td>
                    <td class="text-right">{{ number_format((float)$entry->total_sales_with_vat, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->non_taxable_sales, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->taxpayer_taxable_base, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $entry->taxpayer_aliquot > 0 ? (number_format((float)$entry->taxpayer_aliquot * 100, 0) . '%') : '0%' }}</td>
                    <td class="text-right">{{ number_format((float)$entry->taxpayer_vat, 2, ',', '.') }}</td>
                    @if($enableZoom)
                        <td class="text-right zoom-col">{{ number_format((float)$entry->non_taxpayer_taxable_base, 2, ',', '.') }}</td>
                        <td class="text-center zoom-col">{{ $entry->non_taxpayer_aliquot > 0 ? (number_format((float)$entry->non_taxpayer_aliquot * 100, 0) . '%') : '0%' }}</td>
                        <td class="text-right zoom-col">{{ number_format((float)$entry->non_taxpayer_vat, 2, ',', '.') }}</td>
                    @endif
                    <td class="text-right">{{ number_format((float)$entry->vat_withheld, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $entry->withholding_receipt_number }}</td>
                    <td class="text-center">{{ optional($entry->withholding_receipt_date)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->igtf_amount, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $enableZoom ? 28 : 25 }}" class="text-center">No hay registros cargados en este libro de ventas.</td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="16" class="text-right">TOTALES:</td>
                <td class="text-right">{{ number_format((float)$book->total_sales_with_vat, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_non_taxable_sales, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_taxpayer_taxable_base, 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format((float)$book->total_taxpayer_vat, 2, ',', '.') }}</td>
                @if($enableZoom)
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_non_taxpayer_taxable_base, 2, ',', '.') }}</td>
                    <td class="zoom-col"></td>
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_non_taxpayer_vat, 2, ',', '.') }}</td>
                @endif
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td colspan="2"></td>
                <td class="text-right">{{ number_format((float)$book->total_igtf, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary-title">RESUMEN DE LIBRO DE VENTAS</div>
    <table class="summary-box">
        <thead>
            <tr>
                <th class="concept">Concepto</th>
                <th>ITEM</th>
                <th>Base Imponible</th>
                <th>ITEM</th>
                <th>Débito Fiscal</th>
                <th>IVA Retenido</th>
                <th>I.G.T.F.</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Ventas Internas no Gravadas:</td>
                <td class="text-center">40</td>
                <td class="text-right">{{ number_format((float)$book->total_non_taxable_sales, 2, ',', '.') }}</td>
                <td class="text-center">-</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Ventas Exportación Afectas solo Alicuota General:</td>
                <td class="text-center">41</td>
                <td class="text-right">0,00</td>
                <td class="text-center">-</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Ventas Internas Afectas solo Alicuota General:</td>
                <td class="text-center">42</td>
                <td class="text-right">{{ number_format((float)$book->total_taxpayer_taxable_base, 2, ',', '.') }}</td>
                <td class="text-center">43</td>
                <td class="text-right">{{ number_format((float)$book->total_taxpayer_vat, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Ventas Internas Afectas en Alicuota General + Adicional:</td>
                <td class="text-center">442</td>
                <td class="text-right">0,00</td>
                <td class="text-center">452</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Ventas Internas Afectas solo Alicuota Reducida:</td>
                <td class="text-center">443</td>
                <td class="text-right">{{ number_format((float)$book->total_non_taxpayer_taxable_base, 2, ',', '.') }}</td>
                <td class="text-center">453</td>
                <td class="text-right">{{ number_format((float)$book->total_non_taxpayer_vat, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Ajustes a Periodos Anteriores:</td>
                <td class="text-center">46</td>
                <td class="text-right">0,00</td>
                <td class="text-center">47</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            @php
                $totBase = (float)$book->total_non_taxable_sales + (float)$book->total_taxpayer_taxable_base + (float)$book->total_non_taxpayer_taxable_base;
                $totDebit = (float)$book->total_taxpayer_vat + (float)$book->total_non_taxpayer_vat;
            @endphp
            <tr style="font-weight: bold; background-color: #f7fafc;">
                <td>Total Ventas y Debitos Fiscales del Período:</td>
                <td></td>
                <td class="text-right">{{ number_format($totBase, 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totDebit, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr style="font-weight: bold;">
                <td colspan="6">TOTAL I.G.T.F.:</td>
                <td class="text-right">{{ number_format((float)$book->total_igtf, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
