<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Relación de Retenciones de IVA - SENIAT</title>
    <style>
        @page {
            margin: 10px 12px 15px 12px;
            size: landscape;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.8px;
            color: #000000;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 6px;
            border-bottom: 1.5px solid #000000;
            padding-bottom: 4px;
        }
        .header h2 {
            margin: 0 0 3px 0;
            font-size: 10.5px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
        }
        .header-meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }
        .header-meta td {
            font-size: 7.2px;
            padding: 1.5px 0;
            color: #000000;
        }
        .text-bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        .table-data {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-top: 1px solid #000000;
            border-left: 1px solid #000000;
            table-layout: fixed;
            background-color: #ffffff;
        }
        .table-data th, .table-data td {
            border-right: 0.5px solid #94a3b8;
            border-bottom: 0.5px solid #94a3b8;
            padding: 2.5px 2px;
            word-wrap: break-word;
            overflow: hidden;
        }
        .table-data th {
            background-color: #ffffff;
            color: #000000;
            font-size: 6.2px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1.5px solid #000000;
            line-height: 1.15;
        }
        .table-data td {
            font-size: 6.6px;
            color: #000000;
            background-color: #ffffff;
        }
        .row-total td {
            background-color: #ffffff;
            color: #000000;
            font-weight: bold;
            border-top: 1px solid #000000;
            border-bottom: 2px solid #000000;
            padding: 3px 2px;
        }
        .footer {
            margin-top: 6px;
            font-size: 6.5px;
            color: #333333;
        }
    </style>
</head>
<body>
    @php
        $taxpayerType = ($settings->special_taxpayer_status === 'activada' || $settings->enable_ce)
            ? 'CONTRIBUYENTE ESPECIAL'
            : 'CONTRIBUYENTE ORDINARIO';
        $monthName = mb_strtoupper(\Carbon\Carbon::create($book->year, $book->month, 1)->locale('es')->monthName, 'UTF-8');
        $periodTypeValue = is_object($book->period_type) ? $book->period_type->value : (string) $book->period_type;
        $periodRange = ($periodTypeValue === 'fortnightly')
            ? ($book->fortnight == 1 ? "1RA QUINCENA (01 AL 15 DE {$monthName} DE {$book->year})" : "2DA QUINCENA (16 AL " . \Carbon\Carbon::parse($book->end_date)->format('d') . " DE {$monthName} DE {$book->year})")
            : "MES COMPLETO DE {$monthName} DE {$book->year}";
    @endphp

    <div class="header">
        <h2>Relación y Reporte de Retenciones de IVA (Declaración Informativa SENIAT)</h2>
        <table class="header-meta">
            <tr>
                <td style="width: 38%;"><span class="text-bold">Razón Social:</span> {{ mb_strtoupper($settings->app_name ?: 'FARMACIA', 'UTF-8') }}</td>
                <td style="width: 22%;"><span class="text-bold">RIF Agente:</span> {{ mb_strtoupper($settings->app_rif ?: $settings->rif ?: 'J-00000000-0', 'UTF-8') }}</td>
                <td style="width: 20%;"><span class="text-bold">Mes:</span> {{ $monthName }} {{ $book->year }}</td>
                <td style="width: 20%; text-align: right;"><span class="text-bold">Ejercicio Fiscal:</span> {{ $book->year }}</td>
            </tr>
            <tr>
                <td colspan="3"><span class="text-bold">Dirección Fiscal:</span> {{ mb_strtoupper($settings->address ?: 'DIRECCIÓN FISCAL', 'UTF-8') }}</td>
                <td style="text-align: right;"><span class="text-bold">{{ $taxpayerType }}</span></td>
            </tr>
            <tr>
                <td colspan="4" style="font-style: italic; color: #333333; padding-top: 2px;">
                    Providencia Administrativa SNAT/2015/0049. Período Fiscal: {{ $periodRange }}
                </td>
            </tr>
        </table>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 2.5%;">Nº</th>
                <th style="width: 5%;">FECHA DOC</th>
                <th style="width: 2.5%;">OP</th>
                <th style="width: 3%;">TIPO</th>
                <th style="width: 6.5%;">RIF PROV.</th>
                <th style="width: 17%;">RAZÓN SOCIAL PROVEEDOR</th>
                <th style="width: 7.5%;">Nº FACTURA</th>
                <th style="width: 7%;">Nº CONTROL</th>
                <th style="width: 8%;">TOTAL DOC (BS)</th>
                <th style="width: 8%;">BASE IMPONIBLE (BS)</th>
                <th style="width: 8%;">IVA RETENIDO (BS)</th>
                <th style="width: 5.5%;">DOC AFECT.</th>
                <th style="width: 8.5%;">Nº COMPROBANTE</th>
                <th style="width: 6%;">EXENTO (BS)</th>
                <th style="width: 3%;">ALÍCUOTA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-center">{{ $entry->document_date ? \Carbon\Carbon::parse($entry->document_date)->format('d/m/Y') : '' }}</td>
                    <td class="text-center">{{ $entry->operation_type }}</td>
                    <td class="text-center">{{ $entry->document_type }}</td>
                    <td class="text-center">{{ $entry->supplier_rif }}</td>
                    <td class="text-left">{{ $entry->supplier_name }}</td>
                    <td class="text-center">{{ $entry->document_number }}</td>
                    <td class="text-center">{{ $entry->control_number }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->total_document_amount ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->taxable_base ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float) ($entry->withheld_amount ?? 0), 2, '.', ',') }}</td>
                    <td class="text-center">{{ $entry->affected_document_number ?: '0' }}</td>
                    <td class="text-center">{{ $entry->retention_number }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->exempt_amount ?? 0), 2, '.', ',') }}</td>
                    <td class="text-center">{{ number_format((float) ($entry->tax_rate ?? 16), 1, '.', ',') }}%</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="row-total">
                <td colspan="8" class="text-center">TOTALES CONSOLIDADOS ({{ $book->total_documents }} FACTURAS RETENIDAS)</td>
                <td class="text-right">{{ number_format((float) ($book->total_document_amount ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_taxable_base ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_withheld_amount ?? 0), 2, '.', ',') }}</td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-right">{{ number_format((float) ($book->total_exempt_amount ?? 0), 2, '.', ',') }}</td>
                <td class="text-center">-</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado automáticamente por el Sistema ERP. Relación oficial para la carga de retenciones de IVA en el portal del SENIAT.
    </div>
</body>
</html>
