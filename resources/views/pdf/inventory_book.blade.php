<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro de Inventario - Art. 177 LISR</title>
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
            letter-spacing: 0.3px;
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
            font-size: 6.3px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1.5px solid #000000;
            line-height: 1.15;
        }
        .table-data td {
            font-size: 6.7px;
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
        <h2>Registro detallado de entrada y salida de inventario de mercancía</h2>
        <table class="header-meta">
            <tr>
                <td style="width: 38%;"><span class="text-bold">Razón Social:</span> {{ mb_strtoupper($settings->app_name ?: 'FARMACIA', 'UTF-8') }}</td>
                <td style="width: 22%;"><span class="text-bold">RIF:</span> {{ mb_strtoupper($settings->app_rif ?: $settings->rif ?: 'J-00000000-0', 'UTF-8') }}</td>
                <td style="width: 20%;"><span class="text-bold">Mes:</span> {{ $monthName }} {{ $book->year }}</td>
                <td style="width: 20%; text-align: right;"><span class="text-bold">Ejercicio Fiscal:</span> {{ $book->year }}</td>
            </tr>
            <tr>
                <td colspan="3"><span class="text-bold">Dirección Fiscal:</span> {{ mb_strtoupper($settings->address ?: 'DIRECCIÓN FISCAL', 'UTF-8') }}</td>
                <td style="text-align: right;"><span class="text-bold">{{ $taxpayerType }}</span></td>
            </tr>
            <tr>
                <td colspan="4" style="font-style: italic; color: #333333; padding-top: 2px;">
                    Cumplimiento según el Art. 177 de la Ley de Impuesto Sobre la Renta (LISR) - SENIAT. Período: {{ $periodRange }}
                </td>
            </tr>
        </table>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 2.5%;">Nº</th>
                <th style="width: 6%;">CÓDIGO</th>
                <th style="width: 17.5%;">DESCRIPCIÓN DEL PRODUCTO</th>
                <th style="width: 6.5%;">COSTO UNIT. (BS)</th>
                <th style="width: 5.5%;">EXIST. INICIAL</th>
                <th style="width: 5.5%;">ENTRADAS</th>
                <th style="width: 5.5%;">VENTAS</th>
                <th style="width: 5%;">RETIROS</th>
                <th style="width: 5%;">AUTOCONS.</th>
                <th style="width: 5.5%;">EXISTENCIA</th>
                <th style="width: 6.5%;">VALOR ANTERIOR (BS)</th>
                <th style="width: 6.5%;">COSTO ENTRADAS (BS)</th>
                <th style="width: 6.5%;">COSTO SALIDAS (BS)</th>
                <th style="width: 6.5%;">COSTO RETIROS (BS)</th>
                <th style="width: 6.5%;">COSTO AUTOCONS. (BS)</th>
                <th style="width: 7.5%;">VALOR INVENTARIO (BS)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-right">{{ $entry->product_code }}</td>
                    <td class="text-left">{{ $entry->product_description }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->unit_cost_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->initial_stock ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->purchase_entries ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->sales_exits ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->withdrawals ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->self_consumptions ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float) ($entry->final_stock ?? 0), 0, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->previous_value_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->purchase_entries_cost_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->sales_exits_cost_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->withdrawals_cost_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) ($entry->self_consumptions_cost_bs ?? 0), 2, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format((float) ($entry->final_inventory_value_bs ?? 0), 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="row-total">
                <td colspan="4" class="text-center">TOTALES CONSOLIDADOS</td>
                <td class="text-right">{{ number_format((float) ($book->total_initial_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_purchase_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_sales_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_withdrawal_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_self_consumption_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_final_units ?? 0), 0, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_previous_value_bs ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_purchase_cost_bs ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_sales_cost_bs ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_withdrawal_cost_bs ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_self_consumption_cost_bs ?? 0), 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format((float) ($book->total_final_inventory_value_bs ?? 0), 2, '.', ',') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado automáticamente por el Sistema ERP. Conforme a las providencias y normativas del SENIAT para el Control Fiscal de Inventarios (Art. 177 LISR).
    </div>
</body>
</html>
