<?php

namespace Tests\Feature\Configuration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_public_bootstrap_config(): void
    {
        $this->getJson('/api/public/bootstrap-config')
            ->assertOk();
    }
}
