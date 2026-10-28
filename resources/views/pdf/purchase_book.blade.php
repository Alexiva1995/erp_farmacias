<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro de Compras</title>
    <style>
        @page {
            margin: 10px 12px 15px 12px;
            size: landscape;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 6px;
        }
        .header h2 {
            margin: 0 0 2px 0;
            font-size: 10px;
            font-weight: bold;
            color: #1a365d;
        }
        .header p {
            margin: 1px 0;
            font-size: 7.5px;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #aaa;
            padding: 2px 2.5px;
            text-align: left;
            word-wrap: break-word;
            overflow: hidden;
        }
        .table-data th {
            background-color: #2b5797;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
            font-size: 6.5px;
            line-height: 1.1;
        }
        .table-data th.col-planilla, .table-data td.col-planilla {
            font-size: 5.5px;
            padding: 1px;
            text-align: center;
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
        <h2>NOMBRE O RAZÓN SOCIAL: {{ mb_strtoupper($settings->app_name ?: 'EMPRESA', 'UTF-8') }}</h2>
        <p><strong>R.I.F.:</strong> {{ mb_strtoupper($settings->app_rif ?: $settings->rif ?: 'J-00000000-0', 'UTF-8') }} &nbsp;&nbsp;|&nbsp;&nbsp; <strong>DIRECCIÓN FISCAL:</strong> {{ mb_strtoupper($settings->address ?: 'N/A', 'UTF-8') }}</p>
        <p><strong>LIBRO DE COMPRAS DESDE {{ $book->start_date->format('d/m/Y') }} HASTA {{ $book->end_date->format('d/m/Y') }}</strong> &nbsp;&nbsp;|&nbsp;&nbsp; 
           <strong>CONDICIÓN FISCAL:</strong> {{ mb_strtoupper(($settings->special_taxpayer_status === 'activada' || $settings->enable_ce) ? 'CONTRIBUYENTE ESPECIAL' : 'CONTRIBUYENTE ORDINARIO', 'UTF-8') }}
        </p>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 2.5%;">Nro.</th>
                <th style="width: 5%;">Fecha</th>
                <th style="width: 5.5%;">R.I.F.</th>
                <th style="width: 10%;">Nombre o Razon Social</th>
                <th class="col-planilla" style="width: 4%;">Planilla Imp. (C80/C81)</th>
                <th style="width: 4.5%;">Exp. Import.</th>
                <th style="width: 5%;">Nº Fact.</th>
                <th style="width: 5%;">Nº Control</th>
                <th style="width: 4%;">Nota Déb.</th>
                <th style="width: 4%;">Nota Créd.</th>
                <th style="width: 5%;">Fact. Afect.</th>
                <th style="width: 4.5%;">Tipo Doc.</th>
                <th style="width: 4%;">Tipo Trans.</th>
                <th style="width: 6%;">Total Compras</th>
                <th style="width: 5.5%;">Compras Exentas</th>
                <th style="width: 6%;">Base Imp.</th>
                <th style="width: 3%;">% Alic.</th>
                <th style="width: 5.5%;">IVA General</th>
                @if($enableZoom)
                    <th class="zoom-col" style="width: 5.5%;">Base Zoom</th>
                    <th class="zoom-col" style="width: 3%;">% Alic.</th>
                    <th class="zoom-col" style="width: 5.5%;">IVA Zoom</th>
                @endif
                <th style="width: 5%;">IVA Ret.</th>
                <th style="width: 5%;">Nº Comprobante</th>
            </tr>
        </thead>
        <tbody>
            @forelse($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-center">{{ optional($entry->operation_date)->format('d/m/Y') }}</td>
                    <td>{{ $entry->rif }}</td>
                    <td>{{ Str::limit($entry->supplier_name, 22) }}</td>
                    <td class="col-planilla">{{ $entry->import_planilla_number }}</td>
                    <td>{{ $entry->import_dossier_number }}</td>
                    <td class="text-right">{{ $entry->invoice_number }}</td>
                    <td class="text-right">{{ $entry->control_number }}</td>
                    <td class="text-right">{{ $entry->debit_note_number }}</td>
                    <td class="text-right">{{ $entry->credit_note_number }}</td>
                    <td class="text-right">{{ $entry->affected_invoice_number }}</td>
                    <td class="text-center">{{ Str::limit($entry->document_type, 11) }}</td>
                    <td class="text-center">{{ str_replace(['-REGISTRO', 'REGISTRO'], ['-REG', 'REG'], $entry->transaction_type) }}</td>
                    <td class="text-right">{{ number_format((float)$entry->total_purchases_with_vat, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->exempt_purchases, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float)$entry->general_taxable_base, 2, '.', ',') }}</td>
                    <td class="text-center">{{ $entry->general_aliquot > 0 ? (number_format((float)$entry->general_aliquot * 100, 0) . '%') : '0%' }}</td>
                    <td class="text-right">{{ number_format((float)$entry->general_vat, 2, '.', ',') }}</td>
                    @if($enableZoom)
                        <td class="text-right zoom-col">{{ number_format((float)$entry->reduced_taxable_base, 2, '.', ',') }}</td>
                        <td class="text-center zoom-col">{{ $entry->reduced_aliquot > 0 ? (number_format((float)$entry->reduced_aliquot * 100, 0) . '%') : '0%' }}</td>
                        <td class="text-right zoom-col">{{ number_format((float)$entry->reduced_vat, 2, '.', ',') }}</td>
                    @endif
                    <td class="text-right">{{ number_format((float)$entry->vat_withheld, 2, '.', ',') }}</td>
                    <td class="text-center">{{ $entry->withholding_receipt_number }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $enableZoom ? 23 : 20 }}" class="text-center">No hay registros cargados en este libro de compras.</td>
                </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="13" class="text-right">TOTALES:</td>
                <td class="text-right">{{ number_format((float)$book->total_purchases_with_vat, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_exempt_purchases, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_general_taxable_base, 2, '.', ',') }}</td>
                <td></td>
                <td class="text-right">{{ number_format((float)$book->total_general_vat, 2, '.', ',') }}</td>
                @if($enableZoom)
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_reduced_taxable_base, 2, '.', ',') }}</td>
                    <td class="zoom-col"></td>
                    <td class="text-right zoom-col">{{ number_format((float)$book->total_reduced_vat, 2, '.', ',') }}</td>
                @endif
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, '.', ',') }}</td>
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
                <td class="text-right">{{ number_format((float)$book->total_exempt_purchases, 2, '.', ',') }}</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas solo Alicuota General:</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Importación Afectas solo Alicuota Reducida:</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas solo Alicuota General:</td>
                <td class="text-right">{{ number_format((float)$book->total_general_taxable_base, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_general_vat, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, '.', ',') }}</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            <tr>
                <td>Total Compras Internas Afectas solo Alicuota Reducida:</td>
                <td class="text-right">{{ number_format((float)$book->total_reduced_taxable_base, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_reduced_vat, 2, '.', ',') }}</td>
                <td class="text-right">0.00</td>
                <td class="text-right">0.00</td>
            </tr>
            @php
                $totBase = (float)$book->total_exempt_purchases + (float)$book->total_general_taxable_base + (float)$book->total_reduced_taxable_base;
                $totCredit = (float)$book->total_general_vat + (float)$book->total_reduced_vat;
            @endphp
            <tr style="font-weight: bold; background-color: #f7fafc;">
                <td>TOTALES</td>
                <td class="text-right">{{ number_format($totBase, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($totCredit, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float)$book->total_vat_withheld, 2, '.', ',') }}</td>
                <td class="text-right">0.00</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
