<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? trim($connection->username) : ($connection->structure['co_cli'] ?? '');
        if (str_starts_with(strtoupper($co_cli), 'FRA')) {
            $co_cli = 'FAR' . substr($co_cli, 3);
        }
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/articulos' . (!empty($co_cli) ? '?co_cli=' . urlencode($co_cli) : ''),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? trim($connection->username) : ($connection->structure['co_cli'] ?? '');
        if (str_starts_with(strtoupper($co_cli), 'FRA')) {
            $co_cli = 'FAR' . substr($co_cli, 3);
        }
        $desde = now()->subDays(30)->format('Y-m-d');
        $hasta = now()->format('Y-m-d');
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/facturas?fec_desde=' . $desde . '&fec_hasta=' . $hasta . (!empty($co_cli) ? '&co_cli=' . urlencode($co_cli) : ''),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];
