<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reporte de Cierre #{{ $cashClosing->id }}</title>
    <style>
        @page {
            margin: 12mm 15mm;
            size: letter portrait;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }
        * { box-sizing: border-box; }
        
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .logo {
            max-width: 160px;
            max-height: 80px;
            height: auto;
            width: auto;
            display: block;
        }
        .title-section {
            text-align: right;
        }
        .report-title {
            margin: 0 0 5px 0;
            color: #1a365d;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .info-text {
            color: #555;
            font-size: 11px;
            margin: 2px 0;
        }
        .divider {
            border: 0;
            border-top: 2px solid #2b6cb0;
            margin-bottom: 15px;
        }
        .section-title {
            border-bottom: 1px solid #e2e8f0;
            color: #2b6cb0;
            font-size: 13px;
            margin: 15px 0 10px 0;
            padding-bottom: 4px;
            font-weight: bold;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 15px;
        }
        table.data-table th {
            background-color: #f7fafc;
            border: 1px solid #cbd5e0;
            color: #2d3748;
            padding: 6px 8px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
        }
        table.data-table td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
        }
        .item-detail {
            border-bottom: 1px dashed #edf2f7;
            padding-bottom: 3px;
            margin-bottom: 3px;
        }
        .item-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-success { color: #276749; }
        .text-muted { color: #718096; font-size: 9.5px; }
        .bg-total { background-color: #f7fafc; }
        
        .footer {
            border-top: 1px solid #e2e8f0;
            color: #a0aec0;
            font-size: 9px;
            margin-top: 25px;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- CABECERA -->
    <table class="header-table">
        <tr>
            <td style="width: 35%;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo" alt="Logo" />
                @else
                    <h3 style="margin: 0; color: #2b6cb0;">{{ $setting->app_name ?? 'FARMACIA' }}</h3>
                @endif
            </td>
            <td class="title-section" style="width: 65%;">
                <div class="report-title">Reporte de Ventas por Cierre</div>
                <div class="info-text">Cierre N°: <strong>{{ $cashClosing->id }}</strong></div>
                <div class="info-text">Fecha: <strong>{{ \Carbon\Carbon::parse($cashClosing->closing_date)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</strong></div>
                <div class="info-text">Vendedor: <strong>{{ strtoupper($cashClosing->seller->username ?? 'Sin Nombre') }}</strong></div>
            </td>
        </tr>
    </table>

    <hr class="divider" />

    <!-- FACTURAS -->
    <div class="section-title">Detalle de Facturas</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 12%;">Factura #</th>
                <th class="text-left" style="width: 60%;">Productos (Cantidad x Descripción)</th>
                <th class="text-right" style="width: 28%;">Totales</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashClosing->orders as $order)
                <tr>
                    <td class="text-center font-bold">{{ $order->id }}</td>
                    <td>
                        @if($order->details && $order->details->count() > 0)
                            @foreach($order->details as $detail)
                                <div class="item-detail">
                                    <span class="font-bold">{{ $detail->quantity }}x</span>
                                    <span>{{ $detail->product->name ?? 'Producto' }}</span>
                                    <span class="font-bold" style="color: #4a5568;">
                                        - {{ number_format($detail->price, 2, ',', '.') }} {{ strtoupper($order->currency ?? 'USD') }}
                                    </span>
                                    <span class="text-muted">(ID: {{ $detail->product_id }})</span>
                                </div>
                            @endforeach
                        @else
                            <span class="text-muted" style="font-style: italic;">Sin desglose de productos</span>
                        @endif
                    </td>
                    <td class="text-right bg-total" style="vertical-align: bottom;">
                        <div class="font-bold" style="font-size: 11px; color: #1a365d;">
                            Total: {{ number_format($order->total_amount, 2, ',', '.') }} {{ strtoupper($order->currency ?? 'USD') }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center text-muted" style="padding: 15px;">
                        No hay ventas registradas en este cierre.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ABONOS DE CRÉDITO -->
    @if($cashClosing->creditPayments && $cashClosing->creditPayments->count() > 0)
        <div class="section-title" style="margin-top: 20px;">Abonos de Crédito Recibidos</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 10%;">Abono #</th>
                    <th class="text-left" style="width: 35%;">Cliente</th>
                    <th class="text-left" style="width: 30%;">Método de Pago</th>
                    <th class="text-right" style="width: 25%;">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cashClosing->creditPayments as $payment)
                    @php
                        $paymentMethods = is_string($payment->method_Payment) ? json_decode($payment->method_Payment, true) : ($payment->method_Payment ?? []);
                        $translations = [
                            'cash' => 'Efectivo',
                            'cash_usd' => 'Efectivo USD',
                            'cash_cop' => 'Efectivo COP',
                            'cash_bs' => 'Efectivo Bs.',
                            'transfer' => 'Transferencia',
                            'transfer_usd' => 'Transferencia USD',
                            'transfer_cop' => 'Transferencia COP',
                            'transfer_bs' => 'Transferencia Bs.',
                            'mobile' => 'Pago Móvil',
                            'mobile_payment' => 'Pago Móvil',
                            'bs_mobile' => 'Pago Móvil',
                            'debit_card' => 'Tarjeta de Débito',
                            'credit_card' => 'Tarjeta de Crédito',
                            'bs_card_debito' => 'Tarjeta de Débito',
                            'bs_card_credit' => 'Tarjeta de Crédito',
                            'card' => 'Tarjeta',
                            'paypal' => 'PayPal',
                            'binance' => 'Binance',
                            'zelle' => 'Zelle',
                            'credit' => 'Crédito',
                        ];
                    @endphp
                    <tr>
                        <td class="text-center font-bold">{{ $payment->id }}</td>
                        <td>
                            <div class="font-bold" style="color: #2d3748;">
                                {{ $payment->client->name ?? 'Cliente General' }}
                            </div>
                            @if(!empty($payment->client->identification))
                                <div class="text-muted">C.I / RIF: {{ $payment->client->identification }}</div>
                            @endif
                        </td>
                        <td>
                            @if(is_array($paymentMethods) && count($paymentMethods) > 0)
                                @foreach($paymentMethods as $m)
                                    @php
                                        $methodKey = strtolower(trim($m['method'] ?? ($m['type'] ?? '')));
                                        $translated = $translations[$methodKey] ?? ucwords(str_replace('_', ' ', $methodKey ?: 'Pago'));
                                        $curr = strtoupper($m['currency'] ?? 'USD');
                                        $amt = (float)($m['amount'] ?? 0);
                                    @endphp
                                    <div style="font-size: 10px; color: #4a5568; margin-bottom: 2px;">
                                        {{ $translated }}: {{ number_format($amt, 2, ',', '.') }} {{ $curr }}
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted" style="font-style: italic;">Efectivo / No especificado</span>
                            @endif
                        </td>
                        <td class="text-right bg-total" style="vertical-align: middle;">
                            @php
                                $totalPayment = 0;
                                $currency = 'USD';
                                if (is_array($paymentMethods) && count($paymentMethods) > 0) {
                                    foreach ($paymentMethods as $pm) {
                                        $totalPayment += (float)($pm['amount'] ?? 0);
                                        $currency = strtoupper($pm['currency'] ?? 'USD');
                                    }
                                } else {
                                    $totalPayment = (float)$payment->money_returns;
                                }
                            @endphp
                            <div class="font-bold text-success" style="font-size: 11px;">
                                {{ number_format($totalPayment, 2, ',', '.') }} {{ $currency }}
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Reporte generado automáticamente por el sistema financiero.
    </div>

</body>
</html>
