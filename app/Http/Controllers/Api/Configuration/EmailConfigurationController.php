<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\TestEmailConnectionRequest;
use App\Http\Requests\Configuration\UpdateEmailConfigurationRequest;
use App\Http\Resources\Configuration\EmailConfigurationResource;
use App\Services\Configuration\EmailConfigurationService;
use Illuminate\Http\JsonResponse;

class EmailConfigurationController extends Controller
{
    protected EmailConfigurationService $emailService;

    public function __construct(EmailConfigurationService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Obtener configuración actual de correo y plantillas.
     */
    public function getConfiguration(): JsonResponse
    {
        $setting = $this->emailService->getConfiguration();

        return response()->json([
            'success' => true,
            'data' => new EmailConfigurationResource($setting),
        ]);
    }

    /**
     * Actualizar configuración de correo y plantillas.
     */
    public function updateConfiguration(UpdateEmailConfigurationRequest $request): JsonResponse
    {
        $setting = $this->emailService->updateConfiguration($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Configuración de correos y plantillas guardada con éxito.',
            'data' => new EmailConfigurationResource($setting),
        ]);
    }

    /**
     * Probar envío de correo SMTP en vivo.
     */
    public function testConnection(TestEmailConnectionRequest $request): JsonResponse
    {
        $testEmail = (string) $request->validated()['test_email'];
        $result = $this->emailService->testSmtpConnection($testEmail);

        $status = !empty($result['success']) ? 200 : 422;

        return response()->json($result, $status);
    }
}
