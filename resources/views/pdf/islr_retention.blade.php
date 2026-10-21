<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de Retención de ISLR - {{ $comprobante_number }}</title>
    <style>
        @page {
            margin: 0.8cm 1cm;
            size: letter landscape;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 8pt;
            line-height: 1.15;
            color: #111;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-cell {
            width: 140px;
        }
        .logo-img {
            max-width: 130px;
            max-height: 48px;
        }
        .title-cell {
            text-align: center;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* BARRA SUPERIOR DE METADATOS CON DOBLE LÍNEA AZUL Y AMARILLA */
        .meta-bar-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #111;
            border-left: 1px solid #111;
            border-right: 1px solid #111;
            border-bottom: 2.5px solid #0d47a1; /* Barra azul distintiva */
            margin-bottom: 0px;
        }
        .meta-bar-subline {
            height: 2.5px;
            background-color: #fbc02d; /* Barra amarilla distintiva */
            margin-bottom: 8px;
        }
        .meta-bar-table td {
            padding: 4px 6px;
            font-size: 8pt;
            border-right: 1px solid #111;
        }
        .meta-bar-table td:last-child {
            border-right: none;
        }

        /* RECUADROS DE AGENTE Y SUJETO RETENIDO */
        .parties-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border: 1px solid #111;
        }
        .parties-table td {
            padding: 5px 8px;
            vertical-align: top;
            border-bottom: 1px solid #111;
        }
        .field-label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #222;
            display: block;
            margin-bottom: 1px;
        }
        .field-value {
            font-size: 8pt;
            font-weight: normal;
        }

        /* TABLA DE OPERACIONES / DETALLE FISCAL */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            border: 1px solid #111;
        }
        .data-table th, .data-table td {
            border: 1px solid #111;
            padding: 5px 4px;
            font-size: 7.2pt;
        }
        .data-table th {
            background-color: #fafafa;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* SECCIÓN DE FIRMAS INFERIOR CON 3 LÍNEAS AZULES */
        .signatures-wrapper {
            margin-top: 40px;
            width: 100%;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-col {
            width: 30%;
            text-align: center;
            vertical-align: bottom;
        }
        .sig-spacer {
            width: 5%;
        }
        .sig-blue-line {
            border-top: 2.5px solid #0d47a1;
            width: 100%;
            margin: 0 auto 3px auto;
        }
        .sig-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #111;
        }
        .sig-value-top {
            font-size: 7.5pt;
            font-weight: bold;
            margin-bottom: 6px;
            min-height: 18px;
            color: #222;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                @if(!empty($global_logo_base64))
                    <img src="{{ $global_logo_base64 }}" class="logo-img">
                @elseif(!empty($global_logo_path) && file_exists($global_logo_path))
                    <img src="{{ $global_logo_path }}" class="logo-img">
                @endif
            </td>
            <td class="title-cell">
                <div class="doc-title">COMPROBANTE DE RETENCION DE IMPUESTO SOBRE LA RENTA</div>
            </td>
        </tr>
    </table>

    <!-- BARRA SUPERIOR DE METADATOS -->
    <table class="meta-bar-table">
        <tr>
            <td style="width: 35%;">
                <span style="font-weight: bold;">NRO COMPROBANTE:</span>
                <span style="font-weight: bold; font-size: 9pt; margin-left: 15px;">{{ $comprobante_number }}</span>
            </td>
            <td style="width: 32%;">
                <span style="font-weight: bold;">FECHA EMISION:</span>
                <span style="margin-left: 15px;">{{ $emission_date }}</span>
            </td>
            <td style="width: 33%;">
                <span style="font-weight: bold;">PERIODO FISCAL:</span>
                <span style="margin-left: 10px;">AÑO <strong>{{ $period_year }}</strong> &nbsp;&nbsp;&nbsp; MES: <strong>{{ $period_month }}</strong></span>
            </td>
        </tr>
    </table>
    <div class="meta-bar-subline"></div>

    <!-- DATOS DE AGENTE Y BENEFICIARIO -->
    <table class="parties-table">
        <tr>
            <td style="width: 55%;">
                <span class="field-label">NOMBRE O RAZÓN SOCIAL DEL AGENTE DE RETENCIÓN</span>
                <span class="field-value" style="font-weight: bold;">{{ $agent['name'] }}</span>
            </td>
            <td style="width: 45%;">
                <span class="field-label">REGISTRO DE INFORMACION FISCAL DEL AGENTE DE RETENCION</span>
                <span class="field-value" style="font-weight: bold;">{{ $agent['rif'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="field-label">DIRECCIÓN FISCAL DEL AGENTE DE RETENCIÓN</span>
                <span class="field-value">{{ $agent['address'] }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="field-label">NOMBRE O RAZÓN SOCIAL DEL SUJETO RETENIDO</span>
                <span class="field-value" style="font-weight: bold;">{{ $subject['name'] }}</span>
            </td>
            <td>
                <span class="field-label">REGISTRO DE INFORMACION FISCAL DEL SUJETO RETENIDO</span>
                <span class="field-value" style="font-weight: bold;">{{ $subject['rif'] }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="field-label">DIRECCIÓN FISCAL DEL SUJETO RETENIDO</span>
                <span class="field-value">{{ $subject['address'] }}</span>
            </td>
        </tr>
    </table>

    <!-- TABLA DE OPERACIONES Y RETENCIÓN -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">Oper.<br>No.</th>
                <th style="width: 11%;">Fecha Doc.<br>Relacionado</th>
                <th style="width: 14%;">Número Doc.<br>Relacionado</th>
                <th style="width: 13%;">Número de<br>Control</th>
                <th style="width: 19%;">Concepto<br>Descripción</th>
                <th style="width: 13%;">Monto<br>Base</th>
                <th style="width: 8%;">Sustraendo</th>
                <th style="width: 7%;">%<br>Retención</th>
                <th style="width: 10%;">Monto<br>Retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($operations as $op)
            <tr>
                <td class="text-center">{{ $op['oper_no'] }}</td>
                <td class="text-center">{{ $op['doc_date'] }}</td>
                <td class="text-center">{{ $op['doc_number'] }}</td>
                <td class="text-center">{{ $op['control_number'] }}</td>
                <td class="text-left" style="text-transform: uppercase;">{{ $op['concept_description'] }}</td>
                <td class="text-right">{{ number_format($op['taxable_base'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($op['subtrahend'], 2, ',', '.') }}</td>
                <td class="text-center">{{ number_format($op['retention_percentage'], 2, ',', '.') }}</td>
                <td class="text-right" style="font-weight: bold;">{{ number_format($op['withheld_amount'], 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        @if(count($operations) > 1)
        <tfoot>
            <tr style="background-color: #fafafa; font-weight: bold;">
                <td colspan="5" class="text-right">TOTALES:</td>
                <td class="text-right">{{ number_format($totals['taxable_base'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['subtrahend'], 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totals['withheld_amount'], 2, ',', '.') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <!-- SECCIÓN DE FIRMAS (3 COLUMNAS CON LÍNEA AZUL) -->
    <div class="signatures-wrapper">
        <table class="signatures-table">
            <tr>
                <!-- Columna 1: Agente de Retención -->
                <td class="sig-col">
                    <div class="sig-value-top" style="height: 48px;">
                        @if(!empty($global_signature_stamp_base64))
                            <img src="{{ $global_signature_stamp_base64 }}" style="max-height: 46px; max-width: 160px;">
                        @elseif(!empty($global_signature_stamp_path) && file_exists($global_signature_stamp_path))
                            <img src="{{ $global_signature_stamp_path }}" style="max-height: 46px; max-width: 160px;">
                        @endif
                    </div>
                    <div class="sig-blue-line"></div>
                    <div class="sig-title">AGENTE DE RETENCION</div>
                </td>

                <td class="sig-spacer"></td>

                <!-- Columna 2: Fecha de Entrega -->
                <td class="sig-col">
                    <div class="sig-value-top" style="height: 48px; line-height: 52px;">
                        {{ $emission_date }}
                    </div>
                    <div class="sig-blue-line"></div>
                    <div class="sig-title">FECHA DE ENTREGA</div>
                </td>

                <td class="sig-spacer"></td>

                <!-- Columna 3: Sujeto Retenido -->
                <td class="sig-col">
                    <div class="sig-value-top" style="height: 48px; line-height: 52px; font-size: 7pt; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                        {{ $subject['name'] }}
                    </div>
                    <div class="sig-blue-line"></div>
                    <div class="sig-title">SUJETO RETENIDO</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
