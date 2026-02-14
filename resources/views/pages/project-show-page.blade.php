<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $project->name }}</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
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
                                        wire:click="openRenameLessonModal({{ $lesson->id }})"
                                        class="text-left font-semibold text-gray-900 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-500">
                                    {{ $lesson->name }}
                                </button>
                                <button type="button"
                                        wire:click="openDeleteLessonAlert({{ $lesson->id }})"
                                        class="mt-px text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                        aria-label="Удалить урок">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                      <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                    </svg>
                                </button>
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
                                                    wire:click="openDeleteRunAlert({{ $pipelineRun->id }})"
                                                    class="absolute inset-y-0 right-0 px-2 z-10 rounded-lg flex items-center bg-gray-200/80 dark:bg-gray-800/80 text-gray-500 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
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
                        wire:click="openCreateLessonModal"
                        class="w-full text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Добавить урок
                </button>
                <button type="button"
                        wire:click="openRenameProjectModal"
                        class="w-full text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    Изменить название
                </button>
                <button type="button"
                        wire:click="openDeleteProjectAlert"
                        class="w-full text-sm rounded-lg inset-ring-1 bg-red-500/20 px-3 py-2 font-semibold text-red-700 inset-ring-red-700 hover:text-red-50 shadow-xs hover:bg-red-500 hover:inset-ring-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:text-red-600 dark:inset-ring-red-600 dark:bg-red-900/50 dark:shadow-none  dark:hover:bg-red-600 dark:focus-visible:outline-red-600">
                    Удалить проект
                </button>
            </div>
        </aside>
    </div>

    @if ($showCreateLessonModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-create-lesson-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeCreateLessonModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="createLessonFromYoutube" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить урок</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Заполните название, ссылку на YouTube и версию пайплайна.
                            </p>
                        </div>

                        <div>
                            <label for="lesson-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название урока</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('newLessonName')
                                    <input id="lesson-name" type="text" name="lesson_name" wire:model="newLessonName" aria-invalid="true" aria-describedby="lesson-name-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="lesson-name" type="text" name="lesson_name" wire:model="newLessonName"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('newLessonName')
                                <p id="lesson-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="lesson-youtube-url" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Ссылка на YouTube</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('newLessonYoutubeUrl')
                                    <input id="lesson-youtube-url" type="url" name="lesson_youtube_url" wire:model="newLessonYoutubeUrl" aria-invalid="true" aria-describedby="lesson-youtube-url-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="lesson-youtube-url" type="url" name="lesson_youtube_url" wire:model="newLessonYoutubeUrl"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('newLessonYoutubeUrl')
                                <p id="lesson-youtube-url-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="lesson-pipeline-version" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Версия пайплайна</label>
                            <div class="mt-2" wire:replace>
                                <el-select id="lesson-pipeline-version"
                                           name="lesson_pipeline_version"
                                           value="{{ (string) ($newLessonPipelineVersionId ?? '') }}"
                                           wire:model.live="newLessonPipelineVersionId"
                                           class="block">
                                    <button type="button"
                                            class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                        <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedPipelineVersionLabel }}</el-selectedcontent>
                                        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                             class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                                            <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <el-options anchor="bottom start" popover
                                                class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                        @forelse ($pipelineVersionOptions as $option)
                                            <el-option value="{{ $option['id'] }}"
                                                       class="group/option relative block cursor-default py-2 pr-4 pl-8 text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden dark:text-white dark:focus:bg-indigo-500">
                                                <span class="block truncate font-normal group-aria-selected/option:font-semibold">{{ $option['label'] }}</span>
                                                <span class="absolute inset-y-0 left-0 flex items-center pl-1.5 text-indigo-600 group-not-aria-selected/option:hidden group-focus/option:text-white in-[el-selectedcontent]:hidden dark:text-indigo-400">
                                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                                        <path d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                                    </svg>
                                                </span>
                                            </el-option>
                                        @empty
                                            <el-option value="" disabled
                                                       class="relative block cursor-not-allowed py-2 px-3 text-gray-500 select-none dark:text-gray-400">
                                                Нет доступных версий
                                            </el-option>
                                        @endforelse
                                    </el-options>
                                </el-select>
                            </div>
                            @error('newLessonPipelineVersionId')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeCreateLessonModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showRenameProjectModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-rename-project-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeRenameProjectModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="saveProjectName" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Изменить название проекта</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Укажите новое название проекта и сохраните изменения.
                            </p>
                        </div>

                        <div>
                            <label for="project-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('editableProjectName')
                                    <input id="project-name" type="text" name="project_name" wire:model="editableProjectName" aria-invalid="true" aria-describedby="project-name-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="project-name" type="text" name="project_name" wire:model="editableProjectName"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('editableProjectName')
                                <p id="project-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeRenameProjectModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showRenameLessonModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-rename-lesson-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeRenameLessonModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="saveLessonName" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Изменить название урока</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Укажите новое название урока и сохраните изменения.
                            </p>
                        </div>

                        <div>
                            <label for="lesson-edit-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <div class="mt-2 grid grid-cols-1">
                                @error('editableLessonName')
                                    <input id="lesson-edit-name" type="text" name="lesson_edit_name" wire:model="editableLessonName" aria-invalid="true" aria-describedby="lesson-edit-name-error"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                        <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                @else
                                    <input id="lesson-edit-name" type="text" name="lesson_edit_name" wire:model="editableLessonName"
                                           class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                                @enderror
                            </div>
                            @error('editableLessonName')
                                <p id="lesson-edit-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeRenameLessonModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteProjectAlert)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-delete-project-alert>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeDeleteProjectAlert"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10 dark:bg-red-500/10">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" aria-hidden="true" class="size-6 text-red-600 dark:text-red-400">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Удалить проект</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Вы уверены, что хотите удалить проект? Это действие нельзя отменить.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="deleteProject"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400">
                            Удалить
                        </button>
                        <button type="button" wire:click="closeDeleteProjectAlert"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteLessonAlert)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-delete-lesson-alert>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeDeleteLessonAlert"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10 dark:bg-red-500/10">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" aria-hidden="true" class="size-6 text-red-600 dark:text-red-400">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Удалить урок «{{ $deletingLessonName }}»</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Вы уверены, что хотите удалить урок вместе со всеми расшифровками? Это действие нельзя отменить.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="deleteLesson"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400">
                            Удалить
                        </button>
                        <button type="button" wire:click="closeDeleteLessonAlert"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeleteRunAlert)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-delete-run-alert>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeDeleteRunAlert"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10 dark:bg-red-500/10">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                 stroke="currentColor" aria-hidden="true" class="size-6 text-red-600 dark:text-red-400">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Удалить прогон «{{ $deletingRunLabel }}»</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Вы уверены, что хотите удалить прогон вместе со всеми расшифровками? Это действие нельзя отменить.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="deleteRun"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400">
                            Удалить
                        </button>
                        <button type="button" wire:click="closeDeleteRunAlert"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Отменить
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
