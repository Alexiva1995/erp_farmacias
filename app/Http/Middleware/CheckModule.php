<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModule
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $enabledModules = $this->getEnabledModules();

        if (!in_array(strtolower($module), $enabledModules)) {
            return response()->json([
                'error' => 'Module not enabled',
                'message' => "El módulo '{$module}' no está activo en esta instancia del ERP."
            ], 403);
        }

        return $next($request);
    }

    /**
     * Obtener los módulos activos configurados en el .env.
     */
    private function getEnabledModules(): array
    {
        $modulesString = env('ENABLED_MODULES', 'pharmacy,restaurant,lottery,reservation,sports_rental,minimarket');
        return array_unique(array_map('trim', explode(',', strtolower($modulesString))));
    }
}
