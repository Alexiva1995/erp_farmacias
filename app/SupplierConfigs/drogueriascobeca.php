<?php

return [
    'productos' => function ($connection) {
        return [
            'url' => 'https://comparadores.drogueriascobeca.com/api/Articulos',
            'method' => 'post',
            'payload' => [
                'cod_drogueria' => 3,
            ],
        ];
    },
    'facturas' => function ($connection) {
        return [];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];