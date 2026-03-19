<?php

namespace App\Http\Requests\Api;

use App\Actions\Pipeline\GetPipelineVersionOptionsAction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreProjectLessonRequest extends FormRequest
{
    private const ALLOWED_AUDIO_MIME_TYPES = [
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

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'pipeline_version_id' => ['nullable', 'integer', Rule::in($this->availablePipelineVersionIds())],
            'file' => [
                'required',
                'file',
                'max:512000',
                'mimetypes:'.implode(',', self::ALLOWED_AUDIO_MIME_TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'название урока',
            'source_url' => 'ссылка на исходник',
            'pipeline_version_id' => 'версия шаблона',
            'file' => 'аудиофайл',
        ];
    }

    public function lessonName(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function sourceUrl(): ?string
    {
        $sourceUrl = $this->validated('source_url');

        if (! is_string($sourceUrl)) {
            return null;
        }

        $sourceUrl = trim($sourceUrl);

        return $sourceUrl !== '' ? $sourceUrl : null;
    }

    public function requestedPipelineVersionId(): ?int
    {
        $pipelineVersionId = $this->validated('pipeline_version_id');

        return is_numeric($pipelineVersionId) ? (int) $pipelineVersionId : null;
    }

    public function audioFile(): UploadedFile
    {
        $file = $this->file('file');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Аудиофайл обязателен.',
            ]);
        }

        return $file;
    }

    /**
     * @return array<int, int>
     */
    private function availablePipelineVersionIds(): array
    {
        $viewer = $this->user();

        return collect(app(GetPipelineVersionOptionsAction::class)->handle($viewer instanceof User ? $viewer : null))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
