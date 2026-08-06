<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Pedido — {{ $supplierName ?? 'Proveedor' }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #696cff;
        }
        .order-title {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            color: #566a7f;
        }
        .details-box {
            background-color: #f8f9fa;
            border: 1px solid #e7e7e8;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table td {
            padding: 4px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #696cff;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
            padding: 8px;
            text-align: left;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #e7e7e8;
        }
        .items-table tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 8px;
        }
        .totals-table .total-row td {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #696cff;
            color: #696cff;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #a1acb8;
            border-top: 1px dashed #e7e7e8;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ config('app.name', 'ERP Farmacias') }}</div>
                <div>Orden de Reabastecimiento Automático IA</div>
                <div>Fecha de Emisión: {{ now()->format('d/m/Y g:i A') }}</div>
            </td>
            <td class="order-title">
                ORDEN DE COMPRA<br>
                <span style="color: #696cff;">#ORD-{{ str_pad((string)($orderId ?? 1), 6, '0', STR_PAD_LEFT) }}</span>
            </td>
        </tr>
    </table>

    <div class="details-box">
        <table class="details-table">
            <tr>
                <td><strong>Proveedor:</strong> {{ $supplierName ?? 'N/A' }}</td>
                <td><strong>Estatus:</strong> Pendiente de Aprobación</td>
            </tr>
            <tr>
                <td><strong>RIF / Identificación:</strong> {{ $supplierRif ?? 'S/R' }}</td>
                <td><strong>Fecha de Generación:</strong> {{ now()->format('Y-m-d') }}</td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Producto / Insumo</th>
                <th class="text-center" style="width: 15%;">Cant. Solicitada</th>
                <th class="text-end" style="width: 15%;">Costo Unit. ($)</th>
                <th class="text-end" style="width: 20%;">Subtotal ($)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item['name'] }}</strong>
                        @if(!empty($item['sku']))
                            <br><small style="color: #8592a3;">SKU: {{ $item['sku'] }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item['quantity'], 0) }}</td>
                    <td class="text-end">${{ number_format($item['unit_cost'], 2) }}</td>
                    <td class="text-end">${{ number_format($item['subtotal'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No hay productos registrados en esta orden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Total Ítems Solicitados:</td>
            <td class="text-end">{{ $totalItems ?? 0 }}</td>
        </tr>
        <tr>
            <td>Total Unidades:</td>
            <td class="text-end">{{ number_format($totalQuantity ?? 0, 0) }}</td>
        </tr>
        <tr class="total-row">
            <td>Monto Total USD:</td>
            <td class="text-end">${{ number_format($totalAmount ?? 0, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Documento generado automáticamente por el Asistente Inteligente de Reabastecimiento del ERP Farmacias.
    </div>

</body>
</html>
