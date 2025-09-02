<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    /** @test */
    public function health_endpoint_returns_ok_json(): void
    {
        $res = $this->getJson('/api/health');
        $res->assertOk();
        $res->assertJsonStructure(['status','time']);
        $res->assertJson(['status' => 'ok']);
    }
}


