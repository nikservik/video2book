<div>
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-project-export-modal>
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

        <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                 wire:click.stop>
                <form wire:submit="downloadProjectResults" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $this->projectExportTitle }}</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Выберите версию пайплайна и шаг, для которого хотите скачать результаты.
                        </p>
                    </div>

                    @if ($projectExportPipelineOptions === [])
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                            Нет доступных обработанных прогонов для скачивания.
                        </div>
                    @else
                        <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                            @foreach ($projectExportPipelineOptions as $pipelineOption)
                                <details class="rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5"
                                         @if($this->isProjectExportPipelineExpanded($pipelineOption['id'])) open @endif>
                                    <summary class="cursor-pointer list-none px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                        <div class="flex items-center justify-between gap-3">
                                            <span>{{ $pipelineOption['label'] }}</span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Шагов: {{ count($pipelineOption['steps']) }}</span>
                                        </div>
                                    </summary>
                                    <div class="space-y-2 px-4 pb-4">
                                        @foreach ($pipelineOption['steps'] as $stepOption)
                                            <label for="project-export-step-{{ $pipelineOption['id'] }}-{{ $stepOption['id'] }}"
                                                   class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10">
                                                <input id="project-export-step-{{ $pipelineOption['id'] }}-{{ $stepOption['id'] }}"
                                                       type="radio"
                                                       name="project_export_step"
                                                       wire:model.live="projectExportSelection"
                                                       value="{{ $pipelineOption['id'] }}:{{ $stepOption['id'] }}"
                                                       class="size-4 border-gray-300 text-indigo-600 focus:ring-indigo-600 dark:border-white/20 dark:bg-transparent dark:focus:ring-indigo-500">
                                                <span>{{ $stepOption['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm/6 font-medium text-gray-900 dark:text-white">Именование файлов в архиве</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <button type="button"
                                    wire:click="setProjectExportArchiveFileNaming('lesson')"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 {{ $projectExportArchiveFileNaming === 'lesson'
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                        : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                Урок.{{ $this->projectExportFileExtension }}
                            </button>
                            <button type="button"
                                    wire:click="setProjectExportArchiveFileNaming('lesson_step')"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 {{ $projectExportArchiveFileNaming === 'lesson_step'
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                        : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                Урок - шаг.{{ $this->projectExportFileExtension }}
                            </button>
                        </div>
                    </div>

                    @error('projectExportSelection')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('projectExportArchiveFileNaming')
                        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-10 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                                @disabled($projectExportPipelineOptions === [])
                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-300 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:disabled:bg-indigo-500/60">
                            Скачать
                        </button>
                        <button type="button" wire:click="close"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                            Отменить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
</div>
