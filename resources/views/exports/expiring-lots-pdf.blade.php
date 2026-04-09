<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Vencimientos</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #eee;
            font-weight: bold;
            text-transform: uppercase;
        }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-primary { color: #2979FF; }
        .text-error { color: #ea5455; }
        .title-container { margin-bottom: 20px; border-bottom: 2px solid #2979FF; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="title-container">
        <h2 class="text-primary" style="margin: 0;">Reporte de Productos por Caducar</h2>
        <div style="font-size: 9px; color: #666;">
            Generado el: {{ now()->format('d/m/Y h:i A') }} | 
            Total encontrados: {{ count($lots) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">ID</th>
                <th style="width: 40%;">Producto / Sustancia</th>
                <th style="width: 15%;">Laboratorio</th>
                <th style="width: 12%;">No. Lote</th>
                <th style="width: 15%;">Vencimiento</th>
                <th style="width: 10%;" class="text-right">Stock</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lots as $lot)
                <tr>
                    <td class="text-bold">{{ $lot->product->id ?? '—' }}</td>
                    <td>
                        <div class="text-bold text-uppercase">{{ $lot->product->name ?? '—' }}</div>
                        <div style="font-size: 8px; color: #666;">{{ $lot->product->active_ingredient ?? 'S/A' }}</div>
                    </td>
                    <td class="text-uppercase">{{ $lot->product->laboratory->name ?? '—' }}</td>
                    <td class="text-bold">{{ $lot->lot_number }}</td>
                    <td class="text-error text-bold">{{ $lot->expiration_date->format('d/m/Y') }}</td>
                    <td class="text-right text-bold">{{ number_format($lot->quantity, 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
