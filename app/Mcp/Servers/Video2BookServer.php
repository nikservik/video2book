<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ProjectExportArchiveResource;
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
#[Instructions('Работает с проектами, уроками, прогонами, экспортами и очередью сервиса Video2Book.')]
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
        ProjectExportArchiveResource::class,
    ];
}
