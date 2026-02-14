<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Проекты</h1>

    @if ($projects->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">
                Пока нет проектов.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ($projects as $project)
                <a href="{{ route('projects.show', $project) }}" wire:navigate class="group block">
                    <article class="rounded-lg border border-gray-200 bg-white px-6 py-4 shadow-sm transition group-hover:border-indigo-400 dark:border-white/10 dark:bg-gray-800 dark:group-hover:border-indigo-500/60">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $project->name }}</h2>
                        <div class="mt-1 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p>
                                Уроков: {{ $project->lessons_count }}
                            </p>
                        </div>
                    </article>
                </a>
            @endforeach
        </div>

        {{ $projects->links('pagination.twui') }}
    @endif
</div>
