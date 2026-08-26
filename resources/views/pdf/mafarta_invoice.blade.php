<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Fiscal — {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Lato', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #212529;
            margin: 0;
            padding: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 2px solid #004b87;
            padding-bottom: 6px;
        }
        .cobeca-logo-title {
            font-size: 16px;
            font-weight: 900;
            color: #004b87;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #495057;
        }
        .invoice-title {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            color: #004b87;
        }
        .badge-indexed {
            display: inline-block;
            background-color: #fef08a;
            color: #854d0e;
            padding: 2px 6px;
            font-weight: bold;
            font-size: 9px;
            border-radius: 3px;
            border: 1px solid #facc15;
            margin-top: 3px;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .info-table td {
            padding: 2px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .items-table th {
            background-color: #004b87;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            padding: 5px 6px;
            text-align: left;
        }
        .items-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 10px;
            border: 1px solid #cbd5e1;
        }
        .totals-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        .total-row {
            font-weight: bold;
            font-size: 11px;
            background-color: #e2e8f0;
            color: #004b87;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="company-name">{{ $detail['descDrogueria'] ?? 'C.A. MAFARTA' }}</div>
                <div>RIF: {{ $detail['rifDrogueria'] ?? 'J-07001225-0' }}</div>
                <div>Teléfono: {{ $detail['telefonoDrogueria'] ?? '0273-5153009' }}</div>
            </td>
            <td style="width: 40%;" class="text-right">
                <div class="invoice-title">FACTURA N° {{ $detail['nroFactura'] ?? $invoice->invoice_number }}</div>
                <div><strong>N° Control:</strong> {{ $detail['nroControl'] ?? $invoice->control_number ?? 'N/A' }}</div>
                <div><strong>N° Pedido:</strong> {{ $detail['nroPedido'] ?? 'N/A' }}</div>
                @if($invoice->is_indexed)
                    <div class="badge-indexed">FACTURA INDEXADA (FA$)</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="info-box">
        <table class="info-table">
            <tr>
                <td style="width: 55%;"><strong>Cliente:</strong> {{ $detail['descCliente'] ?? 'FARMACIA BARRIO SUCRE 2024, C.A' }}</td>
                <td style="width: 45%;"><strong>Fecha Emisión:</strong> {{ !empty($detail['fechaFactura']) ? \Carbon\Carbon::parse($detail['fechaFactura'])->format('d/m/Y') : $invoice->created_invoice_date?->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>Código / RIF:</strong> {{ $detail['codCliente'] ?? '31373' }} / {{ $detail['rifCliente'] ?? 'J505406957' }}</td>
                <td><strong>Fecha Vencimiento:</strong> {{ !empty($detail['fechaVencimiento']) ? \Carbon\Carbon::parse($detail['fechaVencimiento'])->format('d/m/Y') : $invoice->exp_date?->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td><strong>Dirección:</strong> {{ $detail['dirCliente'] ?? '' }}</td>
                <td><strong>Tasa Factura (BCV):</strong> {{ number_format((float)($detail['tasaReferencial'] ?? $invoice->exchange_rate ?? 1), 2, ',', '.') }} Bs/$</td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 10%;">Código</th>
                <th style="width: 40%;">Descripción</th>
                <th style="width: 12%;" class="text-center">Lote</th>
                <th style="width: 12%;" class="text-center">Vence</th>
                <th style="width: 8%;" class="text-center">Cant.</th>
                <th style="width: 9%;" class="text-right">Precio Bs</th>
                <th style="width: 9%;" class="text-right">Total Bs</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($detail['detalles']) && count($detail['detalles']) > 0)
                @foreach($detail['detalles'] as $item)
                    <tr>
                        <td>{{ $item['codArticulo'] ?? '' }}</td>
                        <td>{{ $item['descArticulo'] ?? '' }}</td>
                        <td class="text-center">{{ $item['nulotefabric'] ?? 'N/A' }}</td>
                        <td class="text-center">{{ !empty($item['fevencimiento']) ? \Carbon\Carbon::parse($item['fevencimiento'])->format('m/Y') : 'N/A' }}</td>
                        <td class="text-center">{{ $item['cantidadFacturada'] ?? 1 }}</td>
                        <td class="text-right">{{ number_format((float)($item['montoOferta'] ?? $item['montoMayor'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float)($item['montoTotal'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @elseif($invoice->details && count($invoice->details) > 0)
                @foreach($invoice->details as $d)
                    <tr>
                        <td>{{ $d->codigo_producto ?? $d->barcode ?? '' }}</td>
                        <td>{{ $d->descripcion_producto ?? '' }}</td>
                        <td class="text-center">{{ $d->lot_number ?? 'N/A' }}</td>
                        <td class="text-center">{{ $d->expiration_date ? $d->expiration_date->format('m/Y') : 'N/A' }}</td>
                        <td class="text-center">{{ (int)$d->quantity }}</td>
                        <td class="text-right">{{ number_format((float)$d->unit_cost, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float)$d->total_cost, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Base Imponible:</td>
            <td class="text-right">{{ number_format((float)($detail['montoSubTotal'] ?? $invoice->taxable_base ?? 0), 2, ',', '.') }} Bs</td>
        </tr>
        <tr>
            <td>IVA:</td>
            <td class="text-right">{{ number_format((float)($detail['montoIva'] ?? $invoice->tax_amount ?? 0), 2, ',', '.') }} Bs</td>
        </tr>
        <tr class="total-row">
            <td>Total a Pagar (Bs):</td>
            <td class="text-right">{{ number_format((float)($detail['montoTotal'] ?? $invoice->total_amount ?? 0), 2, ',', '.') }} Bs</td>
        </tr>
        <tr>
            <td><strong>Total Referencial (USD):</strong></td>
            <td class="text-right"><strong>${{ number_format((float)($detail['montoTotalReferencial'] ?? $invoice->total_usd ?? 0), 2, ',', '.') }}</strong></td>
        </tr>
    </table>
</body>
</html>
