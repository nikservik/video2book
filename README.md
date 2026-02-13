# Video2Book

Серверное приложение для обработки лекций: проекты, уроки, версии пайплайнов, очереди прогонов и интеграции с LLM-провайдерами.

Текущая целевая архитектура: **Laravel + Livewire** (без desktop-клиента).

## Что делает приложение

- Управляет проектами и уроками.
- Хранит версионируемые пайплайны и шаги.
- Запускает обработку уроков через очередь `pipelines`.
- Поддерживает транскрибацию и текстовые шаги через OpenAI / Anthropic / Gemini.
- Считает usage и стоимость по каждому шагу.
- Экспортирует результаты шагов в PDF/Markdown.
- Поддерживает загрузку уроков по YouTube-ссылке через `yt-dlp`.

## Технологии

- PHP 8.4+
- Laravel 12
- Queue (database driver по умолчанию)
- Blade + Vite
- Livewire (целевая серверная UI-архитектура)
- Laravel AI SDK (`laravel/ai`) как единый слой для OpenAI / Anthropic / Gemini
- `protonemedia/laravel-ffmpeg` и `norkunas/youtube-dl-php`

## Быстрый старт

### 1. Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

### 2. Обязательные `.env` переменные

Минимум:

- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY` (если используете Anthropic)
- `GEMINI_API_KEY` (если используете Gemini)

Полезные настройки:

- `LLM_REQUEST_TIMEOUT=1800`
- `LLM_ANTHROPIC_MAX_TOKENS=64000`
- `YTDLP_BINARY=yt-dlp`
- `QUEUE_CONNECTION=database`

### 3. Запуск разработки

В одном окне:

```bash
composer dev
```

Или отдельными процессами:

```bash
php artisan serve
php artisan queue:listen --tries=1 --queue=pipelines
npm run dev
```

## Архитектура репозитория

- `app/Http/Controllers` — API-контроллеры.
- `app/Jobs` — фоновые задания.
- `app/Models` — Eloquent-модели.
- `app/Services/Lesson` — загрузка/подготовка медиа.
- `app/Services/Pipeline` — запуск шагов, статусы, экспорт.
- `app/Services/Llm` — интеграция с Laravel AI SDK, usage/cost и лимиты.
- `routes/api.php` — REST API.
- `routes/web.php` — веб-маршруты для серверного UI.
- `resources/views` — Blade-шаблоны.
- `tests/Feature`, `tests/Unit` — покрытие API/сервисов.

## Доменные сущности

- `Project` — контейнер уроков.
- `Lesson` — единица обработки (источник + настройки).
- `Pipeline` / `PipelineVersion` — версия сценария обработки.
- `Step` / `StepVersion` — шаги версии пайплайна.
- `PipelineRun` / `PipelineRunStep` — фактический прогон и шаги выполнения.
- `ProjectTag` — теги проектов/уроков.

## API (основное)

- `GET/POST/PUT /api/projects`
- `POST /api/projects/youtube`
- `GET/POST/PUT /api/lessons`
- `POST /api/lessons/{lesson}/audio`
- `POST /api/lessons/{lesson}/download`
- `GET/POST/PUT /api/pipelines`
- `GET /api/pipeline-versions/{id}/steps`
- `GET/POST /api/pipeline-runs`
- `POST /api/pipeline-runs/{run}/restart`
- `GET /api/pipeline-runs/{run}/steps/{step}/export/pdf`
- `GET /api/pipeline-runs/{run}/steps/{step}/export/md`

## Livewire-переход

Архитектурное решение на этом этапе: развивать серверный UI на Livewire и использовать существующий доменный/сервисный слой без дублирования логики.

Рекомендованный подход:

1. Строить новые пользовательские сценарии как Livewire-компоненты.
2. Сложные операции оставлять в сервисах и очередях.
3. API сохранять как стабильный контракт для внутренних и внешних клиентов.

Текущий статус UI:
- Базовый серверный layout (`resources/views/layouts/app.blade.php`) уже подключён для web-страниц и содержит верхнюю навигацию, mobile-меню и контентный контейнер.
- В правой части layout используются кнопка настроек (`cog-8-tooth`, heroicons) и переключатель светлой/тёмной темы (адаптация Tailwind UI под Blade/Livewire), который применяет тему ко всей странице.

## LLM и транскрибация

- Текстовые шаги выполняются через Laravel AI SDK (`config/ai.php`).
- Для транскрибации с провайдером `gemini` используется multimodal-подход: промт + audio attachment в текстовом запросе к Gemini.
- Если выбрана модель `whisper-*`, транскрибация идёт через штатный STT Laravel AI SDK.
- Кастомные прямые SDK-клиенты провайдеров в приложении не используются.

## Проверки качества

```bash
php artisan test
./vendor/bin/pint --test
```

При изменении фронтовых ассетов/шаблонов дополнительно:

```bash
npm run build
```

## Документация

- Основные правила разработки: `AGENTS.md`
- Актуальные документы: `docs/`
- Архив старых документов: `docs/old/`

## История изменений

- Все изменения фиксируются в `CHANGELOG.md`.

## Лицензия

MIT
