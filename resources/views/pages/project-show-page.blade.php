<div class="space-y-6"
     x-data="{ isActionsMenuOpen: false }"
     x-on:keydown.escape.window="isActionsMenuOpen = false">
    <div class="mx-2 md:mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
            {{ $project->name }}
            @if (($projectAudioDuration = $this->projectLessonsAudioDurationLabel($project->settings)) !== null)
                <span class="md:ml-3 inline-block tracking-normal text-lg font-normal text-gray-500 dark:text-gray-400">
                    Длительность {{ $projectAudioDuration }}
                </span>
            @endif
        </h1>
        <button type="button"
                x-on:click="isActionsMenuOpen = !isActionsMenuOpen"
                x-bind:aria-expanded="isActionsMenuOpen ? 'true' : 'false'"
                data-project-actions-toggle
                class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white dark:focus:outline-indigo-500 md:hidden">
            <span class="absolute -inset-0.5"></span>
            <span class="sr-only">Открыть меню действий проекта</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" x-bind:class="{ 'hidden': isActionsMenuOpen }" class="size-6">
                <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" x-bind:class="{ 'hidden': !isActionsMenuOpen }" class="size-6 hidden">
                <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <div class="relative grid grid-cols-1 gap-6 md:grid-cols-3">
        <aside class="absolute top-0 right-0 left-0 z-20 md:order-2 md:static md:col-span-1 md:block"
             x-bind:class="{ 'hidden': !isActionsMenuOpen }"
             x-on:click.outside="if (! $event.target.closest('[data-project-actions-toggle]') && ! $event.target.closest('[data-lesson-sort-select]') && ! $event.target.closest('el-options')) { isActionsMenuOpen = false }"
             x-transition
             data-project-actions-menu>
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-gray-800 md:bg-transparent md:dark:bg-transparent md:p-0 md:shadow-none md:border-none">
                <div class="space-y-3">
                    <div class="space-y-2" data-project-actions-section="settings">
                        <div data-lesson-sort-select wire:ignore>
                            <div>
                                <el-select id="project-lessons-sort"
                                           name="project_lessons_sort"
                                           value="{{ $lessonSort }}"
                                           wire:model.live="lessonSort"
                                           x-on:change="$el.querySelector('el-options')?.hidePopover(); isActionsMenuOpen = false"
                                           class="block w-full">
                                    <button type="button"
                                            class="w-full text-sm font-semibold inline-flex items-center rounded-lg bg-white py-2 pr-2 pl-3 text-center text-gray-900  outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/10 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                        <el-selectedcontent class="flex-1 truncate pl-6">{{ $this->selectedLessonSortLabel }}</el-selectedcontent>
                                        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                             class="size-4 text-gray-500 dark:text-gray-400">
                                            <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <el-options anchor="bottom start" popover
                                                x-on:toggle="
                                                    if ($event.newState === 'open') {
                                                        $wire.markLessonSortDropdownOpened()
                                                    } else {
                                                        $wire.markLessonSortDropdownClosed()
                                                    }
                                                "
                                                class=" w-(--button-width) overflow-auto rounded-lg bg-white shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                        @foreach ($this->lessonSortOptions() as $option)
                                            <el-option value="{{ $option['value'] }}"
                                                       class="group/option relative block cursor-default px-3 py-2 text-sm text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden group-aria-selected/option:bg-indigo-50 group-aria-selected/option:font-semibold dark:text-white dark:focus:bg-indigo-500 dark:group-aria-selected/option:bg-indigo-500/20">
                                                <span class="block truncate">{{ $option['label'] }}</span>
                                            </el-option>
                                        @endforeach
                                    </el-options>
                                </el-select>
                            </div>
                        </div>
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:rename-project-modal-open')"
                                class="w-full text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Редактировать проект
                        </button>
                        <button type="button"
                                wire:click="recalculateProjectAudioDuration"
                                wire:loading.attr="disabled"
                                wire:target="recalculateProjectAudioDuration"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            <svg wire:loading
                                 wire:target="recalculateProjectAudioDuration"
                                 xmlns="http://www.w3.org/2000/svg"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 class="size-4 animate-spin text-gray-500 dark:text-gray-300">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                                <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z"/>
                            </svg>
                            Пересчитать длительность
                        </button>
                    </div>

                    <div class="space-y-2 border-t-0 border-gray-200 pt-3 dark:border-white/10" data-project-actions-section="lessons">
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:create-lesson-modal-open')"
                                class="w-full text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                            Добавить урок
                        </button>
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:add-lesson-from-audio-modal-open')"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Добавить урок из аудио
                        </button>
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:add-lessons-list-modal-open')"
                                @disabled($project->default_pipeline_version_id === null)
                                data-add-lessons-list-button
                                data-disabled="{{ $project->default_pipeline_version_id === null ? 'true' : 'false' }}"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            Добавить список уроков
                        </button>
                    </div>

                    <div class="space-y-2 border-t-0 border-gray-200 pt-3 dark:border-white/10" data-project-actions-section="exports">
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:project-export-modal-open', { format: 'pdf' })"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Скачать проект в PDF
                        </button>
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:project-export-modal-open', { format: 'md' })"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Скачать проект в MD
                        </button>
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:project-export-modal-open', { format: 'docx' })"
                                class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Скачать проект в DOCX
                        </button>
                    </div>

                    <div class="space-y-2 border-t-0 border-gray-200 pt-3 dark:border-white/10" data-project-actions-section="danger">
                        <button type="button"
                                x-on:click="isActionsMenuOpen = false"
                                wire:click="$dispatch('project-show:delete-project-alert-open')"
                                class="w-full text-sm rounded-lg inset-ring-1 bg-red-500/20 px-3 py-2 font-semibold text-red-700 inset-ring-red-700 hover:text-red-50 shadow-xs hover:bg-red-500 hover:inset-ring-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:text-red-600 dark:inset-ring-red-600 dark:bg-red-900/50 dark:shadow-none dark:hover:bg-red-600 dark:focus-visible:outline-red-600">
                            Удалить проект
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <section class="md:order-1 md:col-span-2"
                 @if ($this->shouldPollProjectLessons) wire:poll.2s="refreshProjectLessons" @endif>
            @if ($project->lessons->isEmpty())
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">
                        В этом проекте пока нет уроков.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800 divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($project->lessons as $lesson)
                        <article wire:key="lesson-row-{{ $lesson->id }}"
                                 x-on:click="
                                     const runUrl = $el.dataset.singleRunUrl

                                     if (! runUrl) {
                                         return
                                     }

                                     if ($event.target.closest('a, button')) {
                                         return
                                     }

                                     if (window.Livewire?.navigate) {
                                         window.Livewire.navigate(runUrl)
                                         return
                                     }

                                     window.location.href = runUrl
                                 "
                                 data-lesson-single-run="{{ $this->lessonHasSinglePipelineRun($lesson) ? 'true' : 'false' }}"
                                 data-single-run-url="{{ $this->lessonSinglePipelineRunUrl($lesson) ?? '' }}"
                                 @class([
                                     'px-4 py-3 flex flex-col items-start gap-2 md:gap-3 md:pr-3 md:flex-row',
                                     'cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5' => $this->lessonHasSinglePipelineRun($lesson),
                                 ])>
                            <div class="w-full flex items-start justify-between gap-3 md:w-2/3">
                                <span class="text-base text-gray-900 dark:text-white">
                                    {{ $lesson->name }}
                                </span>
                                <div class="mt-px flex items-center gap-1">
                                    @if(isset($lesson->settings['url']))
                                        <a target="_blank" href="{{ $lesson->settings['url'] }}"
                                                class="inline-flex items-center rounded-lg bg-white p-1 font-semibold text-gray-500 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
                                                aria-label="Отрыть исходник урока">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                              <path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z" clip-rule="evenodd" />
                                              <path fill-rule="evenodd" d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    @endif
                                    <span data-audio-download-status="{{ $this->lessonAudioDownloadStatus($lesson->settings, $lesson->source_filename) }}"
                                          @if ($this->lessonAudioDownloadStatus($lesson->settings, $lesson->source_filename) === 'failed')
                                              title="{{ $this->lessonAudioDownloadErrorTooltip($lesson->settings, $lesson->source_filename) }}"
                                              data-audio-download-error="{{ $this->lessonAudioDownloadErrorTooltip($lesson->settings, $lesson->source_filename) }}"
                                          @endif
                                          class="{{ $this->lessonAudioDownloadIconClass($lesson->settings, $lesson->source_filename) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                                          <path fill-rule="evenodd" d="M5.5 17a4.5 4.5 0 0 1-1.44-8.765 4.5 4.5 0 0 1 8.302-3.046 3.5 3.5 0 0 1 4.504 4.272A4 4 0 0 1 15 17H5.5Zm5.25-9.25a.75.75 0 0 0-1.5 0v4.59l-1.95-2.1a.75.75 0 1 0-1.1 1.02l3.25 3.5a.75.75 0 0 0 1.1 0l3.25-3.5a.75.75 0 1 0-1.1-1.02l-1.95 2.1V7.75Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    <span class="inline-block text-right w-10 min-w-10 shrink-0 whitespace-nowrap text-xs font-medium text-gray-500 dark:text-gray-400">
                                        @if (($lessonAudioDuration = $this->lessonAudioDurationLabel($lesson->settings, $lesson->source_filename)) !== null)
                                            {{ $lessonAudioDuration }}
                                        @endif
                                    </span>
                                    <button type="button"
                                            wire:click="$dispatch('project-show:rename-lesson-modal-open', { lessonId: {{ $lesson->id }} })"
                                            class="inline-flex items-center rounded-lg bg-white p-1 font-semibold text-gray-500 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
                                            aria-label="Изменить название урока">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                          <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                                          <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                                        </svg>
                                    </button>
                                    <button type="button"
                                            wire:click="$dispatch('project-show:delete-lesson-alert-open', { lessonId: {{ $lesson->id }} })"
                                            class="inline-flex items-center rounded-lg bg-white p-1 font-semibold text-gray-500 shadow-xs inset-ring inset-ring-gray-300 hover:text-red-600 hover:bg-red-400/50 hover:inset-ring-red-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-red-800/50 dark:hover:text-red-500 dark:hover:inset-ring-red-800/50"
                                            aria-label="Удалить урок">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                          <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="w-full md:w-1/3 flex items-start gap-1">
                                @if ($lesson->pipelineRuns->isEmpty())
                                    <p class="w-full text-sm text-center py-0.5 text-gray-500 dark:text-gray-400">Шаблонов пока нет</p>
                                @else
                                    <div class="min-w-0 space-y-1">
                                        @foreach ($lesson->pipelineRuns as $pipelineRun)
                                            <div class="group relative min-w-0">
                                                <a href="{{ route('projects.runs.show', ['project' => $project, 'pipelineRun' => $pipelineRun]) }}"
                                                   wire:navigate
                                                   class="block min-w-0 rounded-lg border border-gray-200 bg-gray-100 px-3 py-0.5 hover:bg-gray-200 dark:border-white/10 dark:bg-gray-900/50 dark:hover:bg-white/5">
                                                    <div class="flex items-center justify-between gap-3">
                                                        <span class="truncate text-sm text-gray-700 dark:text-gray-200">
                                                            {{ $this->showPipelineRunVersionInLessonCard
                                                                ? (($pipelineRun->pipelineVersion?->title ?? 'Без названия').' • v'.($pipelineRun->pipelineVersion?->version ?? '—'))
                                                                : ($pipelineRun->pipelineVersion?->title ?? 'Без названия') }}
                                                        </span>
                                                        <span class="{{ $this->pipelineRunStatusBadgeClass($pipelineRun->status) }}">
                                                            {!! $this->pipelineRunStatusLabel($pipelineRun->status) !!}
                                                        </span>
                                                    </div>
                                                </a>
                                                @if ($this->showPipelineRunVersionInLessonCard)
                                                    <button type="button"
                                                            wire:click="$dispatch('project-show:delete-run-alert-open', { pipelineRunId: {{ $pipelineRun->id }} })"
                                                            class="absolute inset-y-0 right-0 z-10 flex items-center rounded-lg bg-gray-200/80 px-2 text-gray-500 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-gray-800/80 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                                            aria-label="Удалить прогон">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                                          <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                <button type="button"
                                        wire:click="$dispatch('project-show:add-pipeline-to-lesson-modal-open', { lessonId: {{ $lesson->id }} })"
                                        class="inline-flex items-center rounded-lg bg-white p-1 font-semibold text-gray-500 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
                                        aria-label="Добавить версию шаблона">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                      <path d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5v-3.5Z" />
                                    </svg>
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <livewire:project-show.modals.create-lesson-modal :project-id="$project->id" :key="'project-show-create-lesson-modal-'.$project->id" />
    <livewire:project-show.modals.add-lesson-from-audio-modal :project-id="$project->id" :key="'project-show-add-lesson-from-audio-modal-'.$project->id" />
    <livewire:project-show.modals.add-lessons-list-modal :project-id="$project->id" :key="'project-show-add-lessons-list-modal-'.$project->id" />
    <livewire:project-show.modals.add-pipeline-to-lesson-modal :project-id="$project->id" :key="'project-show-add-pipeline-modal-'.$project->id" />
    <livewire:project-show.modals.project-export-modal :project-id="$project->id" :key="'project-show-export-modal-'.$project->id" />
    <livewire:project-show.modals.rename-project-modal :project-id="$project->id" :key="'project-show-rename-project-modal-'.$project->id" />
    <livewire:project-show.modals.rename-lesson-modal :project-id="$project->id" :key="'project-show-rename-lesson-modal-'.$project->id" />
    <livewire:project-show.modals.delete-project-alert :project-id="$project->id" :key="'project-show-delete-project-alert-'.$project->id" />
    <livewire:project-show.modals.delete-lesson-alert :project-id="$project->id" :key="'project-show-delete-lesson-alert-'.$project->id" />
    <livewire:project-show.modals.delete-run-alert :project-id="$project->id" :key="'project-show-delete-run-alert-'.$project->id" />
</div>
