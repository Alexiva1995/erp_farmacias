<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/articulos?co_cli=' . urlencode($co_cli) . '&page=1&perPage=1000',
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : 'FAR00818';
        return [
            'url' => 'https://apienterprise.cristmedicals.com/api/factura/FAR?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        // No se usa ya que viene anidado en facturas
        return [];
    },
];
