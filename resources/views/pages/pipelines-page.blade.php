<div class="space-y-6">
    <div class="mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Пайплайны</h1>
        <a href="{{ route('pipelines.create') }}"
           wire:navigate
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-2.25-1.313M21 7.5v2.25m0-2.25-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3 2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75 2.25-1.313M12 21.75V19.5m0 2.25-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />
            </svg>
            Добавить пайплайн
        </a>
    </div>

    @if ($pipelines->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">
                Пока нет пайплайнов.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ($pipelines as $pipeline)
                <article class="rounded-lg border border-gray-200 bg-white px-6 py-4 shadow-sm dark:border-white/10 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold text-gray-900 dark:text-white">
                            {{ $pipeline->currentVersion?->title ?? 'Без названия' }}
                        </h2>
                        <span class="shrink-0 text-gray-500 dark:text-gray-400">
                            v{{ $pipeline->currentVersion?->version ?? '-' }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $pipeline->currentVersion?->description ?: 'Описание не задано.' }}
                    </p>
                </article>
            @endforeach
        </div>

        {{ $pipelines->links('pagination.twui') }}
    @endif
</div>
