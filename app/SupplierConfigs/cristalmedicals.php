<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/articulos?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        $desde = now()->subDays(30)->format('Y-m-d');
        $hasta = now()->format('Y-m-d');
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/facturas?co_cli=' . urlencode($co_cli) . '&fec_desde=' . $desde . '&fec_hasta=' . $hasta,
            'method' => 'post',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];