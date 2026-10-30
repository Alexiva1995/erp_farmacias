<?php

return [
    'productos' => function ($connection) {
        $isEnterprise = str_contains($connection->host ?? '', 'apienterprise') || str_contains($connection->path ?? '', 'apienterprise') || str_contains($connection->path ?? '', '/api/v1');
        if ($isEnterprise) {
            $url = !empty($connection->path) && str_starts_with($connection->path, 'http') ? $connection->path : 'https://apienterprise.cristmedicals.com/api/v1/articulos';
            return [
                'url' => $url,
                'method' => 'get',
            ];
        }

        $co_cli = !empty($connection->username) ? $connection->username : env('CRISTMEDICALS_USERNAME', '');
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/articulos?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $isEnterprise = str_contains($connection->host ?? '', 'apienterprise') || str_contains($connection->invoice_path ?? '', 'apienterprise') || str_contains($connection->invoice_path ?? '', '/api/v1');
        if ($isEnterprise) {
            $url = !empty($connection->invoice_path) && str_starts_with($connection->invoice_path, 'http') ? $connection->invoice_path : 'https://apienterprise.cristmedicals.com/api/v1/facturas';
            return [
                'url' => $url,
                'method' => 'get',
            ];
        }

        $co_cli = !empty($connection->username) ? $connection->username : env('CRISTMEDICALS_USERNAME', '');
        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/factura/FAR?co_cli=' . urlencode($co_cli),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];