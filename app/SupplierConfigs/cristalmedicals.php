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
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/v1/facturas?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        // Viene anidado en la respuesta de facturas
        return [];
    },
];