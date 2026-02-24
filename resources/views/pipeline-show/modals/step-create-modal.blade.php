<div>
@if ($show)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-step-create-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="saveCreatedStep" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавление шага</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Заполните параметры нового шага для выбранной версии шаблона.</p>
                        </div>

                        <div>
                            <label class="block text-sm/6 font-medium text-gray-900 dark:text-white">Тип шага</label>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <button type="button"
                                        wire:click="setCreateStepType('transcribe')"
                                        @disabled(($createStepInsertPosition ?? 0) > 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $createStepType === 'transcribe'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                            : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                    </svg>
                                    Транскрибация
                                </button>
                                <button type="button"
                                        wire:click="setCreateStepType('text')"
                                        @disabled(($createStepInsertPosition ?? 0) === 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $createStepType === 'text'
                                            ? 'bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500'
                                            : 'bg-white text-gray-900 inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-gray-600 dark:bg-white/10 dark:text-white dark:inset-ring-white/5 dark:hover:bg-white/20 dark:focus-visible:outline-gray-500' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    Обработка текста
                                </button>
                                <button type="button"
                                        wire:click="setCreateStepType('glossary')"
                                        @disabled(($createStepInsertPosition ?? 0) === 1)
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 font-semibold shadow-xs focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 {{ $createStepType === 'glossary'
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
                            <label for="create-step-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <input id="create-step-name" type="text" wire:model="createStepName"
                                   class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                        </div>

                        <div>
                            <label for="create-step-description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Короткое описание</label>
                            <textarea id="create-step-description" rows="2" wire:model="createStepDescription"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                        </div>

                        <div>
                            <label for="create-step-input-step" class="flex items-center gap-1 text-sm/6 font-medium text-gray-900 dark:text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                                  <path fill-rule="evenodd" d="M16 3.75a.75.75 0 0 1-.75.75h-7.5v10.94l1.97-1.97a.75.75 0 1 1 1.06 1.06l-3.25 3.25a.75.75 0 0 1-1.06 0l-3.25-3.25a.75.75 0 1 1 1.06-1.06l1.97 1.97V3.75A.75.75 0 0 1 7 3h8.25a.75.75 0 0 1 .75.75Z" clip-rule="evenodd" />
                                </svg>
                                Шаг-источник
                            </label>
                            <div class="mt-2" wire:replace>
                                <el-select id="create-step-input-step"
                                           value="{{ (string) ($createStepInputStepId ?? '') }}"
                                           wire:model.live="createStepInputStepId"
                                           class="block">
                                    <button type="button"
                                            data-create-source-disabled="{{ $createStepType === 'transcribe' ? 'true' : 'false' }}"
                                            @disabled($createStepType === 'transcribe')
                                            class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                        <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedCreateStepInputStepLabel }}</el-selectedcontent>
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
                                        @forelse ($this->createStepInputStepOptions as $option)
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
                                <label for="create-step-model" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Модель</label>
                                <div class="mt-2" wire:replace>
                                    <el-select id="create-step-model"
                                               value="{{ $createStepModel }}"
                                               wire:model.live="createStepModel"
                                               class="block">
                                        <button type="button"
                                                class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500">
                                            <el-selectedcontent class="col-start-1 row-start-1 truncate pr-6">{{ $this->selectedCreateStepModelLabel }}</el-selectedcontent>
                                            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"
                                                 class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400">
                                                <path d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z" clip-rule="evenodd" fill-rule="evenodd"/>
                                            </svg>
                                        </button>

                                        <el-options anchor="bottom start" popover
                                                    class="max-h-60 w-(--button-width) overflow-auto rounded-md bg-white py-1 shadow-lg outline-1 outline-black/5 [--anchor-gap:--spacing(1)] data-leave:transition data-leave:transition-discrete data-leave:duration-100 data-leave:ease-in data-closed:data-leave:opacity-0 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10">
                                            @foreach ($this->createStepModelOptions as $option)
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
                                <label for="create-step-temperature" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Температура</label>
                                <div class="mt-2 flex items-center gap-3">
                                    <input id="create-step-temperature"
                                           type="range"
                                           min="{{ $this->createStepTemperatureConfig['min'] }}"
                                           max="{{ $this->createStepTemperatureConfig['max'] }}"
                                           step="{{ $this->createStepTemperatureConfig['step'] }}"
                                           @disabled($this->createStepTemperatureConfig['disabled'])
                                           wire:model.live="createStepTemperature"
                                           class="h-2 w-full cursor-pointer rounded-lg bg-gray-200 accent-indigo-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/10 dark:accent-indigo-500">
                                    <span class="w-12 text-right text-sm text-gray-600 dark:text-gray-300">{{ number_format($createStepTemperature, 1) }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="create-step-prompt" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Промт</label>
                            <textarea id="create-step-prompt" rows="25" wire:model="createStepPrompt"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
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
