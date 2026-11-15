<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobro de Mensualidad — TOVA</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 30px 10px;
            color: #1a1a2e;
        }
        .wrapper {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e8eaf0;
        }
        .header {
            background: linear-gradient(135deg, #7A0099 0%, #E20074 100%);
            padding: 36px 24px;
            text-align: center;
        }
        .header-brand {
            font-size: 32px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: 2px;
            line-height: 1;
            margin-bottom: 4px;
        }
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 6px 16px;
            border-radius: 20px;
            margin-top: 14px;
        }
        .content {
            padding: 32px 26px;
        }
        .title {
            font-size: 20px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .subtitle {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 22px;
        }
        .invoice-card {
            background: #f8f9fc;
            border: 1px solid #e8eaf0;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table td {
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #edf0f5;
        }
        .invoice-table tr:last-child td {
            border-bottom: none;
            padding-bottom: 0;
        }
        .invoice-table tr:first-child td {
            padding-top: 0;
        }
        .invoice-label {
            color: #6b7280;
            font-weight: 600;
            width: 45%;
        }
        .invoice-value {
            color: #1a1a2e;
            font-weight: 700;
            text-align: right;
        }
        .total-box {
            background: linear-gradient(135deg, rgba(122, 0, 153, 0.06), rgba(226, 0, 116, 0.06));
            border-left: 4px solid #E20074;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .total-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7A0099;
            margin-bottom: 4px;
        }
        .total-amount {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a2e;
        }
        .total-sub {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
        }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #9ca3af;
            margin-bottom: 14px;
        }
        .payment-method {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .payment-method-header {
            font-weight: 700;
            font-size: 15px;
            color: #1a1a2e;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table td {
            padding: 8px 0;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
        }
        .data-table tr:last-child td {
            border-bottom: none;
            padding-bottom: 0;
        }
        .data-table tr:first-child td {
            padding-top: 0;
        }
        .data-label {
            color: #64748b;
            font-weight: 600;
            width: 35%;
        }
        .data-val {
            color: #0f172a;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        .footer {
            background: #1a1a2e;
            padding: 22px;
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            line-height: 1.5;
        }
        .footer strong {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Cabecera de Marca TOVA -->
        <div class="header">
            @if(file_exists(public_path('logo.png')))
                <div>
                    <img src="{{ $message->embed(public_path('logo.png')) }}" alt="TOVA" style="max-width: 170px; height: auto; background: #ffffff; padding: 8px 18px; border-radius: 12px; display: inline-block;">
                </div>
            @else
                <div class="header-brand">TOVA</div>
            @endif
            <div>
                <span class="badge">Facturación y Cobranza</span>
            </div>
        </div>

        <!-- Contenido -->
        <div class="content">
            <h2 class="title">Recordatorio de Pago de Mensualidad</h2>
            <p class="subtitle">
                Hola, te compartimos los datos para la renovación de tu mensualidad de servicio en la plataforma:
            </p>

            <!-- Resumen del servicio -->
            <div class="invoice-card">
                <table class="invoice-table">
                    <tr>
                        <td class="invoice-label">Sitio Web:</td>
                        <td class="invoice-value"><a href="{{ $data['site_url'] ?? 'https://tovaerp.com/' }}" target="_blank" style="color: #7A0099; text-decoration: none;">{{ $data['site_name'] ?? 'tovaerp.com' }}</a></td>
                    </tr>
                    @if(!empty($data['plan_name']))
                    <tr>
                        <td class="invoice-label">Plan Contratado:</td>
                        <td class="invoice-value">{{ $data['plan_name'] }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="invoice-label">Vigencia / Período:</td>
                        <td class="invoice-value">{{ $data['period'] ?? 'Mensualidad Vigente' }}</td>
                    </tr>
                    @if(!empty($data['discount_usd']) && (float) $data['discount_usd'] > 0)
                    <tr>
                        <td class="invoice-label">Precio Regular Plan:</td>
                        <td class="invoice-value" style="color: #9ca3af; text-decoration: line-through;">{{ number_format((float) ($data['plan_price_usd'] ?? 0), 2, '.', ',') }} USD</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Descuento / Ahorro:</td>
                        <td class="invoice-value" style="color: #16a34a;">-{{ number_format((float) $data['discount_usd'], 2, '.', ',') }} USD (${{ number_format((float) ($data['discount_cop'] ?? 0), 0, ',', '.') }} COP)</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="invoice-label">Monto a Pagar USD:</td>
                        <td class="invoice-value">{{ number_format((float) ($data['usd_amount'] ?? 29), 2, '.', ',') }} USD</td>
                    </tr>
                    <tr>
                        <td class="invoice-label">Tasa de Cambio:</td>
                        <td class="invoice-value">{{ number_format((float) ($data['rate'] ?? 3400), 0, ',', '.') }} COP</td>
                    </tr>
                </table>
            </div>

            <!-- Total a pagar -->
            <div class="total-box">
                <div class="total-title">Total a Pagar</div>
                <div class="total-amount">${{ number_format((float) ($data['cop_amount'] ?? (($data['usd_amount'] ?? 29) * ($data['rate'] ?? 3400))), 0, ',', '.') }} COP <span style="font-size: 15px; font-weight: 500; color: #6b7280;">/ {{ number_format((float) ($data['usd_amount'] ?? 29), 2, '.', ',') }} USD</span></div>
                <div class="total-sub">Cálculo: {{ number_format((float) ($data['usd_amount'] ?? 29), 2, '.', ',') }} USD × {{ number_format((float) ($data['rate'] ?? 3400), 0, ',', '.') }} COP = ${{ number_format((float) ($data['cop_amount'] ?? (($data['usd_amount'] ?? 29) * ($data['rate'] ?? 3400))), 0, ',', '.') }} COP</div>
            </div>

            <!-- Formas de pago -->
            <div class="section-title">Datos para Realizar la Transferencia</div>

            <!-- Bancolombia -->
            <div class="payment-method">
                <div class="payment-method-header">🏦 Bancolombia (COP)</div>
                <table class="data-table">
                    <tr>
                        <td class="data-label">N° de Cuenta:</td>
                        <td class="data-val">{{ $data['banco_cuenta'] ?? '91201530453' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Titular:</td>
                        <td class="data-val">{{ $data['banco_titular'] ?? 'Estefanny Torrado' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Cédula (C.C.):</td>
                        <td class="data-val">{{ $data['banco_cc'] ?? '1148456331' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Monto COP:</td>
                        <td class="data-val">${{ number_format((float) ($data['cop_amount'] ?? (($data['usd_amount'] ?? 29) * ($data['rate'] ?? 3400))), 0, ',', '.') }} COP</td>
                    </tr>
                </table>
            </div>

            <!-- Binance -->
            <div class="payment-method">
                <div class="payment-method-header">🟡 Binance Pay (USDT / Crypto)</div>
                <table class="data-table">
                    <tr>
                        <td class="data-label">Correo Binance:</td>
                        <td class="data-val">{{ $data['binance_email'] ?? 'Alexisjose95@gmail.com' }}</td>
                    </tr>
                    <tr>
                        <td class="data-label">Monto USD:</td>
                        <td class="data-val">{{ number_format((float) ($data['usd_amount'] ?? 29), 2, '.', ',') }} USDT / USD</td>
                    </tr>
                </table>
            </div>

            <p style="font-size: 13px; color: #6b7280; margin-top: 20px; line-height: 1.5;">
                Una vez realizado el pago, por favor envía el comprobante respondiendo a este mensaje para registrar la renovación de tu servicio.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Gracias por confiar en <strong>TOVA</strong>.</p>
            <p style="margin-top: 4px;">Soporte y atención continua para tu negocio.</p>
        </div>
    </div>
</body>
</html>
