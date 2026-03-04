# AGENTS.md — Правила разработки Video2Book (Laravel + Livewire)

Документ описывает текущее состояние архитектуры Video2Book и правила внесения правок.

- Цель: развивать серверное приложение на Laravel с веб-интерфейсом на Livewire.
- Любое изменение кода сопровождаем обновлением документации и истории изменений (см. раздел «Документация и история изменений»).
- Текущий принцип: **Livewire-first**. Слой API (`app/Http/Controllers`, `routes/api.php`) является **Legacy** из периода Electron/Vue и используется как референс уже реализованных решений.
- По умолчанию legacy API-код **не редактируем**. Меняем его только при явном запросе или отдельной задаче на поддержку legacy-контракта.

## Источники истины

- `README.md` — обзор продукта, запуск окружений, требования.
- `AGENTS.md` — правила разработки, архитектурные договоренности и процесс.
- `docs/` — актуальные проектные документы и договоренности.
- `docs/old/` — архив исторических документов из desktop/monorepo периода.
- `CHANGELOG.md` — история изменений.

Если код и документация расходятся, приоритет у фактического кода. После исправления кода обязательно обновляем документы и фиксируем это в changelog.

## Архитектура

### Приложение (Laravel)

Проект находится в корне репозитория.

- `app/Http/Controllers` — **legacy API-контроллеры** (референс, не основной слой разработки).
- `app/Jobs` — фоновые задания (`ProcessPipelineJob`, `DownloadLessonAudioJob`).
- `app/Services/Pipeline` — оркестрация шагов, расчет статусов, экспорт результатов.
- `app/Services/Llm` — интеграционный слой с Laravel AI SDK, менеджер, usage/cost и rate-limits.
- `app/Services/Lesson` — загрузка и подготовка медиа по ссылкам.
- `app/Models` — доменные сущности и связи.
- `database/migrations` — схема данных.
- `routes/api.php` — **legacy API-маршруты** (для совместимости и референса).
- `routes/web.php` + `resources/views` — основной серверный веб-слой (Blade/Livewire).

### Legacy API (референс)

- Legacy API остался в проекте как образцы ранее реализованной логики и контрактов.
- Для новых задач используем `Livewire + app/Services/* + routes/web.php`.
- Не переносим новую функциональность в `app/Http/Controllers` и `routes/api.php`, если это не запрошено отдельно.
- При необходимости можно опираться на legacy API-код как на пример структуры данных, валидации и edge-case обработки.

### Веб-слой (Livewire)

- UI строим как серверный интерфейс на Livewire 4, без клиентского desktop-слоя.
- Компоненты размещаем в `app/Livewire` (или feature-подкаталогах внутри него), шаблоны — в `resources/views`.
- Новые страницы подключаем через `routes/web.php`.
- Общую бизнес-логику не дублируем в компонентах: переиспользуем `app/Services/*`.

### Очередь и выполнение пайплайнов

- Очередь `pipelines` выполняет `ProcessPipelineJob`.
- Один `PipelineRun` обрабатывается последовательно, шаг за шагом.
- Уникальные job и блокировки исключают параллельную обработку одного и того же прогона.
- При ошибке run/step получают `failed`, ретрай инициируется явно через перезапуск в текущем UI/сервисах (legacy API endpoint перезапуска сохранён как референс).

## Доменные сущности и данные

### Проекты и уроки

- Проект (`projects`): `id`, `name`, `tags`.
- Урок (`lessons`): `project_id`, `name`, `tag`, `settings`, `source_filename`.
- Создание урока в текущем продукте выполняется через Livewire-flow; legacy endpoint `POST /api/lessons` сохранён для референса и обратной совместимости.
- Аудио хранится в `storage/app/lessons/{id}.mp3`.

### Пайплайны

- `pipelines` содержит историю версий (`pipeline_versions`).
- Шаг (`steps`) — контейнер, данные шага лежат в `step_versions`.
- Поддерживаем типы шагов: `transcribe`, `text`, `glossary`.
- Изменения пайплайнов и шагов поддерживают режимы `current` и `new_version`.

### PipelineRun и PipelineRunStep

- `PipelineRun`: `queued|running|paused|failed|done`, привязан к `lesson` и `pipeline_version`.
- `PipelineRunStep`: `pending|running|paused|failed|done`, позиция, result/error, usage/cost.
- Перезапуск с шага в текущем продукте запускается из Livewire/UI; legacy endpoint `POST /api/pipeline-runs/{run}/restart` с `step_id` оставлен как референс.

### Формат API-ответов

- Раздел относится к legacy API-контрактам.
- Базовый контракт: `{ success: boolean, data?, message?, error? }`.
- Ошибки должны быть осмысленными и возвращаться корректным HTTP-кодом.

## Практики разработки

### Laravel и Livewire

- Livewire-компоненты и web-роуты — основной слой пользовательских сценариев.
- API-контроллеры (`app/Http/Controllers`) считаем legacy-слоем: не развиваем без явного запроса.
- Бизнес-правила — в сервисах (`app/Services/*`).
- Валидация через `FormRequest` или `$request->validate`.
- Для тяжелых операций (LLM, скачивание, обработка) используем очереди, не блокируем HTTP-ответ.
- Livewire-компоненты должны вызывать сервисный слой, а не дублировать алгоритмы.

### Работа с LLM

- Используем `LlmManager` поверх Laravel AI SDK (`config/ai.php`), без прямых SDK-клиентов отдельных провайдеров в коде приложения.
- В каждом шаге обязательно сохраняем usage и стоимость.
- Конфигурация и цены живут в `config/llm.php` и `config/pricing.php`.

### Тесты

- Любые правки backend/Livewire сопровождаем тестами.
- Минимальный набор перед PR:
  - `php artisan test`
  - `./vendor/bin/pint`
- Для изменений веб-слоя добавляем feature-тесты HTTP/Livewire-сценариев.
- Legacy API-тесты изменяем только если задача напрямую затрагивает legacy API.

## Код-стайл и качество

- Следуем стандартам Laravel 12 и PSR-12.
- Не добавляем шумные комментарии и избыточное логирование.
- Логи должны помогать диагностике внешних интеграций и ошибок очереди.

## Документация и история изменений

- Каждый завершённый шаг:
  - обновляем `README.md`, если меняется установка, UX, архитектура или инфраструктура;
  - обновляем релевантные документы в `docs/`;
  - добавляем запись в `CHANGELOG.md` в формате:
    - `## [YYYY-MM-DD] feat|fix|docs|refactor: описание`.
- Исторические материалы сохраняем в `docs/old/` и не используем их как текущие требования.

## Рабочий процесс

1. Перед началом читаем `README.md`, `AGENTS.md` и релевантные документы из `docs/`.
2. Определяем слой реализации: по умолчанию работаем в Livewire/web-слое, legacy API не трогаем без отдельного запроса.
3. Составляем короткий план (5-7 пунктов) и синхронизируем ожидания.
4. Вносим изменения небольшими логическими порциями.
5. Прогоняем тесты и обновляем документацию/чейнджлог в том же шаге.

## Дополнительные рекомендации

- При добавлении новых шагов пайплайна проверяем совместимость с `PipelineRunProcessingService`.
- При изменении формата результата шага синхронизируем экспортеры (`PipelineStepPdfExporter`) и API.
- Новые документы создаём в `docs/`, а архивные материалы складываем в `docs/old/`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.17
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `livewire-development` — Develops reactive Livewire 4 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `developing-with-ai-sdk` — Builds AI agents, generates text and chat responses, produces images, synthesizes audio, transcribes speech, generates vector embeddings, reranks documents, and manages files and vector stores using the Laravel AI SDK (laravel/ai). Supports structured output, streaming, tools, conversation memory, middleware, queueing, broadcasting, and provider failover. Use when building, editing, updating, debugging, or testing any AI functionality, including agents, LLMs, chatbots, text generation, image generation, audio, transcription, embeddings, RAG, similarity search, vector stores, prompting, structured output, or any AI provider (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== laravel/ai rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

</laravel-boost-guidelines>
