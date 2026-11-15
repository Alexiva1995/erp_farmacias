<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPublicLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_consult_client_by_identification(): void
    {
        Client::create([
            'name' => 'Pedro Ramirez',
            'identification' => 'V-15443322',
        ]);

        $this->getJson('/api/public/clients/identification/V-15443322')
            ->assertOk();
    }
}
