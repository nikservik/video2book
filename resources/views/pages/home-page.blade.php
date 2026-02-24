<div class="space-y-6">
    <h1 class="mx-2 md:mx-4 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Главная</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <h2 class="mx-2 md:mx-4 text-lg font-semibold text-gray-900 dark:text-white">Свежие проекты</h2>

            @if ($recentProjects->isEmpty())
                <p class="mt-4 text-gray-600 dark:text-gray-300">
                    Пока нет проектов. Создайте первый проект, чтобы увидеть его в этом списке.
                </p>
            @else
                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($recentProjects as $project)
                                    <tr wire:key="home-project-{{ $project->id }}"
                                        onclick="window.location.href='{{ route('projects.show', $project) }}'"
                                        class="cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5">
                                        <td class="px-2 py-3 text-xs text-gray-500 md:px-4 md:text-sm dark:text-gray-400">
                                            <span class="block truncate">
                                                {{ $project->folder?->name ?? 'Проекты' }}
                                            </span>
                                        </td>
                                        <td class="w-full max-w-0 px-3 py-3 text-sm font-medium text-gray-900 md:w-auto md:max-w-none dark:text-white">
                                            <p class="truncate">{{ $project->name }}</p>
                                            <p class="font-normal md:hidden mt-1 leading-tight">
                                                <span class="truncate inline-block mr-2 text-gray-500 dark:text-gray-400">
                                                    Уроков: {{ $project->lessons_count }}
                                                </span>
                                                <span class="truncate inline-block text-gray-500 dark:text-gray-400">
                                                    Длительность: {{ $this->projectDurationLabel($project->settings) }}
                                                </span>
                                            </p>
                                        </td>
                                        <td class="hidden px-4 py-3 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                                            Уроков: {{ $project->lessons_count }}
                                        </td>
                                        <td class="hidden px-4 py-3 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                                            {{ $this->projectDurationLabel($project->settings) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <livewire:widgets.queue-widget />
        </aside>
    </div>
</div>
