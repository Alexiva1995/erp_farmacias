<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Carta de Renuncia - {{ $employee_name }}</title>
    <style>
        @page {
            margin: 2cm;
            size: A4;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            margin-top: 10px;
        }

        .logo {
            max-width: 400px;
            height: auto;
            margin-bottom: 40px;
        }


        .recipient-section {
            margin: 20px 0;
            line-height: 1.8;
        }

        .recipient-section strong {
            font-weight: bold;
        }

        .subject {
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0;
        }

        .body-text {
            margin: 25px 0;
            text-align: justify;
            line-height: 1.8;
            font-size: 12pt;
            text-indent: 40px;
        }

        .body-text strong {
            font-weight: bold;
        }

        .signature-section {
            margin-top: 40px;
            text-align: center;
        }

        .signature-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .signature-id {
            font-size: 12pt;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Header con Logo -->
    <div class="header">
        <img src="file://{{ base_path('public/images/logoDonative.png') }}" alt="Farmacia Barrio Sucre" class="logo">
    </div>

    <!-- Información del Destinatario -->
    <div class="recipient-section">
        <strong>DIRIGIDO A:</strong> FARMACIA BARRIO SUCRE 2024 C.A.<br>
        <strong>R.I.F:</strong> J-505406957<br>
        TÁCHIRA - LA FRÍA - BARRIO SUCRE - CALLE PRINCIPAL LOCAL 05<br>
        <div class="subject">ASUNTO: RENUNCIA</div>
    </div>

    <!-- Cuerpo de la Carta -->
    <div class="body-text">
        Me dirijo ante usted para informarle que, mediante el presente documento, yo <strong>{{ $employee_name }}</strong>, con cédula de identidad <strong>{{ $employee_identification }}</strong>, no podré seguir desempeñándome como {{ $employee_position }}, cargo que asumí desde el <strong>{{ $start_date_formatted }}</strong> hasta la fecha en esta empresa, por lo que tomó la decisión de terminar mi relación laboral actual y a renunciar de manera voluntaria e irrevocable a mi cargo, desde el día <strong>{{ $effective_date_formatted }}</strong>.
    </div>

    <!-- Espacio para firma -->
    <div style="height: 60px;"></div>

    <!-- Firma -->
    <div class="signature-section">
        <div class="signature-name">{{ $employee_name }}</div>
        <div class="signature-id">{{ $employee_identification }}</div>
    </div>
</body>

</html>
