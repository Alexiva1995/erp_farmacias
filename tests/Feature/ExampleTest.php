<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Verifica que la aplicación responde con 200.
     * Se usa withoutVite() para evitar ViteManifestNotFoundException en CI
     * donde no existe el build de assets (public/build/manifest.json).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertStatus(200);
    }
}
