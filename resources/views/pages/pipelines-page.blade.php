<div class="space-y-6">
    <h1 class="mx-6 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Пайплайны</h1>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
        <p class="text-gray-600 dark:text-gray-300">
            Страница пайплайнов. Этот раздел будет содержать список пайплайнов и переходы к шагам.
        </p>

        <a href="{{ route('pipelines.steps.show', ['pipeline' => 'demo-pipeline', 'step' => 'demo-step']) }}"
           wire:navigate
           class="mt-4 inline-flex items-center font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
            Открыть демо-шаг (проверка активного меню "Пайплайны")
        </a>
    </div>
</div>
