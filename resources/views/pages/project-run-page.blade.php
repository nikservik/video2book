<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
        Прогон #{{ $pipelineRun->id }}
    </h1>

    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
        <div class="space-y-2">
            <p class="text-gray-900 dark:text-white">
                Пайплайн: <span class="font-medium">{{ $pipelineRun->pipelineVersion?->title ?? 'Без названия' }}</span>
                • v{{ $pipelineRun->pipelineVersion?->version ?? '—' }}
            </p>
            <p class="text-gray-900 dark:text-white">
                Статус: <span class="font-medium">{{ $pipelineRun->status }}</span>
            </p>
        </div>
    </section>
</div>
