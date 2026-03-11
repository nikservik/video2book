<?php

namespace Tests\Feature\Mcp;

use App\Models\Folder;
use App\Models\Lesson;
use App\Models\Pipeline;
use App\Models\PipelineRun;
use App\Models\PipelineRunStep;
use App\Models\PipelineVersion;
use App\Models\PipelineVersionStep;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Step;
use App\Models\StepVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class McpResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $withTeamAuthCookie = false;

    public function test_it_reads_run_step_markdown_resource(): void
    {
        $user = $this->makeUser();
        [, , $run, $step] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://pipeline-runs/%d/steps/%d/export/markdown',
                $run->id,
                $step->id,
            ),
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('result.contents.0.mimeType', 'text/markdown')
            ->assertJsonPath('result.contents.0.text', '# Result');
    }

    public function test_it_reads_run_step_pdf_resource(): void
    {
        $user = $this->makeUser();
        [, , $run, $step] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://pipeline-runs/%d/steps/%d/export/pdf',
                $run->id,
                $step->id,
            ),
        );

        $response->assertStatus(200)
            ->assertJsonPath('result.contents.0.mimeType', 'application/pdf');

        $this->assertStringStartsWith(
            '%PDF',
            (string) base64_decode((string) $response->json('result.contents.0.blob'))
        );
    }

    public function test_it_reads_run_step_docx_resource(): void
    {
        $user = $this->makeUser();
        [, , $run, $step] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://pipeline-runs/%d/steps/%d/export/docx',
                $run->id,
                $step->id,
            ),
        );

        $response->assertStatus(200)
            ->assertJsonPath(
                'result.contents.0.mimeType',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );

        $this->assertStringStartsWith(
            'PK',
            (string) base64_decode((string) $response->json('result.contents.0.blob'))
        );
    }

    public function test_it_reads_project_export_archive_resource(): void
    {
        $user = $this->makeUser();
        [$project, $pipelineVersion, , $runStep, $stepVersion] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://projects/%d/exports/%d/%d/md/lesson_step',
                $project->id,
                $pipelineVersion->id,
                $stepVersion->id,
            ),
        );

        $response->assertStatus(200)
            ->assertJsonPath('result.contents.0.mimeType', 'application/zip');

        $this->assertStringStartsWith(
            'PK',
            (string) base64_decode((string) $response->json('result.contents.0.blob'))
        );
    }

    public function test_it_reads_project_single_file_markdown_resource(): void
    {
        $user = $this->makeUser();
        [$project, $pipelineVersion, , , $stepVersion] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://projects/%d/exports/%d/%d/single-file/markdown',
                $project->id,
                $pipelineVersion->id,
                $stepVersion->id,
            ),
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath('result.contents.0.mimeType', 'text/markdown')
            ->assertJsonPath('result.contents.0.text', "# Lesson\n\n## Result");
    }

    public function test_it_reads_project_single_file_pdf_resource(): void
    {
        $user = $this->makeUser();
        [$project, $pipelineVersion, , , $stepVersion] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://projects/%d/exports/%d/%d/single-file/pdf',
                $project->id,
                $pipelineVersion->id,
                $stepVersion->id,
            ),
        );

        $response->assertStatus(200)
            ->assertJsonPath('result.contents.0.mimeType', 'application/pdf');

        $this->assertStringStartsWith(
            '%PDF',
            (string) base64_decode((string) $response->json('result.contents.0.blob'))
        );
    }

    public function test_it_reads_project_single_file_docx_resource(): void
    {
        $user = $this->makeUser();
        [$project, $pipelineVersion, , , $stepVersion] = $this->createProjectRunWithResult();

        $response = $this->readResource(
            $user,
            sprintf(
                'video2book://projects/%d/exports/%d/%d/single-file/docx',
                $project->id,
                $pipelineVersion->id,
                $stepVersion->id,
            ),
        );

        $response->assertStatus(200)
            ->assertJsonPath(
                'result.contents.0.mimeType',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );

        $this->assertStringStartsWith(
            'PK',
            (string) base64_decode((string) $response->json('result.contents.0.blob'))
        );
    }

    private function readResource(User $user, string $uri)
    {
        return $this->postJson('/mcp/video2book/'.$user->access_token, [
            'jsonrpc' => '2.0',
            'id' => '1',
            'method' => 'resources/read',
            'params' => [
                'uri' => $uri,
            ],
        ]);
    }

    private function makeUser(int $accessLevel = User::ACCESS_LEVEL_ADMIN): User
    {
        return User::factory()->create([
            'access_token' => (string) Str::uuid(),
            'access_level' => $accessLevel,
        ]);
    }

    /**
     * @return array{0: Project, 1: PipelineVersion, 2: PipelineRun, 3: PipelineRunStep, 4: StepVersion}
     */
    private function createProjectRunWithResult(): array
    {
        ProjectTag::query()->firstOrCreate(['slug' => 'default'], ['description' => null]);

        $folder = Folder::query()->create([
            'name' => 'Folder '.Str::random(6),
            'hidden' => false,
            'visible_for' => [],
        ]);
        $project = Project::query()->create([
            'folder_id' => $folder->id,
            'name' => 'Project',
            'tags' => null,
        ]);
        $lesson = Lesson::query()->create([
            'project_id' => $project->id,
            'name' => 'Lesson',
            'tag' => 'default',
            'source_filename' => null,
            'settings' => [],
        ]);

        $pipeline = Pipeline::query()->create();
        $pipelineVersion = PipelineVersion::query()->create([
            'pipeline_id' => $pipeline->id,
            'version' => 1,
            'title' => 'Pipeline',
            'description' => null,
            'changelog' => null,
            'status' => 'active',
        ]);
        $pipeline->update(['current_version_id' => $pipelineVersion->id]);

        $step = Step::query()->create([
            'pipeline_id' => $pipeline->id,
            'current_version_id' => null,
        ]);
        $stepVersion = StepVersion::query()->create([
            'step_id' => $step->id,
            'input_step_id' => null,
            'name' => 'Summary',
            'type' => 'text',
            'version' => 1,
            'description' => null,
            'prompt' => 'Prompt',
            'settings' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ],
            'status' => 'active',
        ]);
        $step->update(['current_version_id' => $stepVersion->id]);
        PipelineVersionStep::query()->create([
            'pipeline_version_id' => $pipelineVersion->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
        ]);

        $run = PipelineRun::query()->create([
            'lesson_id' => $lesson->id,
            'pipeline_version_id' => $pipelineVersion->id,
            'status' => 'done',
            'state' => [],
        ]);
        $runStep = PipelineRunStep::query()->create([
            'pipeline_run_id' => $run->id,
            'step_version_id' => $stepVersion->id,
            'position' => 1,
            'status' => 'done',
            'result' => '# Result',
        ]);

        return [$project, $pipelineVersion, $run, $runStep, $stepVersion];
    }
}
