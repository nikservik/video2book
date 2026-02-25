<div class="space-y-6">
    <div class="mx-2 md:mx-6">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Активность</h1>
    </div>

    @if ($activities->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">
                История действий пока пуста.
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($activities as $activity)
                    <li class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 md:px-6">
                        @if ($activity['customDescription'] !== null)
                            {{ $activity['dateTime'] }} — {{ $activity['customDescription'] }}
                        @else
                            {{ $activity['dateTime'] }}
                            — {{ $activity['userName'] }}
                            — {{ $activity['action'] }}
                            {{ $activity['subjectTypeLabel'] }}
                            «{{ $activity['subjectName'] }}»
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        {{ $activities->links('pagination.twui') }}
    @endif
</div>
