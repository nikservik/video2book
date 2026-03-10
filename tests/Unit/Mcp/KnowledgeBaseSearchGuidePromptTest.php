<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Prompts\KnowledgeBaseSearchGuidePrompt;
use App\Mcp\Servers\Video2BookServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeBaseSearchGuidePromptTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_knowledge_base_search_guide_prompt_returns_russian_search_instructions(): void
    {
        $viewer = User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => User::ACCESS_LEVEL_ADMIN,
        ]);

        Video2BookServer::actingAs($viewer)
            ->prompt(KnowledgeBaseSearchGuidePrompt::class)
            ->assertOk()
            ->assertName('knowledge-base-search-guide')
            ->assertSee([
                'внутренняя корпоративная база знаний',
                'is_default=true',
                'Timeline',
                'русскоязычной базы',
            ]);
    }
}
