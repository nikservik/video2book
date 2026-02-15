<div>
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-add-pipeline-to-lesson-modal>
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

        <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                 wire:click.stop>
                <form wire:submit="addPipelineToLesson" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить версию пайплайна</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Для урока «{{ $addingPipelineLessonName }}» выберите версию пайплайна.
                        </p>
                    </div>

                    <div>
                        <label for="adding-pipeline-version" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Версия пайплайна</label>
                        <div class="mt-2" wire:replace>
                            <el-select id="adding-pipeline-version"
                                       name="adding_pipeline_version"
                                       value="{{ (string) ($addingPipelineVersionId ?? '') }}"
                                       wire:model.live="addingPipelineVersionId"
                                       class="block">
                                <button type="button"
                                        class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                    <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedAddingPipelineVersionLabel }}</el-selectedcontent>
                                    <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                         class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                                        <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                    </svg>
                                </button>

                                <el-options anchor="bottom start" popover
                                            class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                    @forelse ($this->addPipelineVersionOptions as $option)
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
                                            Все версии уже добавлены
                                        </el-option>
                                    @endforelse
                                </el-options>
                            </el-select>
                        </div>
                        @error('addingPipelineVersionId')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-10 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                                @disabled($this->addPipelineVersionOptions === [])
                                class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-300 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:disabled:bg-indigo-500/60">
                            Сохранить
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
