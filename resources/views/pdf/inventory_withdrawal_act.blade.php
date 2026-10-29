<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Desincorporación de Inventario y Débito Fiscal IVA</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
            size: portrait;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            line-height: 1.35;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 13px;
            margin: 0;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 10px;
            margin: 3px 0 0 0;
            color: #475569;
            font-weight: normal;
        }
        .company-info {
            width: 100%;
            margin-bottom: 12px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 6px 8px;
        }
        .company-info td {
            font-size: 8.5px;
            padding: 2px 4px;
        }
        .legal-preamble {
            background-color: #f1f5f9;
            border-left: 3px solid #1e3a8a;
            padding: 6px 10px;
            font-size: 8px;
            margin-bottom: 12px;
            text-align: justify;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .table-items th, .table-items td {
            border: 0.5px solid #94a3b8;
            padding: 4px 5px;
            font-size: 8px;
        }
        .table-items th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .summary-box {
            width: 60%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .summary-box td {
            border: 0.5px solid #94a3b8;
            padding: 4px 8px;
            font-size: 8.5px;
        }
        .summary-box .title {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .summary-box .highlight {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 33.3%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-line {
            border-top: 1px solid #334155;
            margin-top: 45px;
            padding-top: 4px;
            font-weight: bold;
            font-size: 8.5px;
        }
        .signature-role {
            font-size: 7.5px;
            color: #64748b;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $monthName = mb_strtoupper(\Carbon\Carbon::create($book->year, $book->month, 1)->locale('es')->monthName, 'UTF-8');
        $periodTypeValue = is_object($book->period_type) ? $book->period_type->value : (string) $book->period_type;
        $periodRange = ($periodTypeValue === 'fortnightly')
            ? ($book->fortnight == 1 ? "1RA QUINCENA (01 AL 15 DE {$monthName} DE {$book->year})" : "2DA QUINCENA (16 AL " . \Carbon\Carbon::parse($book->end_date)->format('d') . " DE {$monthName} DE {$book->year})")
            : "MES DE {$monthName} DE {$book->year}";
        $withdrawnEntries = $book->entries->filter(fn($e) => (float) $e->withdrawals > 0);
    @endphp

    <div class="header">
        <h1>Acta Formal de Desincorporación de Inventario</h1>
        <h2>Soporte Tributario SENIAT (Art. 177 LISR / Art. 4 num. 3 y Art. 7 Ley de IVA)</h2>
    </div>

    <table class="company-info">
        <tr>
            <td style="width: 55%;"><span class="text-bold">Razón Social:</span> {{ mb_strtoupper($settings->app_name ?: 'FARMACIA', 'UTF-8') }}</td>
            <td style="width: 45%;"><span class="text-bold">R.I.F.:</span> {{ mb_strtoupper($settings->app_rif ?: $settings->rif ?: 'J-00000000-0', 'UTF-8') }}</td>
        </tr>
        <tr>
            <td><span class="text-bold">Dirección Fiscal:</span> {{ mb_strtoupper($settings->address ?: 'DIRECCIÓN PRINCIPAL', 'UTF-8') }}</td>
            <td><span class="text-bold">Condición:</span> {{ ($settings->special_taxpayer_status === 'activada' || $settings->enable_ce) ? 'SUJETO PASIVO ESPECIAL' : 'CONTRIBUYENTE ORDINARIO' }}</td>
        </tr>
        <tr>
            <td><span class="text-bold">Período Fiscal Afectado:</span> {{ $periodRange }}</td>
            <td><span class="text-bold">Fecha de Emisión del Acta:</span> {{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="legal-preamble">
        <strong>FUNDAMENTO LEGAL Y JUSTIFICACIÓN FISCAL:</strong><br>
        En la ciudad correspondiente, comparecen los abajo firmantes a fin de hacer constar la desincorporación física y contable de los renglones de inventario descritos en el presente documento, correspondiente a mercancías deterioradas, vencidas, averiadas o con merma/diferencia justificada durante el período gravable citado, de conformidad con lo establecido en el <strong>Artículo 177 de la Ley de Impuesto Sobre la Renta (LISR)</strong> y <strong>Artículo 91 de su Reglamento</strong>. Asimismo, para dar estricto cumplimiento a los <strong>Artículos 4 (numeral 3) y 7 de la Ley del Impuesto al Valor Agregado (LIVA)</strong>, se determina el Débito Fiscal por retiro de bienes muebles corporales que no cuenten con exención legal, el cual se transfiere a la declaración periódica de IVA (Forma 30) del contribuyente.
    </div>

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 5%;">Nº</th>
                <th style="width: 10%;">CÓDIGO</th>
                <th style="width: 35%;">DESCRIPCIÓN DEL PRODUCTO / MEDICAMENTO</th>
                <th style="width: 10%;">UNID. RETIRADAS</th>
                <th style="width: 12%;">COSTO UNIT. (BS)</th>
                <th style="width: 13%;">COSTO TOTAL (BS)</th>
                <th style="width: 5%;">IVA</th>
                <th style="width: 10%;">DÉBITO FISCAL (BS)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($withdrawnEntries as $entry)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-center">{{ $entry->product_code }}</td>
                    <td>{{ $entry->product_description }}</td>
                    <td class="text-right text-bold">{{ number_format($entry->withdrawals, 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format($entry->unit_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-right text-bold">{{ number_format($entry->withdrawals_cost_bs, 2, '.', ',') }}</td>
                    <td class="text-center">{{ $entry->iva_rate > 0 ? '16%' : 'Exento' }}</td>
                    <td class="text-right">{{ number_format($entry->withdrawal_vat_bs, 2, '.', ',') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #64748b;">
                        No se registraron retiros ni desincorporaciones de inventario en este período fiscal.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-box">
        <tr>
            <td class="title">Total Unidades Desincorporadas:</td>
            <td class="text-right text-bold">{{ number_format($book->total_withdrawal_units, 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td class="title">Monto Desincorporado Exento (Art. 18 Ley de IVA - Medicamentos):</td>
            <td class="text-right">{{ number_format($book->total_withdrawal_exempt_bs, 2, '.', ',') }} Bs.</td>
        </tr>
        <tr>
            <td class="title">Base Imponible Gravada Desincorporada (Alícuota 16%):</td>
            <td class="text-right">{{ number_format($book->total_withdrawal_taxable_bs, 2, '.', ',') }} Bs.</td>
        </tr>
        <tr class="highlight">
            <td>TOTAL DÉBITO FISCAL IVA A ENTERAR AL SENIAT (Forma 30):</td>
            <td class="text-right">{{ number_format($book->total_withdrawal_vat_bs, 2, '.', ',') }} Bs.</td>
        </tr>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">REPRESENTANTE LEGAL</div>
                <div class="signature-role">Firma y Cédula de Identidad</div>
            </td>
            <td>
                <div class="signature-line">FARMACÉUTICO REGENTE</div>
                <div class="signature-role">Firma, C.I. y Nro. M.P.P.S.</div>
            </td>
            <td>
                <div class="signature-line">CONTADOR PÚBLICO COLEGIADO</div>
                <div class="signature-role">Firma, C.I. y Nro. C.P.C.</div>
            </td>
        </tr>
    </table>
</body>
</html>
