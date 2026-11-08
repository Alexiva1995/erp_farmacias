<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SwitchToBranchContext
{
    /**
     * Permite conmutar la conexión de base de datos activa a otra sucursal del mismo grupo empresarial
     * si el usuario autenticado tiene permisos de Administrador corporativo.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $targetBranchId = $request->header('X-Branch-Context');

        if (empty($targetBranchId) || !function_exists('tenancy') || !tenancy()->initialized) {
            return $next($request);
        }

        $currentTenant = tenancy()->tenant;
        if (!$currentTenant || $currentTenant->id === $targetBranchId) {
            return $next($request);
        }

        // 1. Resolver usuario autenticado en la conexión actual
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user() ?? $request->user();
        if (!$user) {
            return $next($request);
        }

        // 2. Pre-cargar el usuario en memoria para conservarlo durante el cambio de conexión
        if (method_exists($user, 'loadMissing')) {
            $user->loadMissing('role');
        }
        Auth::guard('sanctum')->setUser($user);
        Auth::guard('web')->setUser($user);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // 3. Validar permisos: debe ser rol 1 (Admin) o tener habilitada la sede
        $isGlobalAdmin = (int) $user->role_id === 1 || (bool) $user->is_corporate_admin;
        $allowedBranches = is_array($user->allowed_branch_ids) ? $user->allowed_branch_ids : [];

        if (!$isGlobalAdmin && !in_array($targetBranchId, $allowedBranches, true)) {
            return response()->json([
                'message' => 'No tienes autorización para acceder a esta sucursal.',
            ], 403);
        }

        // 4. Validar pertenencia al mismo grupo empresarial (seguridad multi-tenant estricta)
        $currentRootId = $currentTenant->parent_tenant_id ?: $currentTenant->id;

        $targetTenant = Tenant::find($targetBranchId);
        if (!$targetTenant) {
            return response()->json([
                'message' => "La sucursal solicitada '{$targetBranchId}' no existe.",
            ], 404);
        }

        $targetRootId = $targetTenant->parent_tenant_id ?: $targetTenant->id;
        if ($currentRootId !== $targetRootId) {
            Log::warning("[SwitchToBranchContext] Intento no autorizado de cruzar grupo empresarial de {$currentTenant->id} a {$targetBranchId}");
            return response()->json([
                'message' => 'La sucursal solicitada no pertenece a tu grupo empresarial.',
            ], 403);
        }

        // 5. Configurar conexión dinámica a la base de datos de la sucursal solicitada
        $previousConnection = DB::getDefaultConnection();
        $targetDbName = $targetTenant->database()->getName();
        $branchConnKey = 'branch_ctx_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $targetBranchId);

        $baseConfig = config("database.connections.{$previousConnection}") ?? config('database.connections.mysql');
        $branchConfig = $baseConfig;
        $branchConfig['database'] = $targetDbName;

        config(["database.connections.{$branchConnKey}" => $branchConfig]);
        DB::purge($branchConnKey);

        try {
            DB::connection($branchConnKey)->getPdo();
        } catch (\Throwable $e) {
            Log::error("[SwitchToBranchContext] Error conectando a BD de sucursal {$targetDbName}: " . $e->getMessage());
            return response()->json([
                'message' => 'No se pudo conectar a la base de datos de la sucursal seleccionada.',
            ], 500);
        }

        // 6. Activar la conexión de la sucursal durante la ejecución de la petición
        DB::setDefaultConnection($branchConnKey);

        try {
            /** @var Response $response */
            $response = $next($request);
            $response->headers->set('X-Active-Branch', $targetBranchId);
            return $response;
        } finally {
            DB::setDefaultConnection($previousConnection);
        }
    }
}
