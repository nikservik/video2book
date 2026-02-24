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
                            <x-pipeline-version-select
                                id="adding-pipeline-version"
                                name="adding_pipeline_version"
                                :value="$addingPipelineVersionId"
                                wire-model="addingPipelineVersionId"
                                :selected-label="$this->selectedAddingPipelineVersionLabel"
                                :options="$this->addPipelineVersionOptions"
                                no-options-label="Все версии уже добавлены"
                            />
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
