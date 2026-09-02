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
            width: 18%;
            vertical-align: middle;
            text-align: center;
        }
        .company-col {
            width: 50%;
            vertical-align: top;
            padding-left: 6px;
            font-size: 7.5px;
            line-height: 1.25;
        }
        .company-title {
            font-size: 10px;
            font-weight: bold;
            color: #0f4c81;
            margin-bottom: 2px;
        }
        .invoice-box-col {
            width: 32%;
            vertical-align: top;
            text-align: right;
        }
        .dromega-header-badge {
            background-color: #0f4c81;
            color: #ffffff;
            padding: 8px 10px;
            text-align: right;
            border-radius: 4px;
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

        .dromega-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f4c81;
            margin-top: 2px;
        }
        .dromega-sub {
            font-size: 7px;
            font-weight: bold;
            color: #555555;
            letter-spacing: 1px;
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
            font-size: 9px;
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
            background-color: #0f4c81;
            color: #ffffff;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 3px;
            text-align: left;
            border-right: 1px solid #236fa8;
        }
        .items-table th:last-child {
            border-right: none;
        }
        .items-table td {
            padding: 3.5px 3px;
            font-size: 7.5px;
            border-bottom: 0.5px solid #e0e0e0;
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
            width: 50%;
            vertical-align: top;
            font-size: 7px;
            color: #555555;
            padding-right: 15px;
            line-height: 1.3;
        }
        .footer-totals {
            width: 50%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        .totals-table td {
            padding: 3px 4px;
            border-bottom: 1px solid #e5e5e5;
        }
        .totals-main-row {
            background-color: #e8f0fe;
            font-weight: bold;
            color: #0f4c81;
            font-size: 9.5px;
        }
    </style>
</head>
<body>
    <!-- ================= ENCABEZADO OFICIAL ================= -->
    <table class="header-table">
        <tr>
            <!-- Logo / Nombre -->
            <td class="logo-col">
                <div class="dromega-name">&#128138; DROMEGA</div>
                <div class="dromega-sub">MÉRIDA</div>
                <div style="font-size:6.5px; color:#777; margin-top:2px;">DROGUERÍA MEGA C.A.</div>
            </td>

            <!-- Datos de la Empresa -->
            <td class="company-col">
                <div class="company-title">DROGUERÍA MEGA C.A. (DROMEGA)</div>
                <div><strong>RIF:</strong> J-30784790-5</div>
                <div>Mérida, Estado Mérida, Venezuela</div>
                <div>Operador de Ventas: {{ $detail['operadorVentas'] ?? 'Ventas 3' }} &bull; Telf: {{ $detail['telefonoOperador'] ?? '0414-7546671' }}</div>
                <div>Operador de Cobranza: {{ $detail['operadorCobranza'] ?? 'Yelitza Dávila' }}</div>
            </td>

            <!-- Cuadro Azul Superior Derecho (Factura N°) -->
            <td class="invoice-box-col">
                <div class="dromega-header-badge">
                    <div class="badge-factura-num">Factura N° {{ $detail['nroFactura'] ?? $invoice->invoice_number }}</div>
                    <div class="badge-date-info">
                        Fecha Emisión: {{ $detail['fecha'] ?? ($invoice->created_invoice_date ? $invoice->created_invoice_date->format('d/m/Y') : date('d/m/Y')) }}<br>
                        Tasa Cambio: {{ !empty($detail['tasaCambio']) ? number_format((float)$detail['tasaCambio'], 4, ',', '.') : number_format((float)($invoice->exchange_rate ?? 1), 4, ',', '.') }} Bs/$
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= DATOS CLIENTE Y CONTROL ================= -->
    <table class="info-table">
        <tr>
            <td class="info-col-left">
                <div><strong>Razón Social:</strong> {{ $detail['descCliente'] ?? 'FARMACIA BARRIO SUCRE 2024, C.A' }}</div>
                <div><strong>Dir. Fiscal:</strong> {{ $detail['dirCliente'] ?? 'CALLE PRINCIPAL LOCAL 05 (L5) SECTOR BARRIO SUCRE LA FRIA EDO. TACHIRA' }}</div>
                <div><strong>N° R.I.F:</strong> {{ $detail['rifCliente'] ?? 'J-50540695-7' }}</div>
            </td>
            <td class="info-col-right">
                <table style="width:100%; border-collapse:collapse;">
                    <tr>
                        <td style="width:50%;"><strong>N° Control:</strong></td>
                        <td style="width:50%; text-align:right;"><span class="control-num-highlight">{{ $detail['nroControl'] ?? $invoice->control_number ?? 'N/A' }}</span></td>
                    </tr>
                    <tr>
                        <td>Cód. Cliente:</td>
                        <td style="text-align:right;">{{ $detail['codCliente'] ?? '7586' }}</td>
                    </tr>
                    <tr>
                        <td>Vencimiento:</td>
                        <td style="text-align:right;">{{ $detail['vencimiento'] ?? ($invoice->exp_date ? \Carbon\Carbon::parse($invoice->exp_date)->format('d/m/Y') : 'N/A') }}</td>
                    </tr>
                    <tr>
                        <td>Protección Tasa:</td>
                        <td style="text-align:right;">{{ $detail['vencimientoProteccion'] ?? ($invoice->payment_date ? \Carbon\Carbon::parse($invoice->payment_date)->format('d/m/Y') : 'N/A') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= TABLA DE PRODUCTOS ================= -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;" class="text-center">Código</th>
                <th style="width: 14%;" class="text-center">Cód. Barras</th>
                <th style="width: 38%;">Descripción del Producto</th>
                <th style="width: 6%;" class="text-center">Cant</th>
                <th style="width: 10%;" class="text-right">Precio Unit</th>
                <th style="width: 8%;" class="text-center">Desc %</th>
                <th style="width: 8%;" class="text-right">Total Bs.</th>
                <th style="width: 8%;" class="text-right">Total $</th>
            </tr>
        </thead>
        <tbody>
            @if(!empty($detail['items']) && count($detail['items']) > 0)
                @foreach($detail['items'] as $item)
                    <tr>
                        <td class="text-center">{{ $item['codigo'] ?? '' }}</td>
                        <td class="text-center">{{ $item['codigo_barras'] ?? '' }}</td>
                        <td><strong>{{ $item['descripcion'] ?? '' }}</strong></td>
                        <td class="text-center font-bold">{{ $item['cantidad'] ?? 1 }}</td>
                        <td class="text-right">{{ $item['precio_unitario'] ?? '0,00' }}</td>
                        <td class="text-center">{{ $item['descuento'] ?? '0' }}</td>
                        <td class="text-right font-bold">{{ $item['total_bs'] ?? '0,00' }}</td>
                        <td class="text-right font-bold" style="color: #0f4c81;">${{ $item['total_usd'] ?? '0,00' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td class="text-center">001</td>
                    <td class="text-center">-</td>
                    <td><strong>COMPRA DE MEDICAMENTOS SEGÚN FACTURA #{{ $invoice->invoice_number }}</strong></td>
                    <td class="text-center font-bold">1</td>
                    <td class="text-right">{{ number_format((float)$invoice->total_amount, 2, ',', '.') }}</td>
                    <td class="text-center">0</td>
                    <td class="text-right font-bold">{{ number_format((float)$invoice->net_payable_amount ?: (float)$invoice->total_amount, 2, ',', '.') }}</td>
                    <td class="text-right font-bold" style="color: #0f4c81;">${{ number_format((float)$invoice->total_usd, 2, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- ================= PIE DE PÁGINA Y TOTALES ================= -->
    <table class="footer-table">
        <tr>
            <td class="footer-left-notes">
                <div style="font-weight: bold; margin-bottom: 3px; color: #333;">INFORMACIÓN ADICIONAL / OBSERVACIONES:</div>
                <div>Factura generada y validada automáticamente desde el portal oficial de Droguería Mega C.A.</div>
                <div style="margin-top: 4px;"><strong>Condición:</strong> Crédito Droguería Mega &bull; Sucursal Mérida</div>
                <div style="margin-top: 4px; font-size: 6.5px; color: #777;">Documento emitido para fines de control administrativo interno y conciliación contable.</div>
            </td>
            <td class="footer-totals">
                <table class="totals-table">
                    <tr>
                        <td style="width: 50%;">Subtotal:</td>
                        <td style="width: 25%; text-align: right;">Bs. {{ $detail['totales']['subtotal_bs'] ?? number_format((float)$invoice->total_amount, 2, ',', '.') }}</td>
                        <td style="width: 25%; text-align: right; color: #0f4c81;">${{ $detail['totales']['subtotal_usd'] ?? number_format((float)$invoice->total_usd, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Descuento Global:</td>
                        <td style="text-align: right;">Bs. {{ $detail['totales']['descuento_bs'] ?? '0,00' }}</td>
                        <td style="text-align: right; color: #0f4c81;">${{ $detail['totales']['descuento_usd'] ?? '0,00' }}</td>
                    </tr>
                    <tr>
                        <td>I.V.A.:</td>
                        <td style="text-align: right;">Bs. {{ $detail['totales']['iva_bs'] ?? number_format((float)$invoice->tax_amount, 2, ',', '.') }}</td>
                        <td style="text-align: right; color: #0f4c81;">${{ $detail['totales']['iva_usd'] ?? '0,00' }}</td>
                    </tr>
                    <tr class="totals-main-row">
                        <td>TOTAL A PAGAR:</td>
                        <td style="text-align: right;">Bs. {{ $detail['totales']['total_bs'] ?? number_format((float)$invoice->net_payable_amount ?: (float)$invoice->total_amount, 2, ',', '.') }}</td>
                        <td style="text-align: right; color: #0f4c81;">${{ $detail['totales']['total_usd'] ?? number_format((float)$invoice->total_usd, 2, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
