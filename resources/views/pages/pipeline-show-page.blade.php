<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
        {{ $this->selectedVersionTitle }}
        <span class="ml-3 inline-block text-base font-normal tracking-normal text-gray-500 dark:text-gray-400">v{{ $this->selectedVersionNumber }}</span>
    </h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            @if ($selectedVersion === null)
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">У этого пайплайна пока нет версий.</p>
                </div>
            @elseif ($selectedVersionSteps === [])
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">В выбранной версии пока нет шагов.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($selectedVersionSteps as $stepData)
                        <article data-pipeline-step="{{ $stepData['step_version']->id }}" class="relative rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-800">
                            <button type="button"
                                    data-step-delete
                                    class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                    aria-label="Удалить шаг">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                  <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                </svg>
                            </button>

                            @if ($stepData['input_step_name'] !== null)
                                <div class="ml-9 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                      <path fill-rule="evenodd" d="M16 3.75a.75.75 0 0 1-.75.75h-7.5v10.94l1.97-1.97a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0l-3.25-3.25a.75.75 0 1 1 1.06-1.06l1.97 1.97V3.75A.75.75 0 0 1 7 3h8.25a.75.75 0 0 1 .75.75Z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $stepData['input_step_name'] }}</span>
                                </div>
                            @endif

                            <div class="flex items-start gap-3 pr-10">
                                <span class="mt-2 text-gray-500 dark:text-gray-400">
                                    @if ($stepData['step_version']->type === 'text')
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    @elseif ($stepData['step_version']->type === 'transcribe')
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                        </svg>
                                    @endif
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <button type="button"
                                                wire:click="openStepEditModal({{ $stepData['step_version']->id }})"
                                                data-step-edit-open="{{ $stepData['step_version']->id }}"
                                                class="truncate text-left text-lg text-gray-900 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-500">
                                            {{ $stepData['step_version']->name ?? 'Без названия шага' }}
                                        </button>
                                        <span class="text-gray-500 dark:text-gray-400">v{{ $stepData['step_version']->version ?? '—' }}</span>
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full bg-violet-100 px-2 py-1 text-xs text-violet-700 dark:bg-violet-400/10 dark:text-violet-300">
                                            {{ $stepData['model_label'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $stepData['step_version']->description ?: 'Описание не задано.' }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <div class="space-y-3">
                <button type="button"
                        class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Редактировать версию
                </button>
                <button type="button"
                        wire:click="makeSelectedVersionCurrent"
                        data-set-current-version-button
                        data-disabled="{{ $this->selectedVersionIsCurrent || $this->selectedVersionIsArchived ? 'true' : 'false' }}"
                        @disabled($this->selectedVersionIsCurrent || $this->selectedVersionIsArchived)
                        class="w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500">
                    Сделать текущей версией
                </button>
                <button type="button"
                        wire:click="toggleSelectedVersionArchiveStatus"
                        class="w-full rounded-lg px-3 py-2 text-sm font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 dark:shadow-none {{ $this->selectedVersionIsArchived
                            ? 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500'
                            : 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600 dark:bg-red-500 dark:hover:bg-red-400 dark:focus-visible:outline-red-500' }}">
                    {{ $this->selectedVersionIsArchived ? 'Вернуть из архива' : 'Архивировать версию' }}
                </button>
            </div>

            <div class="mt-6 space-y-3">
                @foreach ($this->pipelineVersions as $version)
                    <button type="button"
                            wire:click="selectVersion({{ $version->id }})"
                            data-pipeline-version="{{ $version->id }}"
                            data-active="{{ $selectedVersionId === $version->id ? 'true' : 'false' }}"
                            class="w-full rounded-lg border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:focus-visible:outline-indigo-500 {{ $selectedVersionId === $version->id
                                ? 'border-indigo-500 bg-indigo-50/60 dark:border-indigo-400 dark:bg-indigo-500/20'
                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-white/20' }}">
                        <span data-version-status="{{ $version->status }}"
                              class="flex items-center justify-between gap-2 {{ $version->status === 'archived' ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">
                            <span>Версия {{ $version->version }}</span>
                            <span class="flex items-center gap-1">
                                @if ($version->status === 'archived')
                                    <span data-archived-version-icon>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                        </svg>
                                    </span>
                                @endif
                                @if ((int) $pipeline->current_version_id === (int) $version->id)
                                    <span data-current-version-icon class="text-indigo-600 dark:text-indigo-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                @endif
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        </aside>
    </div>

    @if ($showStepEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-step-edit-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeStepEditModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Редактирование шага</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Измените параметры шага и выберите способ сохранения.</p>
                        </div>

                        <div>
                            <label class="block text-sm/6 font-medium text-gray-900 dark:text-white">Тип шага</label>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <button type="button"
                                        wire:click="setEditStepType('transcribe')"
                                        @disabled(($this->editingStepPosition ?? 0) > 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $editStepType === 'transcribe'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                            : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                    </svg>
                                    Транскрибация
                                </button>
                                <button type="button"
                                        wire:click="setEditStepType('text')"
                                        @disabled(($this->editingStepPosition ?? 0) === 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $editStepType === 'text'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                            : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    Обработка текста
                                </button>
                                <button type="button"
                                        wire:click="setEditStepType('glossary')"
                                        @disabled(($this->editingStepPosition ?? 0) === 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $editStepType === 'glossary'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                            : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    Глоссарий
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="edit-step-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <input id="edit-step-name" type="text" wire:model="editStepName"
                                   class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                        </div>

                        <div>
                            <label for="edit-step-description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Короткое описание</label>
                            <textarea id="edit-step-description" rows="2" wire:model="editStepDescription"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                        </div>

                        <div>
                            <label for="edit-step-input-step" class="flex items-center gap-1 text-sm/6 font-medium text-gray-900 dark:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                  <path fill-rule="evenodd" d="M16 3.75a.75.75 0 0 1-.75.75h-7.5v10.94l1.97-1.97a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0l-3.25-3.25a.75.75 0 1 1 1.06-1.06l1.97 1.97V3.75A.75.75 0 0 1 7 3h8.25a.75.75 0 0 1 .75.75Z" clip-rule="evenodd" />
                                </svg>
                                Шаг-источник
                            </label>
                            <div class="mt-2" wire:replace>
                                <el-select id="edit-step-input-step"
                                           value="{{ (string) ($editStepInputStepId ?? '') }}"
                                           wire:model.live="editStepInputStepId"
                                           class="block">
                                    <button type="button"
                                            class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                        <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedEditStepInputStepLabel }}</el-selectedcontent>
                                        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                             class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                                            <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <el-options anchor="bottom start" popover
                                                class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                        <el-option value=""
                                                   class="group/option relative block cursor-default py-2 pr-4 pl-8 text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden dark:text-white dark:focus:bg-indigo-500">
                                            <span class="block truncate font-normal group-aria-selected/option:font-semibold">Не выбрано</span>
                                        </el-option>
                                        @forelse ($this->stepEditInputStepOptions as $option)
                                            <el-option value="{{ $option['id'] }}"
                                                       class="group/option relative block cursor-default py-2 pr-4 pl-8 text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden dark:text-white dark:focus:bg-indigo-500">
                                                <span class="block truncate font-normal group-aria-selected/option:font-semibold">{{ $option['name'] }}</span>
                                            </el-option>
                                        @empty
                                            <el-option value="" disabled
                                                       class="relative block cursor-not-allowed py-2 px-3 text-gray-500 select-none dark:text-gray-400">
                                                Нет доступных шагов-источников
                                            </el-option>
                                        @endforelse
                                    </el-options>
                                </el-select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="edit-step-model" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Модель</label>
                                <div class="mt-2" wire:replace>
                                    <el-select id="edit-step-model"
                                               value="{{ $editStepModel }}"
                                               wire:model.live="editStepModel"
                                               class="block">
                                        <button type="button"
                                                class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                            <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedEditStepModelLabel }}</el-selectedcontent>
                                            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                                 class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                                                <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                            </svg>
                                        </button>

                                        <el-options anchor="bottom start" popover
                                                    class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                            @foreach ($this->stepEditModelOptions as $option)
                                                <el-option value="{{ $option['id'] }}"
                                                           class="group/option relative block cursor-default py-2 pr-4 pl-8 text-gray-900 select-none focus:bg-indigo-600 focus:text-white focus:outline-hidden dark:text-white dark:focus:bg-indigo-500">
                                                    <span class="block truncate font-normal group-aria-selected/option:font-semibold">{{ $option['label'] }}</span>
                                                </el-option>
                                            @endforeach
                                        </el-options>
                                    </el-select>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label for="edit-step-temperature" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Температура</label>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($editStepTemperature, 1) }}</span>
                                </div>
                                <input id="edit-step-temperature"
                                       type="range"
                                       min="{{ $this->editStepTemperatureConfig['min'] }}"
                                       max="{{ $this->editStepTemperatureConfig['max'] }}"
                                       step="{{ $this->editStepTemperatureConfig['step'] }}"
                                       wire:model.live="editStepTemperature"
                                       @disabled($this->editStepTemperatureConfig['disabled'])
                                       class="mt-4 w-full accent-indigo-600 disabled:cursor-not-allowed disabled:opacity-60 dark:accent-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label for="edit-step-prompt" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Промт</label>
                            <textarea id="edit-step-prompt" rows="25" wire:model="editStepPrompt"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                        </div>

                        <div>
                            <label for="edit-step-changelog" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Описание изменения (Обязательно для новой версии)</label>
                            <textarea id="edit-step-changelog" rows="2" wire:model="editStepChangelogEntry"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                        </div>

                        <div class="mt-8 flex items-stretch justify-between gap-3">
                            <button type="button"
                                    wire:click="closeStepEditModal"
                                    class="inline-flex h-11 items-center justify-center rounded-lg bg-white px-4 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500">
                                Отмена
                            </button>
                            <div class="flex items-stretch gap-3">
                                <button type="button"
                                        wire:click="saveStep"
                                        class="inline-flex h-11 items-center justify-center rounded-lg bg-white px-4 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500">
                                    Сохранить
                                </button>
                                <button type="button"
                                        wire:click="saveStepAsNewVersion"
                                        class="inline-flex h-11 flex-col items-center justify-center rounded-lg bg-indigo-600 px-4 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                                    <span>Сохранить</span>
                                    <span class="text-xs font-medium">новая версия</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
