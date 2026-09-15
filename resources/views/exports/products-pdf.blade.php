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
    <div style="margin-bottom: 20px; border-bottom: 2px solid #2979FF; padding-bottom: 10px;">
        @if(!empty($global_logo_base64))
            <img src="{{ $global_logo_base64 }}" style="max-height: 40px; float: right;" alt="Logo">
        @elseif(!empty($global_logo_path) && file_exists($global_logo_path))
            <img src="{{ $global_logo_path }}" style="max-height: 40px; float: right;" alt="Logo">
        @endif
        <div style="font-size: 13px; font-weight: bold; color: #333;">{{ $global_company_name ?? 'FARMACIA' }}</div>
        @if(!empty($global_company_rif))
            <div style="font-size: 9px; color: #666;">RIF: {{ $global_company_rif }}</div>
        @endif
        <h2 style="color: #2979FF; margin: 4px 0 0 0; font-size: 13px;">Lista de Productos</h2>
        <div style="font-size: 9px; color: #666; margin-top: 2px;">Fecha: {{ now()->format('d/m/Y h:i A') }} | Registros: {{ count($products) }}</div>
        <div style="clear: both;"></div>
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
