<?php

namespace App\Mcp\Tools\Folders;

use App\Actions\Project\CreateFolderAction;
use App\Mcp\Support\McpModelResolver;
use App\Mcp\Support\McpPresenter;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-project-folder')]
#[Description('Создаёт новую папку проектов, при необходимости скрытую для выбранных пользователей.')]
class CreateProjectFolderTool extends Tool
{
    public function __construct(
        private readonly McpModelResolver $mcpModelResolver,
        private readonly CreateFolderAction $createFolderAction,
        private readonly McpPresenter $mcpPresenter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $viewer = $this->mcpModelResolver->user($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:folders,name'],
            'hidden' => ['nullable', 'boolean'],
            'visible_for_user_ids' => ['nullable', 'array'],
            'visible_for_user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $hidden = (bool) ($validated['hidden'] ?? false);
        $visibleForUserIds = $hidden
            ? collect($validated['visible_for_user_ids'] ?? [])
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
            : [];

        if ($hidden) {
            $visibleForUserIds[] = $viewer->id;
            $visibleForUserIds = array_merge(
                $visibleForUserIds,
                User::query()
                    ->where('access_level', User::ACCESS_LEVEL_SUPERADMIN)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
        }

        $folder = $this->createFolderAction->handle(
            name: trim($validated['name']),
            hidden: $hidden,
            visibleFor: array_values(array_unique($visibleForUserIds)),
        );

        return Response::structured([
            'folder' => $this->mcpPresenter->folder($folder),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->required()
                ->description('Название новой папки.')
                ->max(255),
            'hidden' => $schema->boolean()
                ->description('Сделать папку скрытой.')
                ->nullable(),
            'visible_for_user_ids' => $schema->array()
                ->description('Список ID пользователей, которым будет видна скрытая папка.')
                ->items($schema->integer())
                ->nullable(),
        ];
    }
}
