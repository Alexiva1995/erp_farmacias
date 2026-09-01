<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Fiscal — {{ $detail['nroFactura'] ?? $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 6mm 8mm 6mm 10mm;
            size: letter portrait;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            color: #000000;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        /* Encabezado */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .logo-col {
            width: 16%;
            vertical-align: middle;
            text-align: center;
        }
        .company-col {
            width: 52%;
            vertical-align: top;
            padding-left: 6px;
            font-size: 7.5px;
            line-height: 1.25;
        }
        .company-title {
            font-size: 9px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 2px;
        }
        .invoice-box-col {
            width: 32%;
            vertical-align: top;
            text-align: right;
        }
        .blue-header-badge {
            background-color: #0d47a1;
            color: #ffffff;
            padding: 8px 10px;
            text-align: right;
        }
        .badge-factura-num {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .badge-date-info {
            font-size: 7.5px;
            font-weight: normal;
            line-height: 1.3;
        }

        .cobeca-name {
            font-size: 9px;
            font-weight: bold;
            color: #0d47a1;
            margin-top: 4px;
        }
        .cobeca-sub {
            font-size: 6.5px;
            font-weight: bold;
            color: #333333;
        }

        /* Sección de Datos de Cliente y Control Fiscal */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 8px;
        }
        .info-col-left {
            width: 58%;
            vertical-align: top;
            padding-right: 10px;
            line-height: 1.35;
        }
        .info-col-right {
            width: 42%;
            vertical-align: top;
            line-height: 1.35;
        }
        .control-num-highlight {
            font-size: 10px;
            font-weight: bold;
            color: #000000;
        }

        /* Tabla de Renglones / Artículos */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .items-table th {
            background-color: #0a3871;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 3px;
            text-align: left;
            border-right: 1px solid #1a4d8c;
        }
        .items-table th:last-child {
            border-right: none;
        }
        .items-table td {
            padding: 3px 3px;
            font-size: 7.5px;
            border-bottom: 0.5px solid #e0e0e0;
        }
        .cesta-header-row td {
            background-color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            color: #333333;
            padding: 3px 2px 2px 2px;
            border-bottom: none;
        }

        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold !important; }

        /* Pie de página y Totales */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .footer-left-notes {
            width: 55%;
            vertical-align: top;
            font-size: 7px;
            color: #555555;
            padding-right: 15px;
            line-height: 1.3;
        }
        .footer-totals {
            width: 45%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        .totals-table td {
            padding: 2.5px 4px;
            border-bottom: 1px solid #e5e5e5;
        }
        .totals-main-row {
            background-color: #e8f0fe;
            font-weight: bold;
            color: #0d47a1;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <!-- ================= ENCABEZADO OFICIAL ================= -->
    <table class="header-table">
        <tr>
            <!-- Logo -->
            <td class="logo-col">
                <div class="cobeca-name">C.A. MAFARTA</div>
                <div class="cobeca-sub">SAN CRISTÓBAL</div>
                <div style="font-size:6px; color:#666;">DROGUERÍAS COBECA</div>
            </td>

            <!-- Datos de la Empresa -->
            <td class="company-col">
                <div class="company-title">COMPAÑÍA ANÓNIMA MAFARTA (C.A. MAFARTA)</div>
                <div><strong>RIF:</strong> J-07001225-0</div>
                <div>Calle Principal Riberas Del Torbes Local Galpón Nro L-04, Sector Barrancas Tariba, Táchira, Zona Postal 5017</div>
                <div>Telf Master: (0276) 4202300 &bull; Telf: 0273-5153009</div>
                <div>Capital Social: Bs.20.000.000,00 &bull; Cód. Act. Contribuyente: 8590</div>
            </td>

            <!-- Cuadro Azul Superior Derecho (Factura N°) -->
            <td class="invoice-box-col">
                <div class="blue-header-badge">
                    <div class="badge-factura-num">Factura N° {{ $detail['nroFactura'] ?? $invoice->invoice_number }}</div>
                    <div class="badge-date-info">
                        Fecha Emisión: {{ !empty($detail['fechaFactura']) ? \Carbon\Carbon::parse($detail['fechaFactura'])->format('d/m/Y') : ($invoice->created_invoice_date ? $invoice->created_invoice_date->format('d/m/Y') : date('d/m/Y')) }}<br>
                        Hora de Emisión: {{ $detail['horaFactura'] ?? '12:00:00PM' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= DATOS CLIENTE Y CONTROL ================= -->
    <table class="info-table">
        <tr>
            <td class="info-col-left">
                <div><strong>Razon Social:</strong> {{ $detail['descCliente'] ?? 'FARMACIA BARRIO SUCRE 2024, C.A' }}</div>
                <div><strong>Dir. Fiscal:</strong> {{ $detail['dirCliente'] ?? 'CALLE PRINCIPAL LOCAL 05 (L5) SECTOR BARRIO SUCRE LA FRIA EDO. TACHIRA' }}</div>
                <div><strong>N° R.I.F:</strong> {{ $detail['rifCliente'] ?? 'J505406957' }}</div>
            </td>
            <td class="info-col-right">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:50%;"><strong>N° Control</strong></td>
                        <td style="width:50%; text-align:right;"><span class="control-num-highlight">{{ $detail['nroControl'] ?? $invoice->control_number ?? 'N/A' }}</span></td>
                    </tr>
                    <tr>
                        <td>Fecha asig Nro Control:</td>
                        <td style="text-align:right;">{{ !empty($detail['fechaFactura']) ? \Carbon\Carbon::parse($detail['fechaFactura'])->format('d/m/Y') : ($invoice->created_invoice_date ? $invoice->created_invoice_date->format('d/m/Y') : date('d/m/Y')) }}</td>
                    </tr>
                    <tr>
                        <td>Cód. Cliente:</td>
                        <td style="text-align:right;">{{ $detail['codCliente'] ?? '31373' }}</td>
                    </tr>
                    <tr>
                        <td>Orden desp:</td>
                        <td style="text-align:right;">{{ $detail['nroPedido'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td>Con Crédito:</td>
                        <td style="text-align:right;">{{ $detail['condicion'] ?? '25' }} DIAS</td>
                    </tr>
                    <tr>
                        <td>Ruta:</td>
                        <td style="text-align:right;">054</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= TABLA DE PRODUCTOS ================= -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 7%;">CÓDIGO</th>
                <th style="width: 32%;">ARTÍCULO</th>
                <th style="width: 9%;" class="text-center">LOTE</th>
                <th style="width: 7%;" class="text-center">VENCE</th>
                <th style="width: 7%;" class="text-center">MED/PRO</th>
                <th style="width: 4%;" class="text-center">UND</th>
                <th style="width: 8%;" class="text-right">MAYOR</th>
                <th style="width: 5%;" class="text-right">%COBECA</th>
                <th style="width: 4%;" class="text-right">%ADIC</th>
                <th style="width: 4%;" class="text-right">%PROV</th>
                <th style="width: 8%;" class="text-right">P.FINAL</th>
                <th style="width: 7%;" class="text-right">P.USD</th>
                <th style="width: 8%;" class="text-right">TOTAL</th>
                <th style="width: 8%;" class="text-right">T.USD</th>
            </tr>
        </thead>
        <tbody>
            @php
                $detallesList = !empty($detail['detalles']) ? $detail['detalles'] : [];
                $firstCesta = !empty($detallesList[0]['cesta']) ? $detallesList[0]['cesta'] : '000001';
            @endphp

            <tr class="cesta-header-row">
                <td colspan="14"><strong>Cesta: (1 / {{ max(1, count($detallesList)) }}) {{ $firstCesta }}</strong></td>
            </tr>

            @if(count($detallesList) > 0)
                @foreach($detallesList as $item)
                    @php
                        $venceFmt = !empty($item['fevencimiento']) ? \Carbon\Carbon::parse($item['fevencimiento'])->format('m-y') : 'N/A';
                        $pMayor = (float)($item['montoMayor'] ?? $item['montoOferta'] ?? 0);
                        $pFinal = (float)($item['montoOferta'] ?? $item['montoMayor'] ?? 0);
                        $pUsd = (float)($item['montoOfertaReferencial'] ?? 0);
                        $totBs = (float)($item['montoTotal'] ?? ($pFinal * ($item['cantidadFacturada'] ?? 1)));
                        $totUsd = (float)($item['montoTotalReferencial'] ?? 0);
                        $isExento = !empty($item['extento']) && (int)$item['extento'] === 1;
                    @endphp
                    <tr>
                        <td>{{ $item['codArticulo'] ?? '' }}</td>
                        <td>{{ $item['descArticulo'] ?? '' }}</td>
                        <td class="text-center">{{ $item['nulotefabric'] ?? 'N/A' }}</td>
                        <td class="text-center">{{ $venceFmt }}</td>
                        <td class="text-center">GEN</td>
                        <td class="text-center">{{ (int)($item['cantidadFacturada'] ?? 1) }}</td>
                        <td class="text-right">{{ number_format($pMayor, 2, ',', '.') }}</td>
                        <td class="text-right">0</td>
                        <td class="text-right">0</td>
                        <td class="text-right">0</td>
                        <td class="text-right">{{ number_format($pFinal, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($pUsd, 2, '.', '') }}</td>
                        <td class="text-right font-bold">{{ number_format($totBs, 2, ',', '.') }}</td>
                        <td class="text-right font-bold">{{ number_format($totUsd, 2, '.', '') }} {{ $isExento ? '(E)' : '' }}</td>
                    </tr>
                @endforeach
            @elseif($invoice->details && count($invoice->details) > 0)
                @foreach($invoice->details as $d)
                    <tr>
                        <td>{{ $d->codigo_producto ?? $d->barcode ?? '' }}</td>
                        <td>{{ $d->descripcion_producto ?? '' }}</td>
                        <td class="text-center">{{ $d->lot_number ?? 'N/A' }}</td>
                        <td class="text-center">{{ $d->expiration_date ? $d->expiration_date->format('m-y') : 'N/A' }}</td>
                        <td class="text-center">GEN</td>
                        <td class="text-center">{{ (int)$d->quantity }}</td>
                        <td class="text-right">{{ number_format((float)$d->unit_cost, 2, ',', '.') }}</td>
                        <td class="text-right">0</td>
                        <td class="text-right">0</td>
                        <td class="text-right">0</td>
                        <td class="text-right">{{ number_format((float)$d->unit_cost, 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float)$d->unit_cost / max(1, (float)$invoice->exchange_rate), 2, '.', '') }}</td>
                        <td class="text-right font-bold">{{ number_format((float)$d->total_cost, 2, ',', '.') }}</td>
                        <td class="text-right font-bold">{{ number_format((float)$d->total_cost / max(1, (float)$invoice->exchange_rate), 2, '.', '') }} (E)</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- ================= TOTALES Y OBSERVACIONES ================= -->
    <table class="footer-table">
        <tr>
            <td class="footer-left-notes">
                <div><strong>Condiciones de Pago:</strong> Pago a crédito según acuerdo comercial.</div>
                <div><strong>Tasa de Cambio Oficial (BCV):</strong> {{ number_format((float)($detail['tasaReferencial'] ?? $invoice->exchange_rate ?? 1), 2, ',', '.') }} Bs/USD</div>
                @if($invoice->is_indexed)
                    <div style="margin-top:4px; font-weight:bold; color:#b45309;">&bull; FACTURA INDEXADA AL DIFERENCIAL CAMBIARIO (FA$)</div>
                @endif
            </td>
            <td class="footer-totals">
                <table class="totals-table">
                    <tr>
                        <td>Base Exenta:</td>
                        <td class="text-right">{{ number_format((float)($detail['montoSubTotal'] ?? $invoice->exempt_amount ?? 0), 2, ',', '.') }} Bs</td>
                    </tr>
                    <tr>
                        <td>Base Imponible:</td>
                        <td class="text-right">{{ number_format((float)($invoice->taxable_base ?? 0), 2, ',', '.') }} Bs</td>
                    </tr>
                    <tr>
                        <td>IVA (16%):</td>
                        <td class="text-right">{{ number_format((float)($detail['montoIva'] ?? $invoice->tax_amount ?? 0), 2, ',', '.') }} Bs</td>
                    </tr>
                    <tr class="totals-main-row">
                        <td>TOTAL A PAGAR (Bs):</td>
                        <td class="text-right">{{ number_format((float)($detail['montoTotal'] ?? $invoice->total_amount ?? 0), 2, ',', '.') }} Bs</td>
                    </tr>
                    <tr>
                        <td><strong>Total Referencial (USD):</strong></td>
                        <td class="text-right"><strong>${{ number_format((float)($detail['montoTotalReferencial'] ?? $invoice->total_usd ?? 0), 2, '.', '') }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
