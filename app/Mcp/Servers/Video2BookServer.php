<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\KnowledgeBaseSearchGuidePrompt;
use App\Mcp\Resources\ProjectExportArchiveResource;
use App\Mcp\Resources\ProjectSingleFileDocxExportResource;
use App\Mcp\Resources\ProjectSingleFileMarkdownExportResource;
use App\Mcp\Resources\ProjectSingleFilePdfExportResource;
use App\Mcp\Resources\RunStepDocxExportResource;
use App\Mcp\Resources\RunStepMarkdownExportResource;
use App\Mcp\Resources\RunStepPdfExportResource;
use App\Mcp\Tools\Folders\CreateProjectFolderTool;
use App\Mcp\Tools\Folders\ListProjectFoldersTool;
use App\Mcp\Tools\Lessons\AddProjectLessonsFromListTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromAudioTool;
use App\Mcp\Tools\Lessons\CreateProjectLessonFromUrlTool;
use App\Mcp\Tools\Lessons\ListProjectLessonsTool;
use App\Mcp\Tools\Projects\CreateProjectTool;
use App\Mcp\Tools\Projects\ListFolderProjectsTool;
use App\Mcp\Tools\Projects\ListProjectExportOptionsTool;
use App\Mcp\Tools\Projects\RecalculateProjectLessonsDurationTool;
use App\Mcp\Tools\Projects\UpdateProjectTool;
use App\Mcp\Tools\Queue\ListQueueTasksTool;
use App\Mcp\Tools\Runs\GetRunStepResultTool;
use App\Mcp\Tools\Runs\ListLessonRunsTool;
use App\Mcp\Tools\Runs\ListPipelineTemplatesTool;
use App\Mcp\Tools\Runs\ListRunStepsTool;
use App\Mcp\Tools\Runs\RestartRunStepTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Video2Book')]
#[Version('1.0.0')]
#[Instructions(
    "Video2Book — это внутренняя корпоративная база знаний компании.\n\n".
    "Структура данных:\n".
    "- Папка — широкая группировка по теме, преподавателю или типу материалов.\n".
    "- Проект — более узкая группировка: ступень курса, набор эфиров или один мастер-класс.\n".
    "- Урок — отдельная лекция, эфир или занятие.\n".
    "- Шаблон — сценарий обработки урока.\n".
    "- Прогон — результат обработки одного урока одним шаблоном.\n".
    "- Шаг — отдельный этап внутри прогона.\n\n".
    "Правила поиска:\n".
    "- Используй папки и проекты для сужения области поиска.\n".
    "- Основное знание обычно находится в результатах шагов внутри прогонов.\n".
    "- Для поиска по смыслу, тезисам, определениям и выводам сначала смотри шаг с is_default=true.\n".
    "- Чаще всего именно шаг по умолчанию содержит структурированный конспект и является лучшим источником ответа.\n".
    "- Если у урока есть несколько прогонов, сначала выбирай завершённые прогоны.\n".
    "- Если нужно собрать знания сразу по нескольким урокам проекта, используй экспорт проекта в режиме Одним файлом.\n".
    "- Экспорт проекта Одним файлом объединяет результаты всех подходящих уроков в один документ: перед каждым уроком ставится заголовок первого уровня, а внутренние заголовки результата сдвигаются на уровень глубже.\n".
    "- Шаблон Timeline и похожие на него шаблоны полезны для навигации по темам, но обычно не являются главным источником полного ответа.\n\n".
    "Рекомендуемый порядок работы:\n".
    "1. Найди подходящие папки.\n".
    "2. Найди нужные проекты внутри папки.\n".
    "3. Найди нужные уроки внутри проекта.\n".
    "4. Просмотри прогоны урока.\n".
    "5. Посмотри структуру шаблона и список шагов.\n".
    "6. Сначала читай результат шага с is_default=true.\n".
    "7. Если нужен обзор сразу по нескольким урокам проекта, используй single_file экспорт проекта.\n".
    "8. При необходимости переходи к другим шагам или шаблонам.\n\n".
    'Все материалы и лекции в системе русскоязычные. Используй русские термины и ориентируйся на поиск знаний во внутренней базе.'
)]
class Video2BookServer extends Server
{
    protected array $tools = [
        ListProjectFoldersTool::class,
        CreateProjectFolderTool::class,
        ListFolderProjectsTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        RecalculateProjectLessonsDurationTool::class,
        ListProjectExportOptionsTool::class,
        ListProjectLessonsTool::class,
        CreateProjectLessonFromUrlTool::class,
        CreateProjectLessonFromAudioTool::class,
        AddProjectLessonsFromListTool::class,
        ListLessonRunsTool::class,
        ListPipelineTemplatesTool::class,
        ListRunStepsTool::class,
        GetRunStepResultTool::class,
        RestartRunStepTool::class,
        ListQueueTasksTool::class,
    ];

    protected array $resources = [
        RunStepMarkdownExportResource::class,
        RunStepPdfExportResource::class,
        RunStepDocxExportResource::class,
        ProjectSingleFileMarkdownExportResource::class,
        ProjectSingleFilePdfExportResource::class,
        ProjectSingleFileDocxExportResource::class,
        ProjectExportArchiveResource::class,
    ];

    protected array $prompts = [
        KnowledgeBaseSearchGuidePrompt::class,
    ];
}
