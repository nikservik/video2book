<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticateMcpUrlTokenTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_it_rejects_invalid_url_token(): void
    {
        $response = $this->postJson('/mcp/video2book/invalid-token', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'ping',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_it_authenticates_valid_url_token_for_mcp_requests(): void
    {
        $user = User::factory()->create([
            'access_token' => (string) Str::uuid(),
        ]);

        $response = $this->postJson('/mcp/video2book/'.$user->access_token, [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'ping',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('result', []);
    }

    public function test_route_without_token_does_not_match(): void
    {
        $this->postJson('/mcp/video2book', [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'ping',
        ])->assertStatus(404);
    }
}
