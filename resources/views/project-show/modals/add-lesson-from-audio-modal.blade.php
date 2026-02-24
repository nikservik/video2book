<div>
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true"
         x-data="{ showUploadErrorNotification: false }"
         data-add-lesson-from-audio-modal>
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

        <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                 x-on:click.stop>
                <form wire:submit="createLessonFromAudio" class="space-y-5">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить урок из аудио</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Укажите название, версию шаблона и загрузите аудиофайл.
                        </p>
                    </div>

                    <div>
                        <label for="audio-lesson-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название урока</label>
                        <div class="mt-2 grid grid-cols-1">
                            @error('newLessonName')
                                <input id="audio-lesson-name" type="text" name="audio_lesson_name" wire:model="newLessonName" aria-invalid="true" aria-describedby="audio-lesson-name-error"
                                       class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-10 pl-3 text-red-900 outline-1 -outline-offset-1 outline-red-300 placeholder:text-red-300 focus:outline-2 focus:-outline-offset-2 focus:outline-red-600 sm:pr-9 sm:text-sm/6 dark:bg-white/5 dark:text-red-400 dark:outline-red-500/50 dark:placeholder:text-red-400/70 dark:focus:outline-red-400">
                                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                     class="pointer-events-none col-start-1 row-start-1 mr-3 size-5 self-center justify-self-end text-red-500 sm:size-4 dark:text-red-400">
                                    <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                </svg>
                            @else
                                <input id="audio-lesson-name" type="text" name="audio_lesson_name" wire:model="newLessonName"
                                       class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                            @enderror
                        </div>
                        @error('newLessonName')
                            <p id="audio-lesson-name-error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="lesson-audio-file" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Аудиофайл</label>
                        <label for="lesson-audio-file"
                               class="mt-2 relative flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-0 text-center hover:border-indigo-400 hover:bg-indigo-50/30 dark:border-white/15 dark:bg-white/5 dark:hover:border-indigo-400/70 dark:hover:bg-indigo-500/10">
                            <input id="lesson-audio-file"
                                   type="file"
                                   wire:model="newLessonAudioFile"
                                   x-on:livewire-upload-start="showUploadErrorNotification = false; $dispatch('project-show:audio-upload-started')"
                                   x-on:livewire-upload-finish="$dispatch('project-show:audio-upload-finished')"
                                   x-on:livewire-upload-cancel="$dispatch('project-show:audio-upload-finished')"
                                   x-on:livewire-upload-error="showUploadErrorNotification = true; $dispatch('project-show:audio-upload-finished')"
                                   accept=".mp3,.wav,.m4a,.aac,.ogg,.oga,.flac,.webm,.mp4,audio/*"
                                   class="absolute inset-0 h-full w-full cursor-pointer opacity-0">

                            <div class="py-20">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    Перетащите аудиофайл сюда или нажмите для выбора
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    MP3, WAV, M4A, AAC, OGG, FLAC, WEBM, MP4
                                </p>
                                @if ($this->selectedAudioFilename !== null)
                                    <p class="mt-3 text-sm text-indigo-700 dark:text-indigo-300">
                                        Выбран файл: {{ $this->selectedAudioFilename }}
                                    </p>
                                @endif
                            </div>
                        </label>

                        <div wire:loading wire:target="newLessonAudioFile" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            Загрузка файла...
                        </div>

                        @error('newLessonAudioFile')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="audio-lesson-pipeline-version" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Версия шаблона</label>
                        <div class="mt-2" wire:replace>
                            <x-pipeline-version-select
                                id="audio-lesson-pipeline-version"
                                name="audio_lesson_pipeline_version"
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

        <div aria-live="assertive" class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6">
            <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
                <div x-show="showUploadErrorNotification"
                     x-transition
                     class="pointer-events-auto w-full max-w-sm translate-y-0 transform rounded-lg bg-white opacity-100 shadow-lg outline-1 outline-black/5 transition duration-300 ease-out sm:translate-x-0 dark:bg-gray-800 dark:-outline-offset-1 dark:outline-white/10">
                    <div class="p-4">
                        <div class="flex items-start">
                            <div class="shrink-0">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6 text-red-500 dark:text-red-400">
                                    <path d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.95 3.374H4.647c-1.733 0-2.816-1.874-1.95-3.374L10.05 3.374c.866-1.5 3.034-1.5 3.9 0l7.353 12.752ZM12 16.5h.008v.008H12V16.5Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="ml-3 w-0 flex-1 pt-0.5">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Не удалось загрузить файл</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Попробуйте выбрать файл ещё раз. Если ошибка повторится, обновите страницу и повторите загрузку.
                                </p>
                                <div class="mt-3 flex items-center gap-2">
                                    <button type="button"
                                            x-on:click="window.location.reload()"
                                            class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
                                        Обновить страницу
                                    </button>
                                    <button type="button"
                                            x-on:click="showUploadErrorNotification = false"
                                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">
                                        Закрыть
                                    </button>
                                </div>
                            </div>
                            <div class="ml-4 flex shrink-0">
                                <button type="button"
                                        x-on:click="showUploadErrorNotification = false"
                                        class="inline-flex rounded-md text-gray-400 hover:text-gray-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:hover:text-white dark:focus:outline-indigo-500">
                                    <span class="sr-only">Закрыть уведомление</span>
                                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5">
                                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endif
</div>
