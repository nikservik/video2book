<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerRouteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_get_requests_to_mcp_route_return_method_not_allowed(): void
    {
        $this->get('/mcp/video2book/demo-token')
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST');
    }

    public function test_delete_requests_to_mcp_route_return_method_not_allowed(): void
    {
        $this->delete('/mcp/video2book/demo-token')
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST');
    }

    public function test_mcp_route_is_registered(): void
    {
        $this->assertNotNull(app('router')->getRoutes()->match(
            request()->create('/mcp/video2book/demo-token', 'GET')
        ));
    }
}
