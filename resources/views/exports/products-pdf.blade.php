<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Productos</title>
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
        }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
    </style>
</head>
<body>
    <div style="margin-bottom: 20px;">
        <h2 style="color: #2979FF; margin: 0;">Lista de Productos</h2>
        <div style="font-size: 9px; color: #666;">Fecha: {{ now()->format('d/m/Y h:i A') }} | Registros: {{ count($products) }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 70%;">Producto (ID - Nombre - Laboratorio)</th>
                <th style="width: 15%;" class="text-right">Stock</th>
                <th style="width: 15%;" class="text-right">PVP</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr>
                    <td>
                        <span class="text-bold">{{ $product->id }}</span> - 
                        {{ $product->name }} 
                        <span>({{ $product->laboratory->name ?? 'N/A' }})</span>
                    </td>
                    <td class="text-right">{{ number_format($product->stock_calculado ?? 0, 0) }}</td>
                    <td class="text-right text-bold">${{ number_format($product->sale_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
