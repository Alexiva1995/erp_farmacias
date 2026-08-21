<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rol del Catálogo de Productos
    |--------------------------------------------------------------------------
    | Opciones:
    | - 'standalone': Funcionamiento independiente tradicional.
    | - 'master': Servidor central oficial que emite y homologa los IDs globales.
    | - 'slave': Instancia cliente/esclava que consulta y registra IDs en el Master.
    */
    'role' => env('CATALOG_ROLE', 'standalone'),

    /*
    |--------------------------------------------------------------------------
    | Configuración del Servidor Master (Para Farmacias Esclavas)
    |--------------------------------------------------------------------------
    */
    'master_url' => rtrim(env('MASTER_API_URL', 'https://principal.tovaerp.com/api/v1/master-catalog'), '/'),
    'master_key' => env('MASTER_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Clave Secreta del Master (Para validar peticiones de Esclavas en el Master)
    |--------------------------------------------------------------------------
    */
    'master_secret' => env('MASTER_CATALOG_SECRET', env('APP_KEY')),
];
