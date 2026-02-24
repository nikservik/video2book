@props([
    'hiddenProperty',
    'visibleForProperty',
    'users' => [],
    'lockedUserIds' => [],
    'idPrefix' => 'folder-visibility',
])

<div class="space-y-4">
    <div class="flex items-start gap-3">
        <div class="flex h-6 shrink-0 items-center">
            <div class="group grid size-4 grid-cols-1">
                <input id="{{ $idPrefix }}-hidden"
                       type="checkbox"
                       wire:model.live="{{ $hiddenProperty }}"
                       class="col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:indeterminate:border-indigo-500 dark:indeterminate:bg-indigo-500 dark:focus-visible:outline-indigo-500 forced-colors:appearance-auto">
                <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white">
                    <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked:opacity-100"/>
                    <path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-indeterminate:opacity-100"/>
                </svg>
            </div>
        </div>
        <div class="text-sm/6">
            <label for="{{ $idPrefix }}-hidden" class="font-medium text-gray-900 dark:text-white">Скрыть папку</label>
        </div>
    </div>
    @error($hiddenProperty)
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if (data_get($this, $hiddenProperty))
        <div class="space-y-2">
            <p class="text-sm/6 font-medium text-gray-900 dark:text-white">Кому папка видна</p>
            <div class="max-h-56 overflow-y-auto rounded-md border border-gray-200 divide-y divide-gray-200 dark:border-white/10 dark:divide-white/10">
                @foreach ($users as $user)
                    @php
                        $userId = (int) data_get($user, 'id');
                        $isLocked = in_array($userId, $lockedUserIds, true);
                    @endphp
                    <label for="{{ $idPrefix }}-user-{{ $userId }}" class="flex cursor-pointer items-center gap-3 px-3 py-2">
                        <span class="flex h-6 shrink-0 items-center">
                            <span class="group grid size-4 grid-cols-1">
                                <input id="{{ $idPrefix }}-user-{{ $userId }}"
                                       type="checkbox"
                                       value="{{ $userId }}"
                                       wire:model.live="{{ $visibleForProperty }}"
                                       @disabled($isLocked)
                                       class="col-start-1 row-start-1 appearance-none rounded-sm border border-gray-300 bg-white checked:border-indigo-600 checked:bg-indigo-600 indeterminate:border-indigo-600 indeterminate:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:checked:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:indeterminate:border-indigo-500 dark:indeterminate:bg-indigo-500 dark:focus-visible:outline-indigo-500 dark:disabled:border-white/5 dark:disabled:bg-white/10 dark:disabled:checked:bg-white/10 forced-colors:appearance-auto">
                                <svg viewBox="0 0 14 14" fill="none" class="pointer-events-none col-start-1 row-start-1 size-3.5 self-center justify-self-center stroke-white group-has-disabled:stroke-gray-950/25 dark:group-has-disabled:stroke-white/25">
                                    <path d="M3 8L6 11L11 3.5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-checked:opacity-100"/>
                                    <path d="M3 7H11" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-0 group-has-indeterminate:opacity-100"/>
                                </svg>
                            </span>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ data_get($user, 'name') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error($visibleForProperty)
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error($visibleForProperty.'.*')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif
</div>
