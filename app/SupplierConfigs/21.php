<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : env('CRISTMEDICALS_USERNAME', '');
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/articulos?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? $connection->username : env('CRISTMEDICALS_USERNAME', '');
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/factura/FAR?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        // No se usa ya que viene anidado en facturas
        return [];
    },
];
