<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro de Compras</title>
    <style>
        @page {
            margin: 15px 20px 20px 20px;
            size: landscape;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
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
            font-size: 8.5px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #aaa;
            padding: 2.5px 3px;
            text-align: left;
        }
        .table-data th {
            background-color: #2b5797;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 7.5px;
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
            width: 75%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .summary-box th, .summary-box td {
            border: 0.5px solid #bbb;
            padding: 3px 6px;
            font-size: 8px;
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
            font-size: 9.5px;
            font-weight: bold;
            margin: 8px 0 4px 0;
            color: #2b5797;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>NOMBRE O RAZÓN SOCIAL: {{ $settings->app_name ?: 'EMPRESA' }}</h2>
        <p><strong>R.I.F.:</strong> {{ $settings->app_rif ?: $settings->rif ?: 'J-00000000-0' }} &nbsp;&nbsp;|&nbsp;&nbsp; <strong>DIRECCIÓN:</strong> {{ $settings->address ?: 'N/A' }}</p>
        <p><strong>LIBRO DE COMPRAS DESDE {{ $book->start_date->format('d/m/Y') }} HASTA {{ $book->end_date->format('d/m/Y') }}</strong> &nbsp;&nbsp;|&nbsp;&nbsp; 
           <strong>CONDICIÓN:</strong> {{ ($settings->special_taxpayer_status === 'activada' || $settings->enable_ce) ? 'CONTRIBUYENTE ESPECIAL' : 'CONTRIBUYENTE ORDINARIO' }}
        </p>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th colspan="13">DATOS GENERALES DE LA TRANSACCIÓN</th>
                <th colspan="2">TOTALES GENERALES</th>
                <th colspan="3">COMPRAS INTERNAS / IMPORTACIÓN (16%)</th>
                @if($enableZoom)
                    <th colspan="3" class="zoom-header">COMPRAS INTERNAS (ZOOM 8%)</th>
                @endif
                <th colspan="2">RETENCIONES</th>
            </tr>
            <tr>
                <th>N°</th>
                <th>Fecha</th>
                <th>R.I.F.</th>
                <th>Nombre / Razón Social</th>
                <th>C80/C81</th>
                <th>Expediente</th>
                <th>N° Factura</th>
                <th>N° Control</th>
                <th>N° ND</th>
                <th>N° NC</th>
                <th>Fac. Afectada</th>
                <th>Tipo Doc.</th>
                <th>Trans.</th>
                <th>Total c/ IVA</th>
                <th>Exento</th>
                <th>Base Imp.</th>
                <th>%</th>
                <th>IVA Gen.</th>
                @if($enableZoom)
                    <th class="zoom-col">Base Imp.</th>
                    <th class="zoom-col">%</th>
                    <th class="zoom-col">IVA Red.</th>
                @endif
                <th>IVA Retenido</th>
                <th>N° Comprobante</th>
            </tr>
        </thead>
        <tbody>
            @forelse($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-center">{{ optional($entry->operation_date)->format('d/m/Y') }}</td>
                    <td>{{ $entry->rif }}</td>
                    <td>{{ Str::limit($entry->supplier_name, 28) }}</td>
                    <td>{{ $entry->import_planilla_number }}</td>
                    <td>{{ $entry->import_dossier_number }}</td>
                    <td>{{ $entry->invoice_number }}</td>
                    <td>{{ $entry->control_number }}</td>
                    <td>{{ $entry->debit_note_number }}</td>
                    <td>{{ $entry->credit_note_number }}</td>
                    <td>{{ $entry->affected_invoice_number }}</td>
                    <td class="text-center">{{ Str::limit($entry->document_type, 14) }}</td>
                    <td class="text-center">{{ $entry->transaction_type }}</td>
                    <td class="text-right">{{ number_format((float)$entry->total_purchases_with_vat, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->exempt_purchases, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->general_taxable_base, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $entry->general_aliquot > 0 ? (number_format((float)$entry->general_aliquot * 100, 0) . '%') : '0%' }}</td>
                    <td class="text-right">{{ number_format((float)$entry->general_vat, 2, ',', '.') }}</td>
                    @if($enableZoom)
                        <td class="text-right zoom-col">{{ number_format((float)$entry->reduced_taxable_base, 2, ',', '.') }}</td>
                        <td class="text-center zoom-col">{{ $entry->reduced_aliquot > 0 ? (number_format((float)$entry->reduced_aliquot * 100, 0) . '%') : '0%' }}</td>
                        <td class="text-right zoom-col">{{ number_format((float)$entry->reduced_vat, 2, ',', '.') }}</td>
                    @endif
                    <td class="text-right">{{ number_format((float)$entry->vat_withheld, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $entry->withholding_receipt_number }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $enableZoom ? 23 : 20 }}" class="text-center">No hay registros cargados en este libro de compras.</td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="13" class="text-right">TOTALES:</td>
                <td class="text-right">{{ number_format((float)$book->total_purchases_with_vat, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_exempt_purchases, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_general_taxable_base, 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format((float)$book->total_general_vat, 2, ',', '.') }}</td>
                @if($enableZoom)
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_reduced_taxable_base, 2, ',', '.') }}</td>
                    <td class="zoom-col"></td>
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_reduced_vat, 2, ',', '.') }}</td>
                @endif
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="summary-title">RESUMEN LIBRO DE COMPRAS</div>
    <table class="summary-box">
        <thead>
            <tr>
                <th class="concept">Concepto</th>
                <th>Base Imponible</th>
                <th>Crédito Fiscal</th>
                <th>Retenciones IVA</th>
                <th>Anticipo IVA</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Compras Exentas y/o Sin Derecho a Crédito Fiscal:</td>
                <td class="text-right">{{ number_format((float)$book->total_exempt_purchases, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas solo Alicuota General:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas solo Alicuota Reducida:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas solo Alicuota General:</td>
                <td class="text-right">{{ number_format((float)$book->total_general_taxable_base, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_general_vat, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas solo Alicuota Reducida (Zoom):</td>
                <td class="text-right">{{ number_format((float)$book->total_reduced_taxable_base, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_reduced_vat, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
            </tr>
            @php
                $totBase = (float)$book->total_exempt_purchases + (float)$book->total_general_taxable_base + (float)$book->total_reduced_taxable_base;
                $totCredit = (float)$book->total_general_vat + (float)$book->total_reduced_vat;
            @endphp
            <tr style="font-weight: bold; background-color: #f7fafc;">
                <td>TOTALES</td>
                <td class="text-right">{{ number_format($totBase, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totCredit, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, ',', '.') }}</td>
                <td class="text-right">0,00</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
