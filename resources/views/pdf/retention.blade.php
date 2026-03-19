<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de Retención de IVA - {{ $comprobante['number'] }}</title>
    <style>
        @page {
            margin: 0.8cm 1cm;
            size: letter landscape;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8.5pt;
            line-height: 1.15;
            color: #111;
        }
        /* CABECERA AL ESTILO NOMINA */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .header-table td { vertical-align: top; }
        .logo-cell { width: 120px; }
        .logo-img { width: 108px; }
        .company-cell { text-align: right; }
        .company-name  { font-size: 12.5pt; font-weight: bold; letter-spacing: 0.4px; }
        .company-sub   { font-size: 8.5pt; font-weight: bold; display: block; margin-top: 2px; }
        .company-extra { font-size: 7.5pt; color: #444; display: block; margin-top: 1px; }

        .title-main {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            margin: 10px 0 6px 0;
            text-transform: uppercase;
            border-top: 1.2px solid #111;
            border-bottom: 1.2px solid #111;
            padding: 5px 0;
        }
        .legal-base {
            font-size: 6.8pt;
            text-align: justify;
            margin-bottom: 12px;
            line-height: 1.1;
        }
        .meta-info-table {
            width: 100%;
            margin-bottom: 12px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .info-grid td {
            vertical-align: top;
            padding: 6px;
            border: 1px solid #000;
        }
        .label { font-weight: bold; font-size: 7pt; text-transform: uppercase; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 6.8pt;
        }
        .data-table th {
            background-color: #f5f5f5;
            text-align: center;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .signature-section {
            margin-top: 35px;
            width: 100%;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-box {
            width: 45%;
            text-align: center;
            vertical-align: bottom;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 90%;
            margin: 0 auto;
            padding-top: 4px;
            font-weight: bold;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 20px;
            font-size: 7pt;
            text-align: justify;
            line-height: 1.2;
            border-top: 0.5px solid #ccc;
            padding-top: 8px;
        }
    </style>
</head>
<body>
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
                <span class="company-extra">Agente de Retención de I.V.A.</span>
            </td>
        </tr>
    </table>

    <div class="title-main">COMPROBANTE DE RETENCIÓN DEL IMPUESTO AL VALOR AGREGADO</div>

    <div class="legal-base" style="color: #555;">
        (Ley IVA - Art. 11. Gaceta Oficial 6.152 Extraordinario. "La Administración Tributaria podrá designar como responsables del pago del impuesto, en calidad de agentes de retención, a quienes por sus funciones públicas o por razón de sus actividades privadas intervengan en operaciones gravadas con el impuesto establecido en este Decreto con Rango, Valor y Fuerza de Ley")
    </div>

    <table class="meta-info-table">
        <tr>
            <td style="width: 50%; font-size: 8.5pt;">
                <strong>Ciudad:</strong> LA FRIA<br>
                <strong>Fecha de Emisión:</strong> {{ $date_now }}
            </td>
            <td style="width: 50%; text-align: right; font-size: 8.5pt;">
                <strong>Nº Comprobante:</strong> <span style="color: #d32f2f; font-weight: bold;">{{ $comprobante['number'] }}</span><br>
                <strong>Periodo Fiscal:</strong> {{ $comprobante['period'] }}
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td style="width: 50%;">
                <div class="label" style="margin-bottom: 4px; border-bottom: 0.5px solid #000; padding-bottom: 2px;">DATOS DEL AGENTE DE RETENCIÓN:</div>
                <span class="label">Nombre o Razón Social:</span> FARMACIA BARRIO SUCRE 2024, C.A.<br>
                <span class="label">Nº R.I.F.:</span> {{ $company['rif'] }} &nbsp;&nbsp; <span class="label">Nº N.I.T.:</span> <br>
                <span class="label">Dirección:</span> CALLE PRINCIPAL LOCAL 05 (L3) SECTOR BARRIO SUCRE LA FRIA TACHIRA
            </td>
            <td style="width: 50%;">
                <div class="label" style="margin-bottom: 4px; border-bottom: 0.5px solid #000; padding-bottom: 2px;">DATOS DEL BENEFICIARIO:</div>
                <span class="label">Nombre o Razón Social:</span> {{ $supplier['name'] }}<br>
                <span class="label">Nº R.I.F.:</span> {{ $supplier['rif'] }} &nbsp;&nbsp; <span class="label">Nº N.I.T.:</span> N/A<br>
                <span class="label">Dirección:</span> {{ $supplier['address'] }}
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">Nº</th>
                <th rowspan="2">Fecha Doc.</th>
                <th rowspan="2">Nº Factura</th>
                <th rowspan="2">Nº Control</th>
                <th rowspan="2" style="width: 45px;">Nº Nota Débito</th>
                <th rowspan="2" style="width: 45px;">Nº Nota Crédito</th>
                <th rowspan="2">Tipo de Transacción</th>
                <th rowspan="2">Nº Fact. Afectada</th>
                <th rowspan="2">Total Factura o Nota Débito</th>
                <th rowspan="2">Sin derecho a Crédito</th>
                <th colspan="4">COMPRAS INTERNAS o IMPORTACIONES</th>
            </tr>
            <tr>
                <th>Base Imponible</th>
                <th>% Alíc.</th>
                <th>Impuesto Causado</th>
                <th>Impuesto Retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $index => $inv)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $inv['date'] }}</td>
                <td class="text-center">{{ $inv['number'] }}</td>
                <td class="text-center">{{ $inv['control'] }}</td>
                <td class="text-center"></td>
                <td class="text-center"></td>
                <td class="text-center">01-Registro</td>
                <td class="text-center"></td>
                <td class="text-right">{{ number_format($inv['total'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($inv['exempt_amount'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($inv['taxable_base'], 2, ',', '.') }}</td>
                <td class="text-center">16.00</td>
                <td class="text-right">{{ number_format($inv['tax_amount'], 2, ',', '.') }}</td>
                <td class="text-right"><strong>{{ number_format($inv['tax_withheld'], 2, ',', '.') }}</strong></td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f5f5f5; font-weight: bold;">
                <td colspan="8" class="text-right">TOTALES:</td>
                <td class="text-right">{{ number_format($totals['total_amount'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['exempt_amount'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['taxable_base'], 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totals['tax_amount'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['tax_withheld'], 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td class="signature-box">
                    <div style="height: 50px;"></div>
                    <div class="signature-line">Firma Y Sello Agente De Retención</div>
                    <div style="font-weight: bold; margin-top: 2px; font-size: 7.5pt;">RIF: J505406957</div>
                </td>
                <td style="width: 10%;"></td>
                <td class="signature-box">
                    <div style="height: 50px;"></div>
                    <div class="signature-line">Recibido por el Proveedor</div>
                    <div style="margin-top: 2px; font-size: 6.5pt; color: #444;">Nombre / CI / Firma / Fecha</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Este comprobante se emite según lo establecido en el Artículo Nº 16 de la Providencia Administrativa Nº SNAT/2015/000049 de fecha 02 de julio de 2015, Publicada Gaceta Oficial Nº 43.171 de fecha 16 Julio de 2025
    </div>
</body>
</html>
</body>
</html>
