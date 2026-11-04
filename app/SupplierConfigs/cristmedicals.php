<?php

return [
    'productos' => function ($connection) {
        $co_cli = !empty($connection->username) ? trim($connection->username) : env('CRISTMEDICALS_USERNAME', '');
        if (empty($co_cli) && !empty($connection->structure['co_cli'])) {
            $co_cli = trim($connection->structure['co_cli']);
        }

        $isEnterprise = str_contains($connection->host ?? '', 'apienterprise') || str_contains($connection->path ?? '', 'apienterprise') || str_contains($connection->path ?? '', '/api/v1');
        if ($isEnterprise) {
            $url = !empty($connection->path) && str_starts_with($connection->path, 'http')
                ? $connection->path
                : 'https://apienterprise.cristmedicals.com/api/v1/articulos';

            if (!empty($co_cli) && !str_contains($url, 'co_cli=')) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'co_cli=' . urlencode($co_cli);
            }

            return [
                'url' => $url,
                'method' => 'get',
                'payload' => !empty($co_cli) ? ['co_cli' => $co_cli] : [],
            ];
        }

        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/articulos' . (!empty($co_cli) ? '?co_cli=' . urlencode($co_cli) : ''),
            'method' => 'get',
        ];
    },
    'facturas' => function ($connection) {
        $co_cli = !empty($connection->username) ? trim($connection->username) : env('CRISTMEDICALS_USERNAME', '');
        if (empty($co_cli) && !empty($connection->structure['co_cli'])) {
            $co_cli = trim($connection->structure['co_cli']);
        }

        $isEnterprise = str_contains($connection->host ?? '', 'apienterprise') || str_contains($connection->invoice_path ?? '', 'apienterprise') || str_contains($connection->invoice_path ?? '', '/api/v1');
        if ($isEnterprise) {
            $url = !empty($connection->invoice_path) && str_starts_with($connection->invoice_path, 'http')
                ? $connection->invoice_path
                : 'https://apienterprise.cristmedicals.com/api/v1/facturas';

            if (!empty($co_cli) && !str_contains($url, 'co_cli=')) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'co_cli=' . urlencode($co_cli);
            }

            return [
                'url' => $url,
                'method' => 'get',
                'payload' => !empty($co_cli) ? ['co_cli' => $co_cli] : [],
            ];
        }

        return [
            'url' => 'https://api.cristmedicals.cristmedicals.com/api/pagina/factura/FAR' . (!empty($co_cli) ? '?co_cli=' . urlencode($co_cli) : ''),
            'method' => 'get',
        ];
    },
    'factura_detalle' => function ($connection, $facturaId) {
        return [];
    },
];
