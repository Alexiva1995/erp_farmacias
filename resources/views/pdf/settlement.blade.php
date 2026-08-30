<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Finiquito de Liquidación - {{ $name }} {{ $last_name }}</title>
    <style>
        @page {
            margin: 1cm 1.5cm;
            size: A4;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1a1a1a;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 5px;
        }
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 5px;
        }
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #2c3e50;
        }
        .company-rif {
            font-size: 9pt;
            color: #7f8c8d;
        }
        .document-title {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 8px;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .info-section {
            width: 100%;
            margin-bottom: 15px;
            background: #f9f9f9;
            padding: 8px;
            border: 1px solid #eee;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2px 5px;
        }
        .section-header {
            background: #2c3e50;
            color: white;
            font-weight: bold;
            padding: 4px 8px;
            margin-top: 10px;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .data-table th, .data-table td {
            border: 1px solid #dee2e6;
            padding: 6px 10px;
            font-size: 9pt;
        }
        .data-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row {
            font-weight: bold;
            background: #f1f3f5;
        }
        .legal-text {
            margin-top: 15px;
            text-align: justify;
            font-size: 9.5pt;
            line-height: 1.5;
            padding: 0 5px;
        }
        .totals-summary {
            margin-top: 10px;
            padding: 8px;
            background: #fff;
            border: 2px solid #2c3e50;
        }
        .net-amount {
            font-size: 11pt;
            font-weight: bold;
            color: #2c3e50;
        }
        .signature-section {
            margin-top: 30px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 3px;
            font-weight: bold;
            font-size: 9pt;
        }
        small { font-size: 8pt; }
    </style>
</head>
<body>
    <div class="header">
        @if(file_exists(public_path('images/' . ($company_logo ?? 'logoDonative.png'))))
            <img src="{{ public_path('images/' . ($company_logo ?? 'logoDonative.png')) }}" alt="Logo" class="logo">
        @endif
        <div class="company-name">{{ $company_name ?? 'FARMACIA BARRIO SUCRE 2024 C.A.' }}</div>
        <div class="company-rif">R.I.F: {{ $company_rif ?? 'J-505406957' }}</div>
        <div class="document-title">FINIQUITO DE PRESTACIONES SOCIALES Y BENEFICIOS LABORALES</div>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td style="width: 55%;"><strong>TRABAJADOR:</strong> {{ $name }} {{ $last_name }}</td>
                <td><strong>CÉDULA:</strong> {{ $formatted_identification }}</td>
            </tr>
            <tr>
                <td><strong>CARGO:</strong> {{ $position }}</td>
                <td><strong>FECHA INGRESO:</strong> {{ $starting_date }}</td>
            </tr>
            <tr>
                <td><strong>FECHA EGRESO:</strong> {{ $resignation_date }}</td>
                <td><strong>ANTIGÜEDAD:</strong> {{ $detailed_seniority }}</td>
            </tr>
            <tr>
                <td><strong>SALARIO BASE RECIBIDO:</strong> Bs. {{ number_format((float)($base_salary ?? 0), 2, ',', '.') }}</td>
                <td><strong>SALARIO INTEGRAL:</strong> Bs. {{ number_format((float)($integral_salary ?? 0), 2, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <div class="section-header">1. CONCEPTOS DEVENGADOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>DESCRIPCIÓN DEL CONCEPTO</th>
                <th class="text-center" style="width: 80px;">DÍAS</th>
                <th class="text-right" style="width: 120px;">MONTO (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Prestaciones Sociales (Garantía de Prestaciones)</td>
                <td class="text-center">{{ (float)($social_benefits_days ?? 0) }}</td>
                <td class="text-right">{{ number_format((float)($social_benefits_amount ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Vacaciones Fraccionadas Legales</td>
                <td class="text-center">{{ (float)($vacation_voucher_days ?? 0) }}</td>
                <td class="text-right">{{ number_format((float)($vacation_voucher_amount ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Bono Vacacional Fraccionado conforme a Ley</td>
                <td class="text-center">{{ (float)($vacation_bonus_voucher_days ?? 0) }}</td>
                <td class="text-right">{{ number_format((float)($vacation_bonus_voucher_amount ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Utilidades Fraccionadas (Bonificación de Fin de Año)</td>
                <td class="text-center">{{ (float)($earnings_voucher_days ?? 0) }}</td>
                <td class="text-right">{{ number_format((float)($earnings_voucher_amount ?? 0), 2, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTAL BRUTO DEVENGADO:</td>
                <td class="text-right">Bs. {{ number_format((float)($total_settlement_amount ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-header">2. DEDUCCIONES Y ANTICIPOS RECIBIDOS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>CONCEPTO DE DEDUCCIÓN / RETENCIÓN</th>
                <th class="text-right" style="width: 120px;">MONTO (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @if((float)($vacation_voucher_deduction ?? 0) > 0)
            <tr>
                <td>Anticipo de Vacaciones pagadas durante el periodo</td>
                <td class="text-right">{{ number_format((float)($vacation_voucher_deduction ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)($vacation_bonus_voucher_deduction ?? 0) > 0)
            <tr>
                <td>Anticipo de Bono Vacacional pagado durante el periodo</td>
                <td class="text-right">{{ number_format((float)($vacation_bonus_voucher_deduction ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)($earnings_voucher_deduction ?? 0) > 0)
            <tr>
                <td>Anticipo de Utilidades pagado durante el periodo</td>
                <td class="text-right">{{ number_format((float)($earnings_voucher_deduction ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endif
            @if(isset($additional_deductions_bs) && (float)$additional_deductions_bs > 0)
            <tr>
                <td>Deducciones Adicionales (Préstamos/Otros conceptos)</td>
                <td class="text-right">{{ number_format((float)$additional_deductions_bs, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="text-right">TOTAL DEDUCCIONES:</td>
                <td class="text-right">Bs. {{ number_format((float)($total_deductions ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals-summary text-right">
        <span class="net-amount">MONTO NETO A LIQUIDAR: Bs. {{ number_format((float)($total_settlement_amount ?? 0) - (float)($total_deductions ?? 0), 2, ',', '.') }}</span>
    </div>

    <div class="legal-text">
        Yo, <strong>{{ $name }} {{ $last_name }}</strong>, arriba identificado, por el presente instrumento declaro bajo fe de juramento que he recibido a mi entera satisfacción de la empresa <strong>{{ $company_name ?? 'FARMACIA BARRIO SUCRE 2024 C.A.' }}</strong>, la cantidad neta indicada en el presente balance. Dicho monto corresponde a la liquidación definitiva de mis prestaciones sociales, vacaciones, utilidades y demás conceptos derivados de la relación laboral que hoy culmina.
        <br><br>
        En virtud de lo anterior, manifiesto mi conformidad absoluta con los cálculos aquí presentados, reconociendo que los mismos cumplen con los extremos legales previstos en la Ley Orgánica del Trabajo, los Trabajadores y las Trabajadoras (LOTTT). Con la recepción de la referida suma, otorgo el más amplio y eficaz finiquito de carácter liberatorio, declarando que la empresa no me adeuda suma alguna por concepto de salarios, bonificaciones o beneficios contractuales, quedando las partes libres de toda obligación recíproca.
    </div>

    <div class="signature-section">
        <table style="width: 100%;">
            <tr>
                <td class="signature-box">
                    <div class="signature-line">POR LA EMPRESA</div>
                    <small>{{ $company_name ?? 'FARMACIA BARRIO SUCRE 2024 C.A.' }}<br>R.I.F: {{ $company_rif ?? 'J-505406957' }}</small>
                </td>
                <td style="width: 10%;"></td>
                <td class="signature-box">
                    <div class="signature-line">EL TRABAJADOR</div>
                    <small>{{ $name }} {{ $last_name }}<br>C.I.: {{ $formatted_identification }}</small>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
