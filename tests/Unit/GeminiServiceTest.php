<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_gemini_service_uses_config_api_key()
    {
        Config::set('services.gemini.api_key', 'test-api-key-12345');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"matched": true, "product_supplier_id": 1, "confidence_score": 0.9, "reason": "Exact match"}']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new GeminiService();
        $result = $service->matchProduct(
            ['name' => 'Paracetamol 500mg', 'laboratory' => 'Genven', 'active_ingredient' => 'Paracetamol'],
            [['id' => 1, 'name' => 'Paracetamol 500mg', 'laboratory' => 'Genven', 'active_ingredient' => 'Paracetamol']]
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['matched']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=test-api-key-12345') ||
                   $request->hasHeader('x-goog-api-key', 'test-api-key-12345');
        });
    }
}
