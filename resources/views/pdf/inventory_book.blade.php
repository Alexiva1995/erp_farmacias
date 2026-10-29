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
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 5px;
            border-bottom: 1.5px solid #1e3a8a;
            padding-bottom: 4px;
        }
        .header h2 {
            margin: 0 0 2px 0;
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .header-meta {
            width: 100%;
            margin-top: 2px;
        }
        .header-meta td {
            font-size: 7px;
            padding: 1px 0;
        }
        .text-bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .table-data th, .table-data td {
            border: 0.5px solid #94a3b8;
            padding: 2px 2px;
            word-wrap: break-word;
            overflow: hidden;
        }
        .group-unidades {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
        }
        .group-bolivares {
            background-color: #fef3c7;
            color: #92400e;
            font-size: 7.5px;
            font-weight: bold;
            text-align: center;
        }
        .header-unidades {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 6.2px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        .header-bolivares {
            background-color: #fde68a;
            color: #78350f;
            font-size: 6.2px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }
        .row-total {
            background-color: #e2e8f0;
            font-weight: bold;
            border-top: 1.5px solid #334155;
            border-bottom: 2px solid #0f172a;
        }
        .footer {
            margin-top: 8px;
            font-size: 6.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $taxpayerType = ($settings->special_taxpayer_status === 'activada' || $settings->enable_ce)
            ? 'CONTRIBUYENTE ESPECIAL'
            : 'CONTRIBUYENTE ORDINARIO';
        $monthName = mb_strtoupper(\Carbon\Carbon::create($book->year, $book->month, 1)->locale('es')->monthName, 'UTF-8');
        $periodRange = $book->period_type->value === 'fortnightly'
            ? ($book->fortnight == 1 ? "1RA QUINCENA (01 AL 15 DE {$monthName} DE {$book->year})" : "2DA QUINCENA (16 AL " . \Carbon\Carbon::parse($book->end_date)->format('d') . " DE {$monthName} DE {$book->year})")
            : "MES COMPLETO DE {$monthName} DE {$book->year}";
    @endphp

    <div class="header">
        <h2>Registro detallado de entrada y salida de inventario de mercancia</h2>
        <table class="header-meta">
            <tr>
                <td style="width: 25%;"><span class="text-bold">Mes:</span> {{ $monthName }}</td>
                <td style="width: 20%;"><span class="text-bold">Ejercicio Gravable:</span> {{ $book->year }}</td>
                <td style="width: 35%;"><span class="text-bold">Razón Social:</span> {{ mb_strtoupper($settings->app_name ?: 'FARMACIA', 'UTF-8') }}</td>
                <td style="width: 20%; text-align: right;"><span class="text-bold">RIF:</span> {{ mb_strtoupper($settings->app_rif ?: $settings->rif ?: 'J-00000000-0', 'UTF-8') }}</td>
            </tr>
            <tr>
                <td colspan="3"><span class="text-bold">DIRECCIÓN PRINCIPAL:</span> {{ mb_strtoupper($settings->address ?: 'DIRECCIÓN FISCAL', 'UTF-8') }}</td>
                <td style="text-align: right;"><span class="text-bold">{{ $taxpayerType }}</span></td>
            </tr>
            <tr>
                <td colspan="4" style="font-style: italic; color: #475569;">
                    Movimiento de Unidades según el artículo 177 de la Ley de Impuesto Sobre la Renta. Período: {{ $periodRange }}
                </td>
            </tr>
        </table>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th colspan="4" style="background-color: #f8fafc; border: none;"></th>
                <th colspan="6" class="group-unidades">UNIDADES</th>
                <th colspan="6" class="group-bolivares">BOLIVARES</th>
            </tr>
            <tr>
                <th class="header-unidades" style="width: 2.5%;">Nº</th>
                <th class="header-unidades" style="width: 5.5%;">CÓDIGO</th>
                <th class="header-unidades" style="width: 17%;">DESCRIPCIÓN DEL PRODUCTO</th>
                <th class="header-unidades" style="width: 6.5%;">COSTO UNIT. (BS)</th>
                <th class="header-unidades" style="width: 5.5%;">EXIST. INICIAL</th>
                <th class="header-unidades" style="width: 5.5%;">ENTRADAS</th>
                <th class="header-unidades" style="width: 5.5%;">VENTAS</th>
                <th class="header-unidades" style="width: 5%;">RETIROS</th>
                <th class="header-unidades" style="width: 5%;">AUTOCONS.</th>
                <th class="header-unidades" style="width: 5.5%;">EXISTENCIA</th>
                <th class="header-bolivares" style="width: 6.8%;">VALOR ANTERIOR</th>
                <th class="header-bolivares" style="width: 6.8%;">COSTOS ENTRADA</th>
                <th class="header-bolivares" style="width: 6.8%;">COSTOS SALIDAS</th>
                <th class="header-bolivares" style="width: 6.8%;">COSTOS RETIROS</th>
                <th class="header-bolivares" style="width: 6.8%;">COSTOS AUTOCONS.</th>
                <th class="header-bolivares" style="width: 7.5%;">VALOR INVENTARIO</th>
            </tr>
        </thead>
        <tbody>
            @foreach($book->entries as $entry)
                <tr>
                    <td class="text-center">{{ $entry->operation_number }}</td>
                    <td class="text-right">{{ $entry->product_code }}</td>
                    <td>{{ $entry->product_description }}</td>
                    <td class="text-right">{{ number_format($entry->unit_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->initial_stock, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->purchase_entries, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->sales_exits, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->withdrawals, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->self_consumptions, 2, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($entry->final_stock, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->previous_value_bs, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->purchase_entries_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->sales_exits_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->withdrawals_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->self_consumptions_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold;">{{ number_format($entry->final_inventory_value_bs, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="row-total">
                <td colspan="4" class="text-center">TOTALES CONSOLIDADOS</td>
                <td class="text-right">{{ number_format($book->total_initial_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_purchase_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_sales_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_withdrawal_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_self_consumption_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_final_units, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_previous_value_bs, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_purchase_cost_bs, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_sales_cost_bs, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_withdrawal_cost_bs, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_self_consumption_cost_bs, 2, '.', ',') }}</td>
                <td class="text-right">{{ number_format($book->total_final_inventory_value_bs, 2, '.', ',') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado automáticamente por el Sistema ERP. Conforme a las providencias y normativas del SENIAT para el Control Fiscal de Inventarios (Art. 177 LISR).
    </div>
</body>
</html>
