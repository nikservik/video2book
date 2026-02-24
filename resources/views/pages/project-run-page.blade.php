<div class="space-y-6"
     x-data="{ isActionsMenuOpen: false }"
     x-on:keydown.escape.window="isActionsMenuOpen = false">
    <div class="mx-2 md:mx-4 flex items-start justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-200">
            {{ $pipelineRun->lesson?->name ?? 'Урок' }}
            <span class="md:ml-3 inline-block tracking-normal text-lg font-normal text-gray-500 dark:text-gray-400">{{ $this->pipelineVersionLabel }}</span>
        </h1>

        <div wire:poll.1s="refreshRunControls" class="flex shrink-0 items-center gap-2">
            @if ($this->hasPausedSteps || $this->hasFailedSteps)
                <button type="button"
                        wire:click="startRun"
                        data-run-control="start"
                        class="inline-flex size-10 items-center justify-center rounded-lg bg-green-600 text-white shadow-xs hover:bg-green-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 dark:bg-green-500 dark:shadow-none dark:hover:bg-green-400 dark:focus-visible:outline-green-500"
                        aria-label="Старт">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                      <path fill-rule="evenodd" d="M4.5 5.653c0-1.427 1.529-2.33 2.779-1.643l11.54 6.347c1.295.712 1.295 2.573 0 3.286L7.28 19.99c-1.25.687-2.779-.217-2.779-1.643V5.653Z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif

            @if (! $this->hasFailedSteps && $this->hasQueuedSteps)
                <button type="button"
                        wire:click="pauseRun"
                        data-run-control="pause"
                        class="inline-flex size-10 items-center justify-center rounded-lg bg-yellow-500 text-white shadow-xs hover:bg-yellow-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-600 dark:bg-yellow-500 dark:shadow-none dark:hover:bg-yellow-400 dark:focus-visible:outline-yellow-500"
                        aria-label="Пауза">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                      <path fill-rule="evenodd" d="M6.75 5.25a.75.75 0 0 1 .75-.75H9a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H7.5a.75.75 0 0 1-.75-.75V5.25Zm7.5 0A.75.75 0 0 1 15 4.5h1.5a.75.75 0 0 1 .75.75v13.5a.75.75 0 0 1-.75.75H15a.75.75 0 0 1-.75-.75V5.25Z" clip-rule="evenodd" />
                    </svg>
                </button>

            @endif

            @if (! $this->hasFailedSteps && ($this->hasQueuedSteps || $this->hasRunningSteps))
                <button type="button"
                        wire:click="stopRun"
                        data-run-control="stop"
                        class="inline-flex size-10 items-center justify-center rounded-lg bg-red-600 text-white shadow-xs hover:bg-red-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400 dark:focus-visible:outline-red-500"
                        aria-label="Стоп">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                      <path fill-rule="evenodd" d="M4.5 7.5a3 3 0 0 1 3-3h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <div class="relative md:hidden"
         x-data="{ isMobileRunStepsMenuOpen: false }"
         x-on:click.outside="isMobileRunStepsMenuOpen = false"
        data-mobile-run-steps-dropdown>
        @if ($pipelineRun->steps->isNotEmpty())
            <div class="relative">
                <button type="button"
                        x-on:click="isMobileRunStepsMenuOpen = !isMobileRunStepsMenuOpen"
                        x-bind:aria-expanded="isMobileRunStepsMenuOpen ? 'true' : 'false'"
                        class="w-full rounded-lg border border-gray-200 bg-white p-3 text-left transition hover:border-gray-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-gray-800 dark:hover:border-white/20 dark:focus-visible:outline-indigo-500"
                        data-mobile-run-steps-toggle>
                    <div class="flex items-center justify-between gap-3">
                        <span class="truncate text-gray-900 dark:text-white">
                            @if ($this->isZeroAccessLevelUser && $this->selectedStepNumber !== null)
                                Шаг {{ $this->selectedStepNumber }}.
                            @endif
                            {{ $this->selectedStep?->stepVersion?->name ?? 'Без названия шага' }}
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="{{ $this->stepStatusBadgeClass($this->selectedStep?->status) }}">
                                {{ $this->stepStatusLabel($this->selectedStep?->status) }}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 20 20"
                                 fill="currentColor"
                                 class="size-5 text-gray-400 transition-transform dark:text-gray-500"
                                 x-bind:class="{ 'rotate-180': isMobileRunStepsMenuOpen }">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>

                    @unless ($this->isZeroAccessLevelUser)
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="{{ $this->tokenMetricsBadgeClass() }}">i:{{ $this->formatTokens($this->selectedStep?->input_tokens) }}</span>
                            <span class="{{ $this->tokenMetricsBadgeClass() }}">o:{{ $this->formatTokens($this->selectedStep?->output_tokens) }}</span>
                            <span class="{{ $this->costMetricsBadgeClass() }}">${{ $this->formatCost($this->selectedStep?->cost) }}</span>
                        </div>
                    @endunless
                </button>

                <div x-show="isMobileRunStepsMenuOpen"
                     x-transition
                     style="display: none;"
                     class="absolute left-0 top-0 z-20 w-full space-y-3 bg-gray-100 dark:bg-gray-900 rounded-lg"
                     data-mobile-run-steps-list>
                    @foreach ($pipelineRun->steps as $step)
                        <button type="button"
                                x-on:click="isMobileRunStepsMenuOpen = false"
                                wire:click="selectStep({{ $step->id }})"
                                data-run-step-mobile="{{ $step->id }}"
                                data-active="{{ $selectedStepId === $step->id ? 'true' : 'false' }}"
                                class="w-full rounded-lg border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:focus-visible:outline-indigo-500 {{ $selectedStepId === $step->id
                                    ? 'border-indigo-500 bg-indigo-100 dark:border-indigo-400 dark:bg-indigo-900'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-white/20' }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-gray-900 dark:text-white">
                                    @if ($this->isZeroAccessLevelUser)
                                        Шаг {{ $loop->iteration }}.
                                    @endif
                                    {{ $step->stepVersion?->name ?? 'Без названия шага' }}
                                </span>
                                <span>
                                    <span class="{{ $this->stepStatusBadgeClass($step->status) }}">
                                        {{ $this->stepStatusLabel($step->status) }}
                                    </span>
                                </span>
                            </div>

                            @unless ($this->isZeroAccessLevelUser)
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="{{ $this->tokenMetricsBadgeClass() }}">i:{{ $this->formatTokens($step->input_tokens) }}</span>
                                    <span class="{{ $this->tokenMetricsBadgeClass() }}">o:{{ $this->formatTokens($step->output_tokens) }}</span>
                                    <span class="{{ $this->costMetricsBadgeClass() }}">${{ $this->formatCost($step->cost) }}</span>
                                </div>
                            @endunless
                        </button>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 dark:border-white/15">
                <p class="text-gray-600 dark:text-gray-300">В этом прогоне пока нет шагов.</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-6 shadow-sm md:col-span-2 dark:border-white/10 dark:bg-gray-800">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <button type="button"
                        wire:click="downloadSelectedStepPdf"
                        @disabled(! $this->canExportSelectedStep)
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    PDF
                </button>
                <button type="button"
                        wire:click="downloadSelectedStepMarkdown"
                        @disabled(! $this->canExportSelectedStep)
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    MD
                </button>
                <button type="button"
                        wire:click="downloadSelectedStepDocx"
                        @disabled(! $this->canExportSelectedStep)
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    DOCX
                </button>
                <button type="button"
                        wire:click="restartSelectedStep"
                        @disabled(! $this->canRestartSelectedStep)
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-amber-500 dark:shadow-none dark:hover:bg-amber-400 dark:focus-visible:outline-amber-500"
                        data-run-restart-step>
                    Перезапуск шага
                </button>
                <span class="ml-auto isolate inline-flex rounded-lg shadow-xs dark:shadow-none">
                    <button type="button"
                            wire:click="setResultViewMode('preview')"
                            data-result-view="preview"
                            data-active="{{ $resultViewMode === 'preview' ? 'true' : 'false' }}"
                            class="relative inline-flex items-center rounded-l-lg px-3 py-2 text-sm font-semibold    focus:z-10  dark:text-white  {{ $resultViewMode === 'preview'
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-700 bg-white  hover:bg-white/70 dark:text-gray-200 dark:bg-white/10 dark:hover:bg-white/20' }}">
                        Превью
                    </button>
                    <button type="button"
                            wire:click="setResultViewMode('source')"
                            data-result-view="source"
                            data-active="{{ $resultViewMode === 'source' ? 'true' : 'false' }}"
                            class="relative -ml-px inline-flex items-center rounded-r-lg px-3 py-2 text-sm font-semibold  focus:z-10  dark:text-white {{ $resultViewMode === 'source'
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-700 bg-white  hover:bg-white/70 dark:text-gray-200 dark:bg-white/10 dark:hover:bg-white/20' }}">
                        Исходник
                    </button>
                </span>
            </div>

            <div class="rounded-lg bg-indigo-50 -mx-4 md:mx-0 px-4 md:px-6 py-4 dark:bg-gray-900/50"
                 @if ($this->shouldPollSelectedStepResult) wire:poll.1s="refreshSelectedStepResult" @endif>
                @if ($resultViewMode === 'preview')
                    <div data-selected-step-id="{{ $this->selectedStep?->id ?? '' }}"
                         data-selected-step-result
                         data-result-mode="preview"
                         class="text-gray-700 dark:text-gray-200 [&_h1]:mb-3 [&_h1]:text-2xl [&_h1]:md:text-4xl [&_h1]:font-semibold [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:md:text-2xl [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:my-3 [&_ol_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:my-3 [&_ul_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-6">
                        {!! $this->selectedStepResultPreview !!}
                    </div>
                @else
                    <pre data-selected-step-id="{{ $this->selectedStep?->id ?? '' }}"
                         data-selected-step-result
                         data-result-mode="source"
                         class="whitespace-pre-wrap text-base text-gray-700 dark:text-gray-200">{{ $this->selectedStepResult }}</pre>
                @endif
            </div>
        </section>

        <aside class="hidden md:block md:col-span-1" @if ($this->hasUnfinishedSteps) wire:poll.2s="refreshRunSteps" @endif>
            <div class="space-y-3">
                @forelse ($pipelineRun->steps as $step)
                    @php
                        $hasDesktopPointer = $this->isZeroAccessLevelUser && ! $loop->last;
                        $isActiveStep = $selectedStepId === $step->id;
                    @endphp

                    <button type="button"
                            wire:click="selectStep({{ $step->id }})"
                            data-run-step="{{ $step->id }}"
                            data-active="{{ $selectedStepId === $step->id ? 'true' : 'false' }}"
                            data-run-step-pointer="{{ $hasDesktopPointer ? 'true' : 'false' }}"
                            class="w-full rounded-lg border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:focus-visible:outline-indigo-500 {{ $selectedStepId === $step->id
                                ? 'border-indigo-500 bg-indigo-100 dark:border-indigo-400 dark:bg-indigo-900'
                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-white/20' }} {{ $hasDesktopPointer
                                ? 'relative overflow-visible before:content-[\'\'] before:absolute before:left-1/2 before:top-full before:-translate-x-1/2 before:border-x-[11px] before:border-x-transparent before:border-t-[11px] after:content-[\'\'] after:absolute after:left-1/2 after:top-full after:-translate-x-1/2 after:-mt-px after:border-x-[10px] after:border-x-transparent after:border-t-[10px]'
                                : '' }} {{ $hasDesktopPointer
                                ? ($isActiveStep
                                    ? 'before:border-t-indigo-500 dark:before:border-t-indigo-400 after:border-t-indigo-100 dark:after:border-t-indigo-900'
                                    : 'before:border-t-gray-200 dark:before:border-t-white/10 after:border-t-white dark:after:border-t-gray-800')
                                : '' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-gray-900 dark:text-white">
                                @if ($this->isZeroAccessLevelUser)
                                    Шаг {{ $loop->iteration }}.
                                @endif
                                {{ $step->stepVersion?->name ?? 'Без названия шага' }}
                            </span>
                            <span class="{{ $this->stepStatusBadgeClass($step->status) }}">
                                {{ $this->stepStatusLabel($step->status) }}
                            </span>
                        </div>

                        @unless ($this->isZeroAccessLevelUser)
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="{{ $this->tokenMetricsBadgeClass() }}">i:{{ $this->formatTokens($step->input_tokens) }}</span>
                                <span class="{{ $this->tokenMetricsBadgeClass() }}">o:{{ $this->formatTokens($step->output_tokens) }}</span>
                                <span class="{{ $this->costMetricsBadgeClass() }}">${{ $this->formatCost($step->cost) }}</span>
                            </div>
                        @endunless
                    </button>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 dark:border-white/15">
                        <p class="text-gray-600 dark:text-gray-300">В этом прогоне пока нет шагов.</p>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</div>
