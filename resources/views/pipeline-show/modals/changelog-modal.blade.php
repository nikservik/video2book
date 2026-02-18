<div>
    @if ($show)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-version-changelog-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="close"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <div class="space-y-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Changelog версии v{{ $this->selectedVersionNumber }}</h3>
                        </div>

                        <div class="max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                            <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">{{ $this->selectedVersionChangelog }}</pre>
                        </div>

                        <div class="sm:flex sm:flex-row-reverse">
                            <button type="button" wire:click="close"
                                    class="inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Закрыть
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
