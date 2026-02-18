<?php

namespace App\Actions\Project;

use Illuminate\Validation\ValidationException;

class ParseLessonsListAction
{
    /**
     * @return array<int, array{name:string,url:string}>
     */
    public function handle(?string $lessonsList, string $errorField): array
    {
        if ($lessonsList === null || trim($lessonsList) === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $lessonsList);

        if ($lines === false) {
            throw ValidationException::withMessages([
                $errorField => 'Не удалось обработать список уроков.',
            ]);
        }

        $lessons = [];
        $pendingLessonTitle = null;

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                continue;
            }

            if ($pendingLessonTitle === null) {
                if (str_starts_with($line, 'https://')) {
                    throw ValidationException::withMessages([
                        $errorField => sprintf('Перед ссылкой в строке %d нужно указать название урока.', $lineNumber + 1),
                    ]);
                }

                $pendingLessonTitle = $line;

                continue;
            }

            if (! str_starts_with($line, 'https://') || ! filter_var($line, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    $errorField => sprintf(
                        'После названия урока "%s" ожидается ссылка на видео в формате https://... (строка %d).',
                        $pendingLessonTitle,
                        $lineNumber + 1
                    ),
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
                $errorField => sprintf('После названия урока "%s" ожидается ссылка на видео.', $pendingLessonTitle),
            ]);
        }

        return $lessons;
    }
}
