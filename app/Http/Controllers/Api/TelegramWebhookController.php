<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TelegramWebhookController extends Controller
{
    protected TelegramWebhookService $telegramWebhookService;

    public function __construct(TelegramWebhookService $telegramWebhookService)
    {
        $this->telegramWebhookService = $telegramWebhookService;
    }

    /**
     * Manejar las peticiones entrantes de Telegram.
     */
    public function handle(Request $request): JsonResponse
    {
        $this->telegramWebhookService->handleWebhook($request->all());

        return response()->json(['status' => 'ok']);
    }
}
