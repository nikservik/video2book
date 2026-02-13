# Changelog

All notable changes to this project are documented here.

The format is inspired by Keep a Changelog. Versions aim to follow SemVer.

## [Unreleased]

### Planned
- Server UI rollout on Livewire for project/lesson/pipeline workflows.
- Coverage expansion for Livewire-driven user scenarios.

### Changed
- Транскрибация через `gemini` переведена на multimodal-flow (audio attachment + prompt в текстовом запросе), а для `whisper-*` сохранён стандартный STT путь Laravel AI SDK.
- Добавлен основной Blade-лейаут `resources/views/layouts/app.blade.php` по шаблону dashboard-навигации (desktop + mobile), `welcome` переведён на этот layout.
- Локальная иконка приложения подключена как favicon (`public/favicon.png`, без преобразования исходного `resources/images/favicon.png`) и как логотип в основном layout.
- В правой части layout удалены bell/avatar, добавлена иконка `cog-8-tooth` (heroicons path) и переключатель светлой/тёмной темы (адаптация шаблона Tailwind UI из Vue под Blade/Livewire), который применяет тему ко всей странице.

## [2026-02-11] refactor: migrate LLM layer to Laravel AI SDK

- `app/Services/Llm` переписан на единый Laravel AI SDK: убраны кастомные провайдерные клиенты OpenAI/Anthropic/Gemini и их wiring в контейнере.
- `DefaultPipelineStepExecutor` переведён на `Laravel\\Ai\\Transcription` для STT и на новый `LlmManager` для текстовых шагов.
- Удалены зависимости `mozex/anthropic-laravel`, `openai-php/laravel`, `google-gemini-php/laravel`; `composer.lock` обновлён, Laravel обновлён до `12.51.0`.
- Обновлены unit-тесты LLM-слоя под новый gateway-based контракт и пересобран package manifest.
- Документация (`README.md`, `AGENTS.md`) синхронизирована с новой архитектурой LLM-интеграции.

## [2026-02-09] docs: Laravel + Livewire documentation baseline

- Полностью обновлены `AGENTS.md` и `README.md` под архитектуру Laravel + Livewire.
- Удалены legacy-упоминания desktop-слоя и старого монорепо-расположения.
- Исправлены ссылки на документацию: актуальные материалы живут в `docs/`, архив — в `docs/old/`.
- Обновлена структура changelog под standalone Laravel-репозиторий.

## [2026-01-07] feat: multi-lesson youtube projects

- Добавлен сервис `LessonDownloadManager` и эндпоинт `POST /api/projects/youtube`, который создаёт проект, уроки, стартовые `PipelineRun` и ставит загрузки в очередь.
- Логика резолва тегов вынесена в `LessonTagResolver`.
- Feature-тесты расширены под массовый сценарий создания уроков по ссылкам.

## [2026-01-06] feat: youtube lesson downloads

- Добавлен `POST /api/lessons/{lesson}/download` и джоба `DownloadLessonAudioJob`.
- Сервер скачивает аудио через `yt-dlp`, нормализует в MP3 и запускает pipeline processing.
- Добавлены события очереди загрузки (`download-started/progress/completed/failed`) для наблюдаемости.
- Добавлены feature-тесты `LessonDownloadTest`.

## [2026-01-03] fix: queue dispatch after audio upload

- Создание урока больше не запускает обработку до фактической загрузки аудио.
- `PipelineRunService::dispatchQueuedRuns()` используется после получения MP3.
- Исключены преждевременные старты прогонов без входного файла.
- Конфиги LLM обновлены: общий таймаут и лимит токенов Anthropic вынесены в `.env`.

## [2025-12-29] feat: pipeline runs and step statuses

- Добавлены сущности `PipelineRun` и `PipelineRunStep` со статусами выполнения.
- Реализованы эндпоинты очереди и перезапуска прогона с выбранного шага.
- `ProcessPipelineJob` обрабатывает шаги последовательно и устойчиво к retry-сценариям.
- Feature-тесты покрывают новый контур прогонов.

## [2025-12-24] feat: pipeline versioning and LLM abstraction

- Реализована версияция пайплайнов и шагов (`pipelines`, `pipeline_versions`, `steps`, `step_versions`, `pipeline_version_steps`).
- Добавлены API-контроллеры для управления пайплайнами, версиями и шагами.
- Внедрён слой `app/Services/Llm` с провайдерами OpenAI, Anthropic и Gemini.
- Добавлен расчёт стоимости в `LlmCostCalculator` на основе `config/pricing.php`.
- Unit и feature-тесты покрывают базовую доменную модель и LLM-провайдеры.
