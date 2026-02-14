<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-200">
        {{ $pipelineRun->lesson?->name ?? 'Урок' }}
        <span class="md:ml-3 inline-block tracking-normal text-lg font-normal text-gray-500 dark:text-gray-400">{{ $this->pipelineVersionLabel }}</span>
    </h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-lg border border-gray-200 bg-white px-4 py-6 shadow-sm lg:col-span-2 dark:border-white/10 dark:bg-gray-800">
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Скачать PDF
                </button>
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Скачать MD
                </button>
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:bg-amber-500 dark:shadow-none dark:hover:bg-amber-400 dark:focus-visible:outline-amber-500">
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

            <div class="rounded-lg bg-indigo-50 px-6 py-4 dark:bg-gray-900/50">
                @if ($resultViewMode === 'preview')
                    <div data-selected-step-id="{{ $this->selectedStep?->id ?? '' }}"
                         data-selected-step-result
                         data-result-mode="preview"
                         class="text-gray-700 dark:text-gray-200 [&_h1]:mb-3 [&_h1]:text-4xl [&_h1]:font-semibold [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:my-3 [&_ol_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-2 [&_ul]:my-3 [&_ul_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-6">
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

        <aside class="lg:col-span-1">
            <div class="space-y-3">
                @forelse ($pipelineRun->steps as $step)
                    <button type="button"
                            wire:click="selectStep({{ $step->id }})"
                            data-run-step="{{ $step->id }}"
                            data-active="{{ $selectedStepId === $step->id ? 'true' : 'false' }}"
                            class="w-full rounded-lg border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:focus-visible:outline-indigo-500 {{ $selectedStepId === $step->id
                                ? 'border-indigo-500 bg-indigo-50/60 dark:border-indigo-400 dark:bg-indigo-500/20'
                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-white/20' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-gray-900 dark:text-white">
                                {{ $step->stepVersion?->name ?? 'Без названия шага' }}
                            </span>
                            <span class="{{ $this->stepStatusBadgeClass($step->status) }}">
                                {{ $this->stepStatusLabel($step->status) }}
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="{{ $this->tokenMetricsBadgeClass() }}">i:{{ $this->formatTokens($step->input_tokens) }}</span>
                            <span class="{{ $this->tokenMetricsBadgeClass() }}">o:{{ $this->formatTokens($step->output_tokens) }}</span>
                            <span class="{{ $this->costMetricsBadgeClass() }}">${{ $this->formatCost($step->cost) }}</span>
                        </div>
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
