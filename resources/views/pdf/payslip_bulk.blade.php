<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nóminas 2025 - Consolidado</title>
    <style>
        @page { margin: 0.8cm 1cm; size: letter landscape; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica','Arial',sans-serif; font-size: 8pt; line-height: 1.2; color: #111; }

        .page-break {
            page-break-after: always;
        }

        /* CABECERA */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 120px; }
        .logo-img { width: 108px; }
        .company-cell { text-align: right; }
        .company-name  { font-size: 12pt; font-weight: bold; letter-spacing: 0.4px; }
        .company-sub   { font-size: 8.5pt; font-weight: bold; display: block; margin-top: 2px; }
        .company-extra { font-size: 7.5pt; color: #444; display: block; margin-top: 1px; }

        /* TÍTULO */
        .title-main {
            text-align: center; font-size: 11pt; font-weight: bold; text-transform: uppercase;
            border-top: 1px solid #111; border-bottom: 1px solid #111;
            padding: 4px 0; margin: 7px 0 5px;
        }

        /* META */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 7px; font-size: 7.5pt; }
        .meta-table td { padding: 0 3px; }

        /* BASE LEGAL */
        .legal-base { font-size: 6pt; text-align: justify; margin-bottom: 8px; line-height: 1.1; color: #555; }

        /* TABLA PRINCIPAL */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .data-table th, .data-table td {
            border: 1px solid #888; padding: 2.5px 3px; font-size: 6.5pt;
        }
        .data-table th { background-color: #e0e0e0; text-align: center; font-weight: bold; }

        .sec-sal    { background-color: #cce0f5; }
        .sec-nosal  { background-color: #ccf0cc; }
        .sec-ded    { background-color: #f5cccc; }

        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }

        .total-row td { background-color: #f0f0f0; font-weight: bold; font-size: 7pt; }
        .resumen-row td { background-color: #e8e8e8; font-weight: bold; font-size: 7pt; }
        .neto-cell { background-color: #c8e6c9; font-weight: bold; font-size: 7.5pt; text-align: right; }

        /* FIRMAS POR EMPLEADO */
        .signatures-section { margin-top: 18px; }
        .signatures-title { font-weight: bold; font-size: 8pt; text-transform: uppercase; border-bottom: 1px solid #333; padding-bottom: 3px; margin-bottom: 10px; }
        .sig-grid { width: 100%; border-collapse: collapse; }
        .sig-cell { width: 33%; text-align: center; vertical-align: bottom; padding: 0 6px 12px; }
        .sig-line { border-top: 1px solid #333; padding-top: 3px; font-size: 6.5pt; }
        .sig-name { font-weight: bold; font-size: 6.5pt; }
        .sig-sub  { font-size: 6pt; color: #666; margin-top: 1px; }
        .sig-space { height: 38px; }

        /* FIRMAS PATRONO */
        .patron-section { margin-top: 20px; width: 100%; }
        .patron-table { width: 100%; border-collapse: collapse; }
        .patron-box { width: 42%; text-align: center; vertical-align: bottom; }
        .patron-line { border-top: 1px solid #111; width: 88%; margin: 0 auto; padding-top: 3px; font-weight: bold; font-size: 7pt; text-transform: uppercase; }

        /* PIE */
        .footer { margin-top: 14px; font-size: 6pt; text-align: justify; line-height: 1.2; border-top: 0.5px solid #bbb; padding-top: 5px; color: #555; }
    </style>
</head>
<body>

@foreach($payrolls as $index => $payroll)
    <div class="{{ $loop->last ? '' : 'page-break' }}">
        {{-- ═══════════ CABECERA ═══════════ --}}
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if(file_exists(public_path('images/logoDonative.png')))
                        <img src="{{ public_path('images/logoDonative.png') }}" class="logo-img">
                    @endif
                </td>
                <td class="company-cell">
                    <span class="company-name">FARMACIA BARRIO SUCRE 2024, C.A.</span>
                    <span class="company-sub">R.I.F. Nº J-50540695-7</span>
                    <span class="company-extra">Calle Principal Local 05 (L3) Sector Barrio Sucre, La Fría, Táchira</span>
                    <span class="company-extra">Tipo de Nómina: Quincenal</span>
                </td>
            </tr>
        </table>

        <div class="title-main">NÓMINA DE PERSONAL - {{ $payroll['name'] }}</div>

        <table class="meta-table">
            <tr>
                <td><strong>Período:</strong> {{ $payroll['period'] }}</td>
                <td><strong>Fecha de Emisión:</strong> {{ \Carbon\Carbon::now()->format('d/m/Y') }}</td>
                <td style="text-align:right;">
                    <strong>Tasa BCV:</strong> {{ number_format($payroll['exchange_rate'], 2, ',', '.') }} Bs./$
                </td>
            </tr>
        </table>

        <div class="legal-base">
            (LOTTT Art. 104 – Salario mensual y percepciones de naturaleza salarial. Art. 105 – Asistencia social de salud
            conforme a la ley. Los conceptos no salariales no forman base de cálculo para prestaciones sociales, IVSS, RPE ni FAOV.)
        </div>

        {{-- ═══════════ TABLA ═══════════ --}}
        @php
            $items = $payroll['items'];
            $totBaseSal   = 0; $totSalPago  = 0; $totAlimento = 0;
            $totSalud     = 0; $totBono     = 0; $totDevengado = 0;
            $totIvss      = 0; $totRpe      = 0; $totFaov     = 0;
            $totDeduc     = 0; $totNeto     = 0;
            $fmt = fn($v) => number_format(abs((float)$v), 2, ',', '.');
        @endphp

        <table class="data-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3%">#</th>
                    <th rowspan="2" style="width:12%">Nombre del<br>Trabajador</th>
                    <th rowspan="2" style="width:8%">Cédula</th>
                    <th colspan="2" class="sec-sal">CONCEPTOS SALARIALES<br><span style="font-weight:normal;font-size:5.5pt;">(Art. 104 LOTTT)</span></th>
                    <th colspan="3" class="sec-nosal">CONCEPTOS NO SALARIALES<br><span style="font-weight:normal;font-size:5.5pt;">(Art. 105 LOTTT)</span></th>
                    <th rowspan="2" style="width:8%">TOTAL<br>DEVENGADO<br>(Bs.)</th>
                    <th colspan="3" class="sec-ded">DEDUCCIONES<br><span style="font-weight:normal;font-size:5.5pt;">(Base: Sal. Mensual)</span></th>
                    <th rowspan="2" style="width:7%">TOTAL<br>DEDUCC.<br>(Bs.)</th>
                    <th rowspan="2" style="width:8%">NETO A<br>COBRAR<br>(Bs.)</th>
                </tr>
                <tr>
                    <th class="sec-sal" style="width:8%">Sal. Mensual<br>(Bs.)</th>
                    <th class="sec-sal" style="width:7%">Sueldo<br>a Pagar<br>(Bs.)</th>
                    <th class="sec-nosal" style="width:7%">Bono de<br>Alimentación<br>(Bs.)</th>
                    <th class="sec-nosal" style="width:10%">Asistencia Social<br>de Salud<br>(Art. 105 LOTTT)<br>(Bs.)</th>
                    <th class="sec-nosal" style="width:10%">Bono Extraordinario<br>de Rendimiento<br>(Bs.)</th>
                    <th class="sec-ded" style="width:5%">IVSS<br>4%<br>(Bs.)</th>
                    <th class="sec-ded" style="width:6%">RPE-Paro<br>Forzoso<br>0.5% (Bs.)</th>
                    <th class="sec-ded" style="width:5%">FAOV<br>1%<br>(Bs.)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $i => $row)
                @php
                    $baseSal   = (float)($row['base_salary_voucher'] ?? 0);
                    $salPago   = (float)($row['salary_to_pay_voucher'] ?? 0);
                    $alimento  = (float)($row['food_voucher'] ?? 0);
                    $salud     = (float)($row['health_support_voucher'] ?? 0);
                    $bono      = (float)($row['performance_voucher'] ?? 0);
                    $ivss      = abs((float)($row['social_security_voucher'] ?? 0));
                    $rpe       = abs((float)($row['employment_voucher'] ?? 0));
                    $faov      = abs((float)($row['housing_property_benefits_voucher'] ?? 0));
                    $devengado = $salPago + $alimento + $salud + $bono;
                    $deduc     = $ivss + $rpe + $faov;
                    $neto      = $devengado - $deduc;
                    $totBaseSal += $baseSal; $totSalPago += $salPago;
                    $totAlimento += $alimento; $totSalud += $salud; $totBono += $bono;
                    $totDevengado += $devengado;
                    $totIvss += $ivss; $totRpe += $rpe; $totFaov += $faov;
                    $totDeduc += $deduc; $totNeto += $neto;
                @endphp
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ trim(($row['name'] ?? '') . ' ' . ($row['last_name'] ?? '')) }}</td>
                    <td class="text-center">V-{{ number_format((int)($row['identification'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ $fmt($baseSal) }}</td>
                    <td class="text-right">{{ $fmt($salPago) }}</td>
                    <td class="text-right">{{ $fmt($alimento) }}</td>
                    <td class="text-right">{{ $fmt($salud) }}</td>
                    <td class="text-right">{{ $fmt($bono) }}</td>
                    <td class="text-right bold">{{ $fmt($devengado) }}</td>
                    <td class="text-right">{{ $fmt($ivss) }}</td>
                    <td class="text-right">{{ $fmt($rpe) }}</td>
                    <td class="text-right">{{ $fmt($faov) }}</td>
                    <td class="text-right bold">{{ $fmt($deduc) }}</td>
                    <td class="text-right bold">{{ $fmt($neto) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTALES</td>
                    <td class="text-right">{{ number_format($totBaseSal, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totSalPago, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totAlimento, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totSalud, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totBono, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totDevengado, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totIvss, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totRpe, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totFaov, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totDeduc, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($totNeto, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="8" class="text-right resumen-row">
                        Total Devengado: <strong>{{ number_format($totDevengado, 2, ',', '.') }} Bs.</strong>
                        &nbsp;&nbsp; Total Deducciones: <strong>{{ number_format($totDeduc, 2, ',', '.') }} Bs.</strong>
                    </td>
                    <td colspan="6" class="neto-cell">
                        NETO A PAGAR: {{ number_format($totNeto, 2, ',', '.') }} Bs.
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- ═══════════ FIRMAS POR EMPLEADO ═══════════ --}}
        <div class="signatures-section">
            <div class="signatures-title">RECEPCIÓN DE PAGO — FIRMA DE TRABAJADORES</div>
            <table class="sig-grid">
                @foreach($items as $i => $row)
                @if($i % 3 === 0)<tr>@endif
                @php
                    $neto2 = ((float)($row['salary_to_pay_voucher']??0) + (float)($row['food_voucher']??0) + (float)($row['health_support_voucher']??0) + (float)($row['performance_voucher']??0))
                           - (abs((float)($row['social_security_voucher']??0)) + abs((float)($row['employment_voucher']??0)) + abs((float)($row['housing_property_benefits_voucher']??0)));
                @endphp
                <td class="sig-cell">
                    <div class="sig-space"></div>
                    <div class="sig-line">
                        <div class="sig-name">{{ trim(($row['name'] ?? '') . ' ' . ($row['last_name'] ?? '')) }}</div>
                        <div class="sig-sub">
                            V-{{ number_format((int)($row['identification']??0), 0, ',', '.') }}
                            &nbsp;|&nbsp; Neto: {{ number_format($neto2, 2, ',', '.') }} Bs.
                        </div>
                    </div>
                </td>
                @if(($i + 1) % 3 === 0 || $i + 1 === count($items))</tr>@endif
                @endforeach
            </table>
        </div>

        {{-- ═══════════ FIRMA PATRONO ═══════════ --}}
        <div class="patron-section">
            <table class="patron-table">
                <tr>
                    <td class="patron-box">
                        <div style="height:40px;"></div>
                        <div class="patron-line">Representante Legal / Patrono</div>
                        <div style="font-size:6.5pt; text-align:center; margin-top:2px;">R.I.F. J-50540695-7 | FARMACIA BARRIO SUCRE 2024, C.A.</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            Documento de nómina emitido conforme a la Ley Orgánica del Trabajo, los Trabajadores y las Trabajadoras (LOTTT), Art. 104 y 105.
            Los conceptos no salariales no forman base de cálculo para prestaciones sociales, IVSS, RPE ni FAOV.
            Tasa BCV aplicada: {{ number_format($payroll['exchange_rate'], 2, ',', '.') }} Bs/$.
        </div>
    </div>
@endforeach

</body>
</html>
