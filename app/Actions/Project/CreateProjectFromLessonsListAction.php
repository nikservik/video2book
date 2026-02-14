<?php

namespace App\Actions\Project;

use App\Models\Project;
use Illuminate\Validation\ValidationException;

class CreateProjectFromLessonsListAction
{
    public function __construct(
        private readonly CreateProjectLessonFromYoutubeAction $createProjectLessonFromYoutubeAction,
    ) {}

    public function handle(
        string $projectName,
        ?string $referer,
        ?int $defaultPipelineVersionId,
        ?string $lessonsList,
    ): Project {
        $parsedLessons = $this->parseLessonsList($lessonsList);

        if ($parsedLessons !== [] && $defaultPipelineVersionId === null) {
            throw ValidationException::withMessages([
                'newProjectDefaultPipelineVersionId' => 'Выберите версию пайплайна по умолчанию для создания уроков.',
            ]);
        }

        $project = Project::query()->create([
            'name' => trim($projectName),
            'tags' => null,
            'default_pipeline_version_id' => $defaultPipelineVersionId,
            'referer' => $referer,
        ]);

        foreach ($parsedLessons as $lesson) {
            $this->createProjectLessonFromYoutubeAction->handle(
                project: $project,
                lessonName: $lesson['name'],
                youtubeUrl: $lesson['url'],
                pipelineVersionId: (int) $defaultPipelineVersionId,
            );
        }

        return $project->fresh();
    }

    /**
     * @return array<int, array{name:string,url:string}>
     */
    private function parseLessonsList(?string $lessonsList): array
    {
        if ($lessonsList === null || trim($lessonsList) === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $lessonsList);

        if ($lines === false) {
            throw ValidationException::withMessages([
                'newProjectLessonsList' => 'Не удалось обработать список уроков.',
            ]);
        }

        $lessons = [];
        $pendingLessonTitle = null;

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                if ($pendingLessonTitle !== null) {
                    throw ValidationException::withMessages([
                        'newProjectLessonsList' => sprintf(
                            'После заголовка урока "%s" ожидается ссылка на видео.',
                            $pendingLessonTitle
                        ),
                    ]);
                }

                $title = trim(ltrim($line, '# '));

                if ($title === '') {
                    throw ValidationException::withMessages([
                        'newProjectLessonsList' => sprintf('Пустой заголовок урока в строке %d.', $lineNumber + 1),
                    ]);
                }

                $pendingLessonTitle = $title;

                continue;
            }

            if ($pendingLessonTitle === null) {
                throw ValidationException::withMessages([
                    'newProjectLessonsList' => 'Каждый урок должен начинаться со строки заголовка в формате "# Название урока".',
                ]);
            }

            if (! filter_var($line, FILTER_VALIDATE_URL) || ! str_starts_with($line, 'https://')) {
                throw ValidationException::withMessages([
                    'newProjectLessonsList' => sprintf('Некорректная ссылка в строке %d. Используйте формат https://...', $lineNumber + 1),
                ]);
            }

            $lessons[] = [
                'name' => $pendingLessonTitle,
                'url' => $line,
            ];

            $pendingLessonTitle = null;
        }

        if ($pendingLessonTitle !== null) {
            throw ValidationException::withMessages([
                'newProjectLessonsList' => sprintf('После заголовка урока "%s" ожидается ссылка на видео.', $pendingLessonTitle),
            ]);
        }

        return $lessons;
    }
}
