<div>
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-create-lesson-modal>
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

        <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                 wire:click.stop>
                <form wire:submit="createLessonFromYoutube" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить урок</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Заполните название, ссылку на YouTube и версию шаблона.
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
                        <label for="lesson-pipeline-version" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Версия шаблона</label>
                        <div class="mt-2" wire:replace>
                            <x-pipeline-version-select
                                id="lesson-pipeline-version"
                                name="lesson_pipeline_version"
                                :value="$newLessonPipelineVersionId"
                                wire-model="newLessonPipelineVersionId"
                                :selected-label="$this->selectedPipelineVersionLabel"
                                :options="$pipelineVersionOptions"
                            />
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
