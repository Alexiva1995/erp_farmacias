<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Pago</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #2d3748;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f6f9;
            padding: 30px 0;
        }
        .main-card {
            max-width: 680px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 32px 36px;
            color: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-img {
            max-height: 54px;
            max-width: 180px;
            object-fit: contain;
        }
        .pharmacy-title {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .pharmacy-rif {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 36px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .message {
            font-size: 14px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0284c7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 28px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 0;
            font-size: 13px;
        }
        .summary-label {
            color: #64748b;
            font-weight: 600;
            width: 40%;
        }
        .summary-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }
        .highlight-amount {
            font-size: 16px;
            color: #059669;
            font-weight: 800;
        }
        .table-title {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .invoice-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .invoice-table td {
            padding: 12px 14px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .invoice-table tr:last-child td {
            border-bottom: none;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge-indexed {
            display: inline-block;
            background-color: #fef3c7;
            color: #92400e;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .receipt-preview-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 28px;
        }
        .receipt-preview-img {
            max-width: 100%;
            max-height: 380px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 36px;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #64748b;
            margin: 4px 0;
            line-height: 1.5;
        }
        .footer-legal {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <!-- CABECERA CORPORATIVA -->
            <div class="header">
                <table class="header-table">
                    <tr>
                        <td style="vertical-align: middle;">
                            @if(!empty($pharmacyLogo))
                                <img src="{{ $pharmacyLogo }}" alt="{{ $pharmacyName }}" class="logo-img">
                            @else
                                <h1 class="pharmacy-title">{{ $pharmacyName }}</h1>
                            @endif
                            @if(!empty($pharmacyRif))
                                <div class="pharmacy-rif">RIF: {{ $pharmacyRif }}</div>
                            @endif
                        </td>
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="display: inline-block; background: rgba(255,255,255,0.12); padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                Notificación Oficial de Pago
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- CONTENIDO PRINCIPAL -->
            <div class="content">
                <div class="greeting">Estimado/a aliado comercial: {{ $supplier->name }}</div>
                <div class="message">
                    Le saludamos cordialmente desde <strong>{{ $pharmacyName }}</strong>. A través de la presente comunicación le notificamos que se ha procesado satisfactoriamente el pago de las facturas que se detallan a continuación.
                </div>

                <!-- RESUMEN DEL PAGO -->
                <div class="summary-box">
                    <table class="summary-table">
                        <tr>
                            <td class="summary-label">Fecha de Pago:</td>
                            <td class="summary-value">{{ \Carbon\Carbon::parse($paymentData['payment_date'])->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="summary-label">Forma de Pago:</td>
                            <td class="summary-value">{{ $paymentMethodLabel }}</td>
                        </tr>
                        @if(!empty($paymentData['reference']))
                        <tr>
                            <td class="summary-label">N° de Referencia:</td>
                            <td class="summary-value" style="color: #0284c7; font-family: monospace; font-size: 14px;">#{{ $paymentData['reference'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($paymentData['destination_bank']))
                        <tr>
                            <td class="summary-label">Banco Destino:</td>
                            <td class="summary-value">{{ $paymentData['destination_bank'] }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="summary-label">Monto Liquidado:</td>
                            <td class="summary-value highlight-amount">
                                {{ number_format((float)$paymentData['payment_amount'], 2, ',', '.') }} {{ $paymentData['payment_currency'] ?? 'VES' }}
                            </td>
                        </tr>
                        @if(!empty($paymentData['source_amount']) && !empty($paymentData['source_currency']) && $paymentData['source_currency'] !== ($paymentData['payment_currency'] ?? 'VES'))
                        <tr>
                            <td class="summary-label">Monto en Moneda Origen:</td>
                            <td class="summary-value" style="color: #475569;">
                                {{ number_format((float)$paymentData['source_amount'], 2, ',', '.') }} {{ $paymentData['source_currency'] }} 
                                @if(!empty($paymentData['exchange_rate_applied']))
                                    (Tasa: {{ number_format((float)$paymentData['exchange_rate_applied'], 4, ',', '.') }})
                                @endif
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>

                <!-- TABLA DE FACTURAS LIQUIDADAS -->
                <div class="table-title">
                    📋 Detalle de Facturas Canceladas ({{ count($invoices) }})
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>N° Factura</th>
                            <th>N° Control</th>
                            <th class="text-right">Monto USD</th>
                            <th class="text-right">Monto Liquidado (Bs)</th>
                            <th class="text-center">Condición</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $inv)
                        <tr>
                            <td style="font-weight: 700; color: #0284c7;">#{{ $inv->invoice_number }}</td>
                            <td style="font-family: monospace;">{{ !empty($inv->control_number) && $inv->control_number !== 'N/A' ? $inv->control_number : 'S/N' }}</td>
                            <td class="text-right">${{ number_format((float)($inv->total_usd ?? 0), 2, ',', '.') }}</td>
                            <td class="text-right" style="font-weight: 700; color: #059669;">
                                {{ number_format((float)($inv->total_amount_bs ?? ($inv->total_amount ?? 0)), 2, ',', '.') }} Bs
                            </td>
                            <td class="text-center">
                                @if($inv->is_indexed)
                                    <span class="badge-indexed">Indexada</span>
                                @else
                                    <span style="font-size: 11px; color: #64748b;">Estándar</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- COMPROBANTE DE PAGO ADJUNTO/VISTA PREVIA -->
                @if(!empty($receiptPhotoUrl))
                <div class="table-title">
                    📎 Soporte / Comprobante de Transferencia
                </div>
                <div class="receipt-preview-box">
                    <img src="{{ $receiptPhotoUrl }}" alt="Comprobante de Pago" class="receipt-preview-img">
                    <div style="margin-top: 10px; font-size: 12px; color: #64748b;">
                        Archivo adjunto: Soporte de transacción emitido por la entidad bancaria.
                    </div>
                </div>
                @endif

                <div class="message" style="margin-top: 24px; font-size: 13px;">
                    Agradecemos confirmar la recepción y conciliación de este pago en su sistema. Para cualquier duda o aclaratoria, puede comunicarse directamente con nuestro departamento de administración o cobranza.
                </div>
            </div>

            <!-- PIE DE PÁGINA CORPORATIVO -->
            <div class="footer">
                <p><strong>{{ $pharmacyName }}</strong></p>
                @if(!empty($pharmacyRif))
                    <p>RIF: {{ $pharmacyRif }} @if(!empty($pharmacyAddress)) | {{ $pharmacyAddress }} @endif</p>
                @endif
                <p class="footer-legal">
                    Este es un mensaje generado automáticamente por el sistema administrativo de {{ $pharmacyName }}. Por favor no responda directamente a este correo si es una dirección desatendida.
                </p>
            </div>
        </div>
    </div>
</body>
</html>