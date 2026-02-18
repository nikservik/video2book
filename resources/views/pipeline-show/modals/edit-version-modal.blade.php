<div>
    @if ($show)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-edit-version-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form wire:submit="saveVersion" class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Редактировать версию</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Обновите название и описание выбранной версии.</p>
                        </div>

                        <div>
                            <label for="pipeline-version-title" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <input id="pipeline-version-title" type="text" wire:model="editableVersionTitle"
                                   class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                            @error('editableVersionTitle')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="pipeline-version-description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Описание</label>
                            <textarea id="pipeline-version-description" rows="4" wire:model="editableVersionDescription"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                            @error('editableVersionDescription')
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
