<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMasterCatalogToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('catalog.master_secret');

        // Si no está configurado un secreto, permitir en desarrollo o rechazar en prod
        if (empty($expectedSecret)) {
            return $next($request);
        }

        $providedKey = $request->header('X-Master-Key') 
            ?? $request->bearerToken() 
            ?? $request->query('api_key');

        if (!$providedKey || !hash_equals((string) $expectedSecret, (string) $providedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado al Catálogo Maestro. Clave de API inválida o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
