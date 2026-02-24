<div class="space-y-6">
    <h1 class="mx-2 md:mx-4 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
        {{ $this->selectedVersionTitle }}
        <span class="ml-3 inline-block text-base font-normal tracking-normal text-gray-500 dark:text-gray-400">v{{ $this->selectedVersionNumber }}</span>
    </h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            @if ($selectedVersion === null)
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">У этого шаблона пока нет версий.</p>
                </div>
            @elseif ($selectedVersionSteps === [])
                <div class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <p class="text-gray-600 dark:text-gray-300">В выбранной версии пока нет шагов.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($selectedVersionSteps as $stepData)
                        <article data-pipeline-step="{{ $stepData['step_version']->id }}" class="relative rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-800">
                            @if ((int) $stepData['position'] > 1)
                                <button type="button"
                                        wire:click="$dispatch('pipeline-show:delete-step-alert-open', { stepVersionId: {{ $stepData['step_version']->id }} })"
                                        data-step-delete="{{ $stepData['step_version']->id }}"
                                        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                        aria-label="Удалить шаг">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                      <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                    </svg>
                                </button>
                            @endif

                            @if ($stepData['input_step_name'] !== null)
                                <div class="ml-9 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                      <path fill-rule="evenodd" d="M16 3.75a.75.75 0 0 1-.75.75h-7.5v10.94l1.97-1.97a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0l-3.25-3.25a.75.75 0 1 1 1.06-1.06l1.97 1.97V3.75A.75.75 0 0 1 7 3h8.25a.75.75 0 0 1 .75.75Z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ $stepData['input_step_name'] }}</span>
                                </div>
                            @endif

                            <div class="flex items-start gap-3 pr-10">
                                <div class="mt-1 flex w-6 shrink-0 flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                                    @if ($stepData['step_version']->type === 'text')
                                        <div class="group grid size-4 grid-cols-1">
                                            <input type="checkbox"
                                                   aria-label="Сделать шаг по умолчанию"
                                                   data-step-default-checkbox="{{ $stepData['step_version']->id }}"
                                                   data-checked="{{ $stepData['is_default'] ? 'true' : 'false' }}"
                                                   @checked($stepData['is_default'])
                                                   wire:click="setDefaultTextStep({{ $stepData['step_version']->id }})"
                                                   class="col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:indeterminate:border-indigo-500 dark:indeterminate:bg-indigo-500 dark:focus-visible:outline-indigo-500 dark:disabled:border-white/5 dark:disabled:bg-white/10 dark:disabled:checked:bg-white/10 forced-colors:appearance-auto" />
                                            <svg viewBox="0 0 14 14" fill="none"
                                                 class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-disabled:stroke-gray-950/25 dark:group-has-disabled:stroke-white/25">
                                                <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                      class="opacity-0 group-has-checked:opacity-100" />
                                            </svg>
                                        </div>
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
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <button type="button"
                                                wire:click="$dispatch('pipeline-show:step-edit-modal-open', { stepVersionId: {{ $stepData['step_version']->id }} })"
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

                        <div class="flex items-center py-1" wire:key="step-divider-{{ $stepData['step_version']->id }}">
                            <div aria-hidden="true" class="w-full border-t border-gray-300 dark:border-white/15"></div>
                            <div class="relative flex justify-center">
                                <button type="button"
                                        wire:click="$dispatch('pipeline-show:step-create-modal-open', { afterStepVersionId: {{ $stepData['step_version']->id }} })"
                                        data-step-add-after="{{ $stepData['step_version']->id }}"
                                        class="bg-white px-2 text-gray-500 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-gray-900 dark:text-gray-400 dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-500"
                                        aria-label="Добавить шаг после выбранного">
                                    <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5">
                                        <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                                    </svg>
                                </button>
                            </div>
                            <div aria-hidden="true" class="w-full border-t border-gray-300 dark:border-white/15"></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <div class="space-y-3">
                <button type="button"
                        wire:click="$dispatch('pipeline-show:edit-version-modal-open')"
                        class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    Редактировать версию
                </button>
                <button type="button"
                        wire:click="$dispatch('pipeline-show:changelog-modal-open')"
                        class="w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500">
                    Посмотреть changelog
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
                        wire:click="$dispatch('pipeline-show:duplicate-pipeline-modal-open')"
                        data-open-duplicate-pipeline-modal
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    Создать копию
                </button>
                <button type="button"
                        wire:click="toggleSelectedVersionArchiveStatus"
                        data-archive-version-button
                        data-archive-version-disabled="{{ $this->selectedVersionIsArchived && $this->selectedVersionHasDraftSteps ? 'true' : 'false' }}"
                        @disabled($this->selectedVersionIsArchived && $this->selectedVersionHasDraftSteps)
                        class="w-full rounded-lg px-3 py-2 text-sm font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 dark:shadow-none {{ $this->selectedVersionIsArchived
                            ? 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500'
                            : 'bg-red-600 text-white hover:bg-red-500 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-500 dark:hover:bg-red-400 dark:focus-visible:outline-red-500' }}">
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

    <livewire:pipeline-show.modals.edit-version-modal :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-edit-version-modal-'.$pipeline->id.'-'.$selectedVersionId" />
    <livewire:pipeline-show.modals.changelog-modal :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-changelog-modal-'.$pipeline->id.'-'.$selectedVersionId" />
    <livewire:pipeline-show.modals.duplicate-pipeline-modal :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-duplicate-pipeline-modal-'.$pipeline->id.'-'.$selectedVersionId" />
    <livewire:pipeline-show.modals.step-create-modal :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-step-create-modal-'.$pipeline->id.'-'.$selectedVersionId" />
    <livewire:pipeline-show.modals.step-edit-modal :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-step-edit-modal-'.$pipeline->id.'-'.$selectedVersionId" />
    <livewire:pipeline-show.modals.delete-step-alert :pipeline-id="$pipeline->id" :selected-version-id="$selectedVersionId" :key="'pipeline-show-delete-step-alert-'.$pipeline->id.'-'.$selectedVersionId" />
</div>
