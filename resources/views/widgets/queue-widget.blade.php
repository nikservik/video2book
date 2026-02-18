<div wire:poll.2s class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $widget['title'] }}</h2>

    @if ($widget['items'] === [])
        <p class="mt-3 text-gray-600 dark:text-gray-300">Очередь сейчас пуста.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($visibleItems as $task)
                <li wire:key="queue-task-{{ $task['task_key'] }}" class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-900/40">
                    <div class="flex items-start gap-3" data-queue-task>
                        <div class="shrink-0 {{ $task['icon_color_class'] }}">
                            @if ($task['type'] === 'pipeline')
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                            @endif
                        </div>

                        <button type="button"
                                wire:click="toggleTask('{{ $task['task_key'] }}')"
                                class="min-w-0 flex-1 text-left">
                            <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $task['lesson_name'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $task['pipeline_label'] }}</p>
                        </button>

                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $task['steps_progress'] }}</span>
                    </div>

                    @if (in_array($task['task_key'], $expandedTaskKeys, true))
                        <div class="mt-2 pl-9">
                            @if ($task['type'] === 'pipeline')
                                @if (($task['steps'] ?? []) === [])
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Шаги не найдены.</p>
                                @else
                                    <ul class="space-y-1.5">
                                        @foreach ($task['steps'] as $step)
                                            <li class="flex items-center justify-between gap-2">
                                                <span class="truncate text-sm text-gray-700 dark:text-gray-200">{{ $step['name'] }}</span>
                                                <span class="{{ $step['status_badge_class'] }}">{{ $step['status_label'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @else
                                <div>
                                    <div class="mb-1 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>Прогресс скачивания</span>
                                        <span>{{ $task['progress_label'] ?? '0%' }}</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-white/10">
                                        <div class="h-2 rounded-full bg-indigo-600 dark:bg-indigo-500"
                                             style="width: {{ $task['progress_width'] ?? '0%' }}"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($hiddenItemsCount > 0)
            <p class="mt-3 text-center text-sm text-gray-500 dark:text-gray-400">
                Ещё {{ $hiddenItemsCount }} задач
            </p>
        @endif
    @endif
</div>
