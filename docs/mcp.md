# MCP-сервер Video2Book

## Статус

Сервер из этого документа реализован 2026-03-10. Ниже сохранён исходный пошаговый план, по которому выполнена реализация.

Текущее состояние:

- HTTP endpoint: `/mcp/video2book/{access_token}`
- Авторизация: `AuthenticateMcpUrlToken` ищет пользователя по `users.access_token` и аутентифицирует его в `web` guard
- Санитизация логов: URL MCP в exception context редактируется до `/mcp/video2book/[redacted]`
- Сервер: `App\Mcp\Servers\Video2BookServer`
- Tools: папки, проекты, уроки, прогоны, очередь
- Resources: markdown/pdf/docx экспорт шага и zip-экспорт проекта
- Prompts: `knowledge-base-search-guide`
- Бинарные загрузки: шаги отдаются как `text/markdown`, `application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`; экспорт проекта отдается как `application/zip`

Основные проверки реализации:

- `php artisan test --compact tests/Feature/Mcp tests/Unit/Mcp`
- `php artisan mcp:inspector /mcp/video2book/{access_token}`

## Реализованный состав сервера

`Video2BookServer` регистрирует:

- tools: `list-project-folders`, `create-project-folder`, `list-folder-projects`, `create-project`, `update-project`, `recalculate-project-lessons-duration`, `list-project-export-options`, `list-project-lessons`, `create-project-lesson-from-url`, `create-project-lesson-from-audio`, `add-project-lessons-from-list`, `list-lesson-runs`, `list-pipeline-templates`, `list-run-steps`, `get-run-step-result`, `restart-run-step`, `list-queue-tasks`
- resources: `video2book://pipeline-runs/{run_id}/steps/{step_id}/export/markdown`, `video2book://pipeline-runs/{run_id}/steps/{step_id}/export/pdf`, `video2book://pipeline-runs/{run_id}/steps/{step_id}/export/docx`, `video2book://projects/{project_id}/exports/{pipeline_version_id}/{step_version_id}/{format}/{archive_file_naming}`
- prompts: `knowledge-base-search-guide`

## Исходный план реализации

## Что фиксируем на этапе планирования

Этот план синхронизирован с официальной документацией Laravel MCP и не оставляет решений на этап реализации.

- Устанавливаем пакет командой `composer require laravel/mcp`.
- Публикуем файл маршрутов MCP командой `php artisan vendor:publish --tag=ai-routes --no-interaction`.
- Сервер создаём генератором `php artisan make:mcp-server Video2BookServer --no-interaction`.
- Tools создаём генератором `php artisan make:mcp-tool ... --no-interaction`.
- Resources создаём генератором `php artisan make:mcp-resource ... --no-interaction`.
- MCP HTTP endpoint регистрируем в `routes/ai.php` через `Mcp::web(...)`.
- Промпты не создаём. В сервере будут только `tools` и `resources`.
- Все tools возвращают `Response::structured(...)`.
- Markdown resource возвращает `Response::text(...)`.
- PDF, DOCX и ZIP resources возвращают `Response::blob(...)`.
- Динамические URI для скачиваний реализуем через resource templates с `HasUriTemplate`.
- Unit-тесты tools/resources пишем через `Video2BookServer::actingAs(...)->tool(...)` и `Video2BookServer::actingAs(...)->resource(...)`.
- Ручную проверку делаем через `php artisan mcp:inspector ...`.

## Осознанное отклонение от стандартной схемы auth из документации

Официальная документация Laravel MCP показывает OAuth/Sanctum и примеры custom middleware с чтением токена из `Authorization` header. Для Video2Book принимаем другое окончательное решение:

- токен пользователя берём из `users.access_token`;
- токен передаётся прямо в URL сервера;
- endpoint сервера имеет вид `/mcp/video2book/{token}`;
- авторизация реализуется кастомным middleware на маршруте `Mcp::web(...)`.

Это не отдельный режим Laravel MCP, а сознательная кастомизация поверх поддержанного extension point: документация прямо допускает произвольный middleware на `Mcp::web` маршруте. Внутри tools/resources пользователь должен быть доступен через `auth()->user()`.

## Границы реализации

Через MCP открываем только уже существующий и проверенный UI-функционал:

- папки проектов;
- проекты в папке;
- уроки проекта;
- прогоны урока;
- очередь задач;
- скачивание экспорта шага и проекта.

Через MCP не добавляем:

- управление пользователями;
- управление шаблонами;
- просмотр активности;
- prompts;
- задел на будущие MCP-возможности вне текущего UI.

## Окончательные архитектурные решения

### Сервер

- Класс сервера: `app/Mcp/Servers/Video2BookServer.php`
- Команда создания: `php artisan make:mcp-server Video2BookServer --no-interaction`
- Атрибуты сервера:
  - `#[Name('Video2Book')]`
  - `#[Version('1.0.0')]`
  - `#[Instructions('Работает с проектами, уроками, прогонами и очередью Video2Book.')]`
- В сервере регистрируем только конкретный список classes из этого плана.

### Маршрут сервера

- Файл: `routes/ai.php`
- Регистрируем один маршрут:

```php
use App\Http\Middleware\AuthenticateMcpUrlToken;
use App\Mcp\Servers\Video2BookServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/video2book/{token}', Video2BookServer::class)
    ->middleware(AuthenticateMcpUrlToken::class);
```

### Middleware авторизации

- Класс: `app/Http/Middleware/AuthenticateMcpUrlToken.php`
- Команда создания: `php artisan make:middleware AuthenticateMcpUrlToken --no-interaction`
- Поведение middleware:
  - читает `$request->route('token')`;
  - ищет `User::query()->where('access_token', $token)->first()`;
  - если пользователь не найден, возвращает `401`;
  - если найден, вызывает `Auth::guard('web')->setUser($user)`;
  - не читает cookie;
  - не читает `Authorization` header.

### Санитизация токена в Laravel-логах

Так как токен попадает в URL, на этапе реализации сразу делаем и это:

- в `bootstrap/app.php` добавляем sanitization для exception context;
- все URL вида `/mcp/video2book/{real-token}` в Laravel-level логах и exception-reporting заменяем на `/mcp/video2book/[redacted]`.

Это часть основной реализации, а не дополнительная опция.

## Точный набор классов, которые создаём

### Tools

Создаём эти классы:

```bash
php artisan make:mcp-tool ListProjectFoldersTool --no-interaction
php artisan make:mcp-tool CreateProjectFolderTool --no-interaction
php artisan make:mcp-tool ListFolderProjectsTool --no-interaction
php artisan make:mcp-tool CreateProjectTool --no-interaction
php artisan make:mcp-tool UpdateProjectTool --no-interaction
php artisan make:mcp-tool RecalculateProjectLessonsDurationTool --no-interaction
php artisan make:mcp-tool ListProjectExportOptionsTool --no-interaction
php artisan make:mcp-tool ListProjectLessonsTool --no-interaction
php artisan make:mcp-tool CreateProjectLessonFromUrlTool --no-interaction
php artisan make:mcp-tool CreateProjectLessonFromAudioTool --no-interaction
php artisan make:mcp-tool AddProjectLessonsFromListTool --no-interaction
php artisan make:mcp-tool ListLessonRunsTool --no-interaction
php artisan make:mcp-tool ListPipelineTemplatesTool --no-interaction
php artisan make:mcp-tool ListRunStepsTool --no-interaction
php artisan make:mcp-tool GetRunStepResultTool --no-interaction
php artisan make:mcp-tool RestartRunStepTool --no-interaction
php artisan make:mcp-tool ListQueueTasksTool --no-interaction
```

### Resources

Создаём эти классы:

```bash
php artisan make:mcp-resource RunStepMarkdownExportResource --no-interaction
php artisan make:mcp-resource RunStepPdfExportResource --no-interaction
php artisan make:mcp-resource RunStepDocxExportResource --no-interaction
php artisan make:mcp-resource ProjectExportArchiveResource --no-interaction
```

## Карта tools и точка переиспользования существующего кода

### Папки

`ListProjectFoldersTool`

- Источник данных: `App\Services\Project\ProjectFoldersQuery`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Output:
  - `id`
  - `name`
  - `hidden`
  - `projects_count`
- Response: `Response::structured([...])`

`CreateProjectFolderTool`

- Источник данных: `App\Actions\Project\CreateFolderAction`
- Input:
  - `name`
  - `hidden`
  - `visible_for_user_ids`
- Дополнительная логика адаптера:
  - если `hidden=true`, принудительно добавляет в `visible_for_user_ids` текущего пользователя и всех `superadmin`;
  - если `hidden=false`, передаёт пустой `visible_for`.
- Response: `Response::structured([...])`

### Проекты

`ListFolderProjectsTool`

- Источник данных: `App\Services\Project\ProjectFoldersQuery`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `folder_id`
- Output:
  - `id`
  - `name`
  - `lessons_count`
  - `duration_seconds`
  - `duration_label`
  - `default_pipeline_version_id`
  - `referer`

`CreateProjectTool`

- Источник данных: `App\Actions\Project\CreateProjectFromLessonsListAction`
- Input:
  - `folder_id`
  - `name`
  - `referer`
  - `default_pipeline_version_id`
  - `lessons_list`
- Validation:
  - `referer` только `https://...`

`UpdateProjectTool`

- Источник данных: `App\Actions\Project\UpdateProjectNameAction`
- Input:
  - `project_id`
  - `name`
  - `referer`
  - `default_pipeline_version_id`

`RecalculateProjectLessonsDurationTool`

- Источник данных: `App\Actions\Project\RecalculateProjectLessonsAudioDurationAction`
- Input:
  - `project_id`
- Output:
  - `project_id`
  - `total_duration_seconds`
  - `total_duration_label`

`ListProjectExportOptionsTool`

- Источник данных: `App\Actions\Project\GetProjectExportPipelineStepOptionsAction`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `project_id`
- Output:
  - pipeline versions со списком text steps

### Уроки

`ListProjectLessonsTool`

- Источник данных: `App\Services\Project\ProjectDetailsQuery`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `project_id`
- Output:
  - `id`
  - `name`
  - `source_filename`
  - `audio_duration_seconds`
  - `audio_duration_label`
  - `download_status`
  - `runs`

`CreateProjectLessonFromUrlTool`

- Источник данных: `App\Actions\Project\CreateProjectLessonFromYoutubeAction`
- Input:
  - `project_id`
  - `lesson_name`
  - `youtube_url`
  - `pipeline_version_id`

`CreateProjectLessonFromAudioTool`

- Источник данных: `App\Actions\Project\CreateProjectLessonFromAudioAction`
- Input:
  - `project_id`
  - `lesson_name`
  - `pipeline_version_id`
  - `filename`
  - `mime_type`
  - `content_base64`
- Реализация:
  - декодировать `content_base64`;
  - записать временный файл в `storage/app/tmp/mcp-uploads/...`;
  - собрать `UploadedFile`;
  - передать в action;
  - удалить временный файл в `finally`.

`AddProjectLessonsFromListTool`

- Источник данных: `App\Actions\Project\AddLessonsListToProjectAction`
- Input:
  - `project_id`
  - `lessons_list`

### Прогоны и шаги

`ListLessonRunsTool`

- Источник данных: `Lesson::query()->with(['pipelineRuns.pipelineVersion', 'pipelineRuns.steps'])`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `lesson_id`
- Output:
  - `id`
  - `status`
  - `pipeline_version_id`
  - `pipeline_title`
  - `pipeline_version`
  - `steps_count`
  - `done_steps_count`

`ListPipelineTemplatesTool`

- Источник данных: `App\Actions\Pipeline\GetPipelineTemplatesCatalogAction`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input: нет
- Output:
  - `id`
  - `name`
  - `label`
  - `description`
  - `version`
  - `steps`
  - для каждого шага:
    - `id`
    - `position`
    - `name`
    - `description`
    - `is_default`

`ListRunStepsTool`

- Источник данных: `App\Services\Project\ProjectRunDetailsQuery`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `run_id`
- Output:
  - `id`
  - `position`
  - `name`
  - `status`
  - `error`
  - `input_tokens`
  - `output_tokens`
  - `cost`

`GetRunStepResultTool`

- Источник данных: `App\Services\Project\ProjectRunDetailsQuery`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input:
  - `run_id`
  - `step_id`
- Output:
  - `id`
  - `position`
  - `name`
  - `status`
  - `result`
  - `error`
  - `input_tokens`
  - `output_tokens`
  - `cost`

`RestartRunStepTool`

- Источник данных: `App\Actions\Pipeline\RestartPipelineRunFromStepAction`
- Input:
  - `run_id`
  - `step_id`
- Output:
  - `run_id`
  - `restarted_from_step_id`
  - `status`

### Очередь

`ListQueueTasksTool`

- Источник данных: `App\Services\Home\QueueWidgetDataProvider`
- Аннотации: `#[IsReadOnly]`, `#[IsIdempotent]`
- Input: нет
- Output:
  - `title`
  - `items`

## Карта resources и точка переиспользования существующего кода

### Экспорт результата шага

`RunStepMarkdownExportResource`

- Реализация: resource template
- URI template: `video2book://pipeline-runs/{runId}/steps/{stepId}/export.md`
- Источник данных: `PipelineRun`, `PipelineRunStep`
- MIME: `text/markdown`
- Response: `Response::text((string) $step->result)`

`RunStepPdfExportResource`

- Реализация: resource template
- URI template: `video2book://pipeline-runs/{runId}/steps/{stepId}/export.pdf`
- Источник данных: `App\Services\Pipeline\PipelineStepPdfExporter`
- MIME: `application/pdf`
- Response: `Response::blob($pdfBinary)`

`RunStepDocxExportResource`

- Реализация: resource template
- URI template: `video2book://pipeline-runs/{runId}/steps/{stepId}/export.docx`
- Источник данных: `App\Services\Pipeline\PipelineStepDocxExporter`
- MIME: `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- Response: `Response::blob($docxBinary)`

### Экспорт проекта

`ProjectExportArchiveResource`

- Реализация: resource template
- URI template: `video2book://projects/{projectId}/exports/{pipelineVersionId}/{stepVersionId}/{format}/{archiveNaming}.zip`
- Источник данных: `App\Actions\Project\BuildProjectStepResultsArchiveAction`
- MIME: `application/zip`
- Response: `Response::blob($zipBinary)`
- Поддерживаемые значения:
  - `format`: `pdf`, `md`, `docx`
  - `archiveNaming`: `lesson`, `lesson_step`

## Пошаговый план реализации

### 1. Установка пакета и публикация MCP routes

Команды:

```bash
composer require laravel/mcp
php artisan vendor:publish --tag=ai-routes --no-interaction
php artisan make:mcp-server Video2BookServer --no-interaction
php artisan make:middleware AuthenticateMcpUrlToken --no-interaction
```

Результат шага:

- пакет установлен;
- в проекте есть `routes/ai.php`;
- создан сервер `Video2BookServer`;
- создан middleware `AuthenticateMcpUrlToken`.

Проверка:

- файл `routes/ai.php` существует;
- серверный класс создан;
- middleware создан.

### 2. Регистрация HTTP MCP route и URL-token auth

Делаем:

- в `routes/ai.php` регистрируем `Mcp::web('/mcp/video2book/{token}', Video2BookServer::class)->middleware(AuthenticateMcpUrlToken::class);`
- в middleware реализуем lookup пользователя по `users.access_token`;
- в `bootstrap/app.php` добавляем sanitization URL для Laravel-level logging и exception-reporting.

Результат шага:

- HTTP MCP endpoint авторизует пользователя по токену из URL;
- внутри tools/resources работает `auth()->user()`;
- Laravel-логи не содержат живой токен.

Проверка:

- валидный токен авторизует;
- невалидный токен возвращает `401`;
- в логах путь замаскирован.

### 3. Генерация всех tool/resource классов

Команды:

```bash
php artisan make:mcp-tool ListProjectFoldersTool --no-interaction
php artisan make:mcp-tool CreateProjectFolderTool --no-interaction
php artisan make:mcp-tool ListFolderProjectsTool --no-interaction
php artisan make:mcp-tool CreateProjectTool --no-interaction
php artisan make:mcp-tool UpdateProjectTool --no-interaction
php artisan make:mcp-tool RecalculateProjectLessonsDurationTool --no-interaction
php artisan make:mcp-tool ListProjectExportOptionsTool --no-interaction
php artisan make:mcp-tool ListProjectLessonsTool --no-interaction
php artisan make:mcp-tool CreateProjectLessonFromUrlTool --no-interaction
php artisan make:mcp-tool CreateProjectLessonFromAudioTool --no-interaction
php artisan make:mcp-tool AddProjectLessonsFromListTool --no-interaction
php artisan make:mcp-tool ListLessonRunsTool --no-interaction
php artisan make:mcp-tool ListPipelineTemplatesTool --no-interaction
php artisan make:mcp-tool ListRunStepsTool --no-interaction
php artisan make:mcp-tool GetRunStepResultTool --no-interaction
php artisan make:mcp-tool RestartRunStepTool --no-interaction
php artisan make:mcp-tool ListQueueTasksTool --no-interaction
php artisan make:mcp-resource RunStepMarkdownExportResource --no-interaction
php artisan make:mcp-resource RunStepPdfExportResource --no-interaction
php artisan make:mcp-resource RunStepDocxExportResource --no-interaction
php artisan make:mcp-resource ProjectExportArchiveResource --no-interaction
```

Результат шага:

- весь набор классов создан;
- дальше идёт только реализация методов и регистрация в сервере.

### 4. Реализация tools для папок и проектов

Делаем:

- `ListProjectFoldersTool`
- `CreateProjectFolderTool`
- `ListFolderProjectsTool`
- `CreateProjectTool`
- `UpdateProjectTool`
- `RecalculateProjectLessonsDurationTool`
- `ListProjectExportOptionsTool`

Правила реализации:

- не копируем доменную логику из Livewire;
- вызываем существующие `Actions` и `Queries`;
- все read-only tools используют `Response::structured(...)`;
- schema и outputSchema описывают только реально возвращаемые поля;
- скрытые папки фильтруются через `Folder::visibleTo($viewer)`.

Проверка:

- список папок и проектов совпадает с UI;
- создание hidden folder не лишает создателя доступа;
- проект создаётся и редактируется как в UI;
- пересчёт длительности меняет `project.settings.lessons_audio_duration_seconds`.

### 5. Реализация tools для уроков

Делаем:

- `ListProjectLessonsTool`
- `CreateProjectLessonFromUrlTool`
- `CreateProjectLessonFromAudioTool`
- `AddProjectLessonsFromListTool`

Правила реализации:

- урок по URL создаётся через `CreateProjectLessonFromYoutubeAction`;
- урок по аудио создаётся через `CreateProjectLessonFromAudioAction`;
- список уроков строится через `ProjectDetailsQuery`;
- временные файлы для audio upload очищаются в `finally`.

Проверка:

- создание урока по URL ставит загрузку и прогон в очередь;
- создание урока по аудио ставит нормализацию и прогон в очередь;
- lessons list парсится тем же форматом, что и UI.

### 6. Реализация tools для прогонов и шагов

Делаем:

- `ListLessonRunsTool`
- `ListPipelineTemplatesTool`
- `ListRunStepsTool`
- `GetRunStepResultTool`
- `RestartRunStepTool`

Правила реализации:

- детали прогона и шага читаем через `ProjectRunDetailsQuery`;
- restart делаем только через `RestartPipelineRunFromStepAction`;
- output fields совпадают с уже существующим UI state.

Проверка:

- список прогонов урока корректный;
- список шагов прогона корректный;
- результат выбранного шага возвращается полностью;
- restart сбрасывает выбранный шаг и все последующие.

### 7. Реализация resources для скачивания

Делаем:

- `RunStepMarkdownExportResource`
- `RunStepPdfExportResource`
- `RunStepDocxExportResource`
- `ProjectExportArchiveResource`

Правила реализации:

- resources делаем template-based через `HasUriTemplate`;
- markdown export отдаёт `Response::text(...)`;
- pdf/docx/zip export отдаёт `Response::blob(...)`;
- для project export используем `BuildProjectStepResultsArchiveAction`;
- временные ZIP-файлы очищаются сразу после чтения бинарного содержимого.

Проверка:

- шаг скачивается в `md`, `pdf`, `docx`;
- проект скачивается в ZIP для `pdf`, `md`, `docx`;
- именование `lesson` и `lesson_step` работает.

### 8. Реализация tool очереди и регистрация всего набора в сервере

Делаем:

- `ListQueueTasksTool` поверх `QueueWidgetDataProvider`;
- в `Video2BookServer` регистрируем полный список tools и resources;
- в сервере не оставляем пустые заготовки и не добавляем неиспользуемые prompts.

Проверка:

- inspector видит весь список tools/resources;
- queue tool возвращает ту же структуру, что и виджет `Очередь обработки`.

### 9. Автотесты

Создаём тесты:

```bash
php artisan make:test --phpunit Feature/Mcp/AuthenticateMcpUrlTokenTest --no-interaction
php artisan make:test --phpunit Feature/Mcp/McpServerRouteTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListProjectFoldersToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/CreateProjectFolderToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListFolderProjectsToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/CreateProjectToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/UpdateProjectToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/RecalculateProjectLessonsDurationToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListProjectExportOptionsToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListProjectLessonsToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/CreateProjectLessonFromUrlToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/CreateProjectLessonFromAudioToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/AddProjectLessonsFromListToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListLessonRunsToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListPipelineTemplatesToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListRunStepsToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/GetRunStepResultToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/RestartRunStepToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ListQueueTasksToolTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/RunStepMarkdownExportResourceTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/RunStepPdfExportResourceTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/RunStepDocxExportResourceTest --no-interaction
php artisan make:test --phpunit Unit/Mcp/ProjectExportArchiveResourceTest --no-interaction
```

Что проверяем:

- auth по URL token;
- route registration;
- tool invocation через `Video2BookServer::actingAs($user)->tool(...)`;
- resource invocation через `Video2BookServer::actingAs($user)->resource(...)`;
- hidden folder visibility;
- create/update project flows;
- create lesson by URL/audio/list;
- run listing and restart;
- queue listing;
- step export;
- project export;
- redacted URL в Laravel-level logging.

### 10. Финальная ручная проверка

Команды:

```bash
php artisan mcp:inspector /mcp/video2book/{access_token}
php artisan test --compact tests/Feature/Mcp tests/Unit/Mcp
./vendor/bin/pint --dirty --format agent
```

Сценарии в inspector:

- list folders;
- create hidden folder;
- list folder projects;
- create project;
- update project;
- recalculate duration;
- list project lessons;
- add lesson by URL;
- add lesson by audio;
- add lessons by list;
- list runs;
- list templates;
- list steps;
- get step result;
- restart step;
- read markdown/pdf/docx export resource;
- read project ZIP export resource;
- list queue.

## Что не делаем в этой задаче

- не создаём MCP prompts;
- не делаем OAuth;
- не делаем Bearer auth;
- не переносим legacy API в MCP;
- не добавляем новый функционал поверх UI;
- не добавляем tools для пользователей, шаблонов и активности;
- не делаем условную регистрацию tools/resources под будущее расширение.

## Критерии готовности

- Сервер создан по официальному MCP workflow Laravel: install, publish routes, make server, make tools, make resources, register `Mcp::web(...)`.
- Все запросы MCP авторизуются токеном из URL, который сверяется с `users.access_token`.
- Весь заявленный UI-функционал из этого документа доступен через MCP.
- MCP-слой переиспользует существующие `Actions`, `Queries`, `Services`, а не дублирует бизнес-логику.
- На каждый tool и resource есть автоматический тест.
- Токен не попадает в Laravel-level логи и exception-reporting в открытом виде.
