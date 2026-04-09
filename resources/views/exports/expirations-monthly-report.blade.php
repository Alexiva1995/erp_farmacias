<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Acta de Desincorporación - {{ $month }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #333; line-height: 1.4; }
        .header { margin-bottom: 30px; border-bottom: 2px solid #0d47a1; padding-bottom: 15px; }
        .logo-container { float: right; width: 140px; text-align: right; }
        .logo { width: 140px; height: auto; }
        .company-info { float: left; width: 350px; }
        .company-name { color: #0d47a1; margin: 0; font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .company-rif { font-size: 12px; font-weight: bold; margin-top: 5px; }
        .clearfix { clear: both; }
        
        .document-title { text-align: center; margin: 20px 0; background: #f8f9fa; padding: 10px; border: 1px solid #ddd; }
        .document-title h2 { margin: 0; font-size: 14px; text-transform: uppercase; color: #333; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; color: #0d47a1; text-transform: uppercase; font-size: 8px; }
        
        .summary { margin-bottom: 30px; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 5px solid #0d47a1; }
        .summary-item { margin-bottom: 5px; font-size: 11px; }
        .summary-label { font-weight: bold; width: 150px; display: inline-block; }
        
        .footer { margin-top: 40px; border-top: 1px solid #eee; padding-top: 10px; }
        .signature-grid { width: 100%; margin-top: 50px; }
        .signature-box { width: 33%; text-align: center; vertical-align: bottom; border: none; padding: 0 10px; }
        .signature-line { border-top: 1px solid #000; padding-top: 5px; font-weight: bold; font-size: 9px; }
        
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-uppercase { text-transform: uppercase; }
        .text-primary { color: #0d47a1; }
        .currency-symbol { font-size: 8px; color: #777; font-weight: normal; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="{{ public_path('images/logoDonative.png') }}" class="logo" alt="Logo">
        </div>
        <div class="company-info">
            <div class="company-name">FARMACIA BARRIO SUCRE 2024 C.A</div>
            <div class="company-rif">RIF: J-505406957</div>
            <div style="font-size: 9px; margin-top: 3px;">San Cristóbal - Edo. Táchira, Venezuela</div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="document-title">
        <h2>Acta de Desincorporación de Inventario por Vencimiento</h2>
    </div>

    <div class="summary">
        @php
            // Asegurar que el mes salga en español profesional (ej: Febrero 2026)
            $dateObj = \Carbon\Carbon::parse($month . '-01');
            $translatedMonth = $dateObj->locale('es')->translatedFormat('F Y');
        @endphp
        <div class="summary-item"><span class="summary-label">Periodo Aplicado:</span> <span class="text-uppercase">{{ $translatedMonth }}</span></div>
        <div class="summary-item"><span class="summary-label">Fecha de Emisión:</span> {{ now()->format('d/m/Y h:i A') }}</div>
        <div class="summary-item"><span class="summary-label">Tasa de Cambio (BS):</span> {{ number_format($bs_rate, 2) }} Bs.</div>
        <div class="summary-item">
            <span class="summary-label">Total a Desincorporar:</span> 
            <span class="text-primary text-bold" style="font-size: 13px;">
                {{ number_format($total_cost * $bs_rate, 2) }} Bs.
            </span>
            <span style="font-size: 10px; margin-left: 10px;">($ {{ number_format($total_cost, 2) }})</span>
        </div>
    </div>

    <p style="font-size: 9px; margin-bottom: 15px;">
        Se procede a detallar los productos que han alcanzado su fecha de caducidad, perdiendo su utilidad terapéutica y comercial. Los mismos son retirados del inventario activo para su posterior disposición final según las normativas vigentes.
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">ID</th>
                <th>Descripción del Producto / Lote</th>
                <th style="width: 80px;">Laboratorio</th>
                <th style="width: 60px;">Vencimiento</th>
                <th style="width: 40px;" class="text-right">Cant.</th>
                <th style="width: 70px;" class="text-right">Costo Unit. (Bs)</th>
                <th style="width: 80px;" class="text-right">Costo Total (Bs)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                @php
                    $unit_cost_bs = $log->cost_per_unit * $bs_rate;
                    $total_cost_bs = $log->total_lost_value * $bs_rate;
                @endphp
                <tr>
                    <td>{{ $log->product->id ?? '—' }}</td>
                    <td>
                        <div class="text-bold text-uppercase">{{ $log->product->name ?? '—' }}</div>
                        <div style="font-size: 7px; color: #777;">Lote: {{ $log->lot_number }}</div>
                    </td>
                    <td class="text-uppercase" style="font-size: 7px;">{{ $log->product->laboratory->name ?? 'S/L' }}</td>
                    <td class="text-bold">{{ \Carbon\Carbon::parse($log->expiration_date)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ number_format($log->expired_quantity, 0) }}</td>
                    <td class="text-right">{{ number_format($unit_cost_bs, 2) }}</td>
                    <td class="text-right text-bold">{{ number_format($total_cost_bs, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #f8f9fa;">
                <th colspan="4" class="text-right">TOTALES ACUMULADOS</th>
                <th class="text-right">{{ number_format($total_quantity, 0) }}</th>
                <th class="text-right">---</th>
                <th class="text-right text-primary" style="font-size: 11px;">
                    {{ number_format($total_cost * $bs_rate, 2) }} <span class="currency-symbol">Bs.</span>
                </th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <table class="signature-grid">
            <tr>
                <td class="signature-box">
                    <div class="signature-line">Elaborado por (Firma / Sello)</div>
                    <div style="font-size: 7px;">Control de Inventario</div>
                </td>
                <td class="signature-box">
                    <div class="signature-line">Revisado por (Firma / Sello)</div>
                    <div style="font-size: 7px;">Farmacéutico Regente</div>
                </td>
                <td class="signature-box">
                    <div class="signature-line">Autorizado por (Firma / Sello)</div>
                    <div style="font-size: 7px;">Gerencia General</div>
                </td>
            </tr>
        </table>
        
        <div style="margin-top: 30px; text-align: center; font-size: 8px; color: #999;">
            Documento de control interno no válido para transacciones comerciales. Generado por el sistema ERP Álexiva.
        </div>
    </div>
</body>
</html>
