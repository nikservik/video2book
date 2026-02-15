<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $project->name }}</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2"
                 @if ($this->shouldPollProjectLessons) wire:poll.2s="refreshProjectLessons" @endif>
            @if ($project->lessons->isEmpty())
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">
                        В этом проекте пока нет уроков.
                    </p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    @foreach ($project->lessons as $lesson)
                        <article wire:key="lesson-row-{{ $lesson->id }}"
                                 class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <button type="button"
                                        wire:click="$dispatch('project-show:rename-lesson-modal-open', { lessonId: {{ $lesson->id }} })"
                                        class="inline-flex items-center gap-2 text-left font-semibold text-gray-900 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-500">
                                    {{ $lesson->name }}
                                    <span data-audio-download-status="{{ $this->lessonAudioDownloadStatus($lesson->settings, $lesson->source_filename) }}"
                                          class="{{ $this->lessonAudioDownloadIconClass($lesson->settings, $lesson->source_filename) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                                          <path fill-rule="evenodd" d="M5.5 17a4.5 4.5 0 0 1-1.44-8.765 4.5 4.5 0 0 1 8.302-3.046 3.5 3.5 0 0 1 4.504 4.272A4 4 0 0 1 15 17H5.5Zm5.25-9.25a.75.75 0 0 0-1.5 0v4.59l-1.95-2.1a.75.75 0 1 0-1.1 1.02l3.25 3.5a.75.75 0 0 0 1.1 0l3.25-3.5a.75.75 0 1 0-1.1-1.02l-1.95 2.1V7.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div class="mt-px flex items-center gap-1">
                                    <button type="button"
                                            wire:click="$dispatch('project-show:add-pipeline-to-lesson-modal-open', { lessonId: {{ $lesson->id }} })"
                                            class="text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                            aria-label="Добавить версию пайплайна">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                          <path d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5v-3.5Z" />
                                        </svg>
                                    </button>
                                    <button type="button"
                                            wire:click="$dispatch('project-show:delete-lesson-alert-open', { lessonId: {{ $lesson->id }} })"
                                            class="text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                            aria-label="Удалить урок">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                          <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            @if ($lesson->pipelineRuns->isEmpty())
                                <p class="mt-3 text-gray-500 dark:text-gray-400">Прогонов пока нет.</p>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach ($lesson->pipelineRuns as $pipelineRun)
                                        <div class="group relative">
                                            <a href="{{ route('projects.runs.show', ['project' => $project, 'pipelineRun' => $pipelineRun]) }}"
                                               wire:navigate
                                               class="block rounded-lg border border-gray-200 bg-gray-100 px-3 py-1 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-900/50 dark:hover:bg-white/5">
                                                <div class="flex items-center justify-between gap-3">
                                                    <span class="truncate text-sm text-gray-700 dark:text-gray-200">
                                                        {{ ($pipelineRun->pipelineVersion?->title ?? 'Без названия') }} • v{{ $pipelineRun->pipelineVersion?->version ?? '—' }}
                                                    </span>
                                                    <span class="{{ $this->pipelineRunStatusBadgeClass($pipelineRun->status) }}">
                                                        {{ $this->pipelineRunStatusLabel($pipelineRun->status) }}
                                                    </span>
                                                </div>
                                            </a>
                                            <button type="button"
                                                    wire:click="$dispatch('project-show:delete-run-alert-open', { pipelineRunId: {{ $pipelineRun->id }} })"
                                                    class="absolute inset-y-0 right-0 z-10 flex items-center rounded-lg bg-gray-200/80 px-2 text-gray-500 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-gray-800/80 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                                    aria-label="Удалить прогон">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                                  <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <div class="space-y-3">
                <button type="button"
                        wire:click="$dispatch('project-show:create-lesson-modal-open')"
                        class="w-full text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Добавить урок
                </button>
                <button type="button"
                        wire:click="$dispatch('project-show:rename-project-modal-open')"
                        class="w-full text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    Редактировать проект
                </button>
                <button type="button"
                        wire:click="$dispatch('project-show:project-export-modal-open', { format: 'pdf' })"
                        class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Скачать проект в PDF
                </button>
                <button type="button"
                        wire:click="$dispatch('project-show:project-export-modal-open', { format: 'md' })"
                        class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Скачать проект в MD
                </button>
                <button type="button"
                        wire:click="$dispatch('project-show:delete-project-alert-open')"
                        class="w-full text-sm rounded-lg inset-ring-1 bg-red-500/20 px-3 py-2 font-semibold text-red-700 inset-ring-red-700 hover:text-red-50 shadow-xs hover:bg-red-500 hover:inset-ring-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:text-red-600 dark:inset-ring-red-600 dark:bg-red-900/50 dark:shadow-none  dark:hover:bg-red-600 dark:focus-visible:outline-red-600">
                    Удалить проект
                </button>
            </div>
        </aside>
    </div>

    <livewire:project-show.modals.create-lesson-modal :project-id="$project->id" :key="'project-show-create-lesson-modal-'.$project->id" />
    <livewire:project-show.modals.add-pipeline-to-lesson-modal :project-id="$project->id" :key="'project-show-add-pipeline-modal-'.$project->id" />
    <livewire:project-show.modals.project-export-modal :project-id="$project->id" :key="'project-show-export-modal-'.$project->id" />
    <livewire:project-show.modals.rename-project-modal :project-id="$project->id" :key="'project-show-rename-project-modal-'.$project->id" />
    <livewire:project-show.modals.rename-lesson-modal :project-id="$project->id" :key="'project-show-rename-lesson-modal-'.$project->id" />
    <livewire:project-show.modals.delete-project-alert :project-id="$project->id" :key="'project-show-delete-project-alert-'.$project->id" />
    <livewire:project-show.modals.delete-lesson-alert :project-id="$project->id" :key="'project-show-delete-lesson-alert-'.$project->id" />
    <livewire:project-show.modals.delete-run-alert :project-id="$project->id" :key="'project-show-delete-run-alert-'.$project->id" />
</div>
