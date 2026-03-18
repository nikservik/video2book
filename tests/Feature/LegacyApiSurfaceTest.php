<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyApiSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_legacy_endpoints_are_unavailable(): void
    {
        $this->getJson('/api/projects')->assertNotFound();
        $this->postJson('/api/projects', [])->assertNotFound();
        $this->putJson('/api/projects/1', [])->assertNotFound();
        $this->postJson('/api/projects/youtube', [])->assertNotFound();

        $this->getJson('/api/lessons')->assertNotFound();
        $this->postJson('/api/lessons', [])->assertNotFound();
        $this->putJson('/api/lessons/1', [])->assertNotFound();
        $this->postJson('/api/lessons/1/audio', [])->assertNotFound();
        $this->postJson('/api/lessons/1/download', [])->assertNotFound();

        $this->getJson('/api/project-tags')->assertNotFound();
        $this->postJson('/api/project-tags', [])->assertNotFound();
        $this->putJson('/api/project-tags/default', [])->assertNotFound();
        $this->deleteJson('/api/project-tags/default')->assertNotFound();

        $this->getJson('/api/pipelines')->assertNotFound();
        $this->postJson('/api/pipelines', [])->assertNotFound();
        $this->getJson('/api/pipelines/1')->assertNotFound();
        $this->getJson('/api/pipelines/1/versions')->assertNotFound();
        $this->putJson('/api/pipelines/1', [])->assertNotFound();
        $this->postJson('/api/pipelines/1/archive', [])->assertNotFound();
        $this->postJson('/api/pipelines/1/steps/reorder', [])->assertNotFound();
        $this->postJson('/api/pipelines/1/steps', [])->assertNotFound();
        $this->postJson('/api/pipelines/1/steps/1/initial-version', [])->assertNotFound();
        $this->postJson('/api/pipelines/1/steps/1/versions', [])->assertNotFound();
        $this->deleteJson('/api/pipelines/1/steps/1')->assertNotFound();
        $this->getJson('/api/pipeline-versions/1/steps')->assertNotFound();
        $this->getJson('/api/steps/1/versions')->assertNotFound();

        $this->postJson('/api/pipeline-runs', [])->assertNotFound();
        $this->getJson('/api/pipeline-runs/queue')->assertNotFound();
        $this->getJson('/api/pipeline-runs/events/queue')->assertNotFound();
        $this->getJson('/api/pipeline-runs/1')->assertNotFound();
        $this->get('/api/pipeline-runs/1/events')->assertNotFound();
        $this->get('/api/pipeline-runs/1/steps/1/export/pdf')->assertNotFound();
        $this->get('/api/pipeline-runs/1/steps/1/export/md')->assertNotFound();
        $this->get('/api/pipeline-runs/1/steps/1/export/docx')->assertNotFound();
        $this->postJson('/api/pipeline-runs/1/restart', [])->assertNotFound();
    }
}
