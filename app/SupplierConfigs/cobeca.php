<?php

return [
    'productos' => function ($connection) {
        return [
            'Drogueria' => 3,
        ];
    },
    'facturas' => function ($connection) {
        return [
            'fechaInicio' => '2025-08-01T23:39:32.886Z',
            'fechaFin' => now()->toIso8601String(),
            'cliente' => 31373,
            'drogueria' => 3,
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [
            'url' => 'https://comparadores.drogueriascobeca.com/api/facturas/detalle?cod_factura=' . $facturaId,
        ];
    },
];