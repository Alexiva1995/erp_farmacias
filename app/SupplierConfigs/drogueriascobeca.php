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
        return [
            'url' => 'https://comparadores.drogueriascobeca.com/api/facturas/resumen',
            'method' => 'post',
            'payload' => [
                'fechaInicio' => now()->subDays(60)->toIso8601String(),
                'fechaFin' => now()->toIso8601String(),
                'cliente' => !empty($connection->username) ? (int) preg_replace('/\D/', '', $connection->username) : (int) env('MAFARTA_CLIENTE', 31373),
                'drogueria' => 3,
            ],
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [
            'url' => 'https://comparadores.drogueriascobeca.com/api/facturas/detalle?cod_factura=' . $facturaId,
            'method' => 'get',
        ];
    },
];