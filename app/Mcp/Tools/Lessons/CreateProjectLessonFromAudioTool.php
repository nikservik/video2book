<?php

namespace App\Mcp\Tools\Lessons;

use App\Actions\Project\CreateProjectLessonFromAudioAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Services\Project\LessonRunsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-project-lesson-from-audio')]
#[Description('Добавляет в проект урок с загрузкой аудиофайла и запускает стандартную нормализацию аудио.')]
class CreateProjectLessonFromAudioTool extends Tool
{
    private const ALLOWED_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/aac',
        'audio/ogg',
        'audio/webm',
        'audio/flac',
    ];

    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly CreateProjectLessonFromAudioAction $createProjectLessonFromAudioAction,
        private readonly LessonRunsQuery $lessonRunsQuery,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
            'lesson_name' => ['required', 'string', 'max:255'],
            'pipeline_version_id' => ['required', 'integer', 'min:1'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255', 'in:'.implode(',', self::ALLOWED_MIME_TYPES)],
            'content_base64' => ['required', 'string'],
        ], attributes: [
            'project_id' => 'проект',
            'lesson_name' => 'название урока',
            'pipeline_version_id' => 'версия шаблона',
            'filename' => 'имя файла',
            'mime_type' => 'MIME-тип',
            'content_base64' => 'содержимое аудиофайла',
        ]);

        $viewer = $this->mcpModelResolver->user($request);
        $project = $this->mcpModelResolver->visibleProject($viewer, (int) $validated['project_id']);
        $pipelineVersionId = $this->mcpModelResolver->allowedPipelineVersionId(
            $viewer,
            (int) $validated['pipeline_version_id'],
        );

        $binaryContent = base64_decode($validated['content_base64'], true);

        if ($binaryContent === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'Не удалось декодировать base64 содержимое аудиофайла.',
            ]);
        }

        $tmpDirectory = storage_path('app/tmp/mcp-uploads');
        File::ensureDirectoryExists($tmpDirectory);

        $safeFilename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $validated['filename']) ?? 'audio-file';
        $temporaryPath = $tmpDirectory.'/'.Str::uuid().'-'.$safeFilename;
        file_put_contents($temporaryPath, $binaryContent);

        try {
            $uploadedFile = new UploadedFile(
                path: $temporaryPath,
                originalName: $validated['filename'],
                mimeType: $validated['mime_type'],
                test: true,
            );

            $lesson = $this->createProjectLessonFromAudioAction->handle(
                project: $project,
                lessonName: trim($validated['lesson_name']),
                audioFile: $uploadedFile,
                pipelineVersionId: (int) $pipelineVersionId,
            );
        } finally {
            File::delete($temporaryPath);
        }

        $lesson->setRelation('pipelineRuns', $this->lessonRunsQuery->get($lesson));

        return Response::structured([
            'lesson' => $this->mcpPresenter->lesson($lesson),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID проекта, в который нужно добавить урок.'),
            'lesson_name' => $schema->string()
                ->required()
                ->max(255)
                ->description('Название нового урока.'),
            'pipeline_version_id' => $schema->integer()
                ->required()
                ->description('ID версии шаблона для нового урока.'),
            'filename' => $schema->string()
                ->required()
                ->max(255)
                ->description('Исходное имя аудиофайла.'),
            'mime_type' => $schema->string()
                ->required()
                ->max(255)
                ->description('MIME-тип аудиофайла.'),
            'content_base64' => $schema->string()
                ->required()
                ->description('Аудиофайл, закодированный в base64.'),
        ];
    }
}
