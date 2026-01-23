<?php

/**
 * Router para el servidor de desarrollo de PHP (php -S / php artisan serve).
 * Sirve archivos estáticos desde public/ o delega en public/index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
