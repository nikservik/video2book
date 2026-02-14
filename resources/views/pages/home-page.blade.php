<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Главная</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800 lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Последние измененные проекты</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">5 последних</span>
            </div>

            @if ($recentProjects->isEmpty())
                <p class="text-gray-600 dark:text-gray-300">
                    Пока нет проектов. Создайте первый проект, чтобы увидеть его в этом списке.
                </p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($recentProjects as $project)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between gap-4">
                                <p class="truncate font-medium text-gray-900 dark:text-white">{{ $project->name }}</p>
                                <time datetime="{{ $project->updated_at?->toIso8601String() }}"
                                      class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $project->updated_at?->format('d.m.Y H:i') }}
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <livewire:widgets.development-queue-widget />
        </aside>
    </div>
</div>
