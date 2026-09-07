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
            'url' => 'https://sic.drogueriascobeca.com/api/estadocuenta/consulta',
            'method' => 'post',
            'payload' => [
                'compania' => 3,
                'drogueria' => 3,
                'cliente' => !empty($connection->username) ? (int) preg_replace('/\D/', '', $connection->username) : (int) env('MAFARTA_CLIENTE', 31373),
                'tipo' => 1,
            ],
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        $numDoc = str_pad(ltrim((string) $facturaId, '0'), 10, '0', STR_PAD_LEFT);
        return [
            'url' => 'https://sic.drogueriascobeca.com/api/factura/' . $numDoc,
            'method' => 'get',
        ];
    },
];