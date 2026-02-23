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
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
                    @foreach ($recentProjects as $project)
                        <a href="{{ route('projects.show', $project) }}" wire:navigate class="group block">
                            <article class="rounded-lg border border-gray-200 bg-white px-4 py-4 shadow-sm transition group-hover:border-indigo-400 dark:border-white/10 dark:bg-gray-800 dark:group-hover:border-indigo-500/60 md:px-6">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $project->name }}</h3>
                                <div class="mt-1 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                    <p>Уроков: {{ $project->lessons_count }}</p>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="lg:col-span-1">
            <livewire:widgets.queue-widget />
        </aside>
    </div>
</div>
