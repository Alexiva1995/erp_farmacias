<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Productos</title>
    <style>
        @page {
            margin: 1cm;
        }
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #2979FF;
            padding-bottom: 10px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #2979FF;
            text-transform: uppercase;
        }
        .meta {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border-bottom: 1px solid #eee;
            padding: 8px 5px;
            text-align: left;
            word-wrap: break-word;
        }
        th {
            background-color: #f8f9fa;
            color: #2979FF;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
        }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        
        /* Alternar colores de fila para mejor lectura */
        tr:nth-child(even) {
            background-color: #fafafa;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            font-size: 9px;
            color: #999;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Lista Maestra de Productos</div>
        <div class="meta">
            Generado el: {{ now()->format('d/m/Y h:i A') }} | 
            Total Productos: {{ count($products) }}
        </div>
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
                        <span style="color: #666; font-style: italic;">({{ $product->laboratory->name ?? 'N/A' }})</span>
                    </td>
                    <td class="text-right">{{ number_format($product->stock_calculado ?? 0, 0) }}</td>
                    <td class="text-right text-bold">${{ number_format($product->sale_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        ERP Farmacia - Página <script type="text/php">echo $PAGE_NUM . " de " . $PAGE_COUNT;</script>
    </div>
</body>
</html>
