<?php

namespace Tests\Feature\Restaurant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTableOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_table_returns_404_for_invalid_token(): void
    {
        $this->getJson('/api/public/restaurant/qr/table/invalid-token-999')
            ->assertStatus(404);
    }
}
