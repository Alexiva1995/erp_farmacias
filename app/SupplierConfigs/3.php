<?php

return [
    'productos' => function ($connection) {
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina//articulos?co_cli=FAR00818',
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/factura/resumen?co_cli=FAR00818',
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        // No se usa ya que viene anidado en facturas
        return [];
    },
];
