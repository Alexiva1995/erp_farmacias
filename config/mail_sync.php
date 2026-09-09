<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gmail IMAP Configuration
    |--------------------------------------------------------------------------
    |
    | Parámetros de conexión IMAP para Gmail mediante SSL.
    | Requiere el uso de una contraseña de aplicación de 16 dígitos de Google.
    |
    */
    'host' => env('GMAIL_SYNC_HOST', 'imap.gmail.com'),
    'port' => (int) env('GMAIL_SYNC_PORT', 993),
    'email' => env('GMAIL_SYNC_EMAIL', ''),
    'password' => env('GMAIL_SYNC_PASSWORD', ''),
    'folder' => env('GMAIL_SYNC_FOLDER', 'INBOX'),
    'allowed_extensions' => ['xlsx', 'xls', 'csv', 'txt'],
    'timeout' => 30,
];