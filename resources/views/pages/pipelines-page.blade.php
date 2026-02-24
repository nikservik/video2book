<div class="space-y-6">
    <div class="mx-2 md:mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Шаблоны</h1>
        <button type="button"
                wire:click="openCreatePipelineModal"
                data-open-create-pipeline-modal
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-2.25-1.313M21 7.5v2.25m0-2.25-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3 2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75 2.25-1.313M12 21.75V19.5m0 2.25-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />
            </svg>
            Добавить шаблон
        </button>
    </div>

    @if ($pipelines->isEmpty())
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-300">
                Пока нет шаблонов.
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-3">
            @foreach ($pipelines as $pipeline)
                <a href="{{ route('pipelines.show', $pipeline) }}" wire:navigate class="group block">
                    <article class="rounded-lg border border-gray-200 bg-white px-4 md:px-6 py-4 shadow-sm transition group-hover:border-indigo-400 dark:border-white/10 dark:bg-gray-800 dark:group-hover:border-indigo-500/60">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-semibold {{ $pipeline->currentVersion?->status === 'archived'
                                ? 'text-gray-500 dark:text-gray-400'
                                : 'text-gray-900 dark:text-white' }}">
                                {{ $pipeline->currentVersion?->title ?? 'Без названия' }}
                            </h2>
                            @if ($pipeline->currentVersion?->status === 'archived')
                                <span data-archived-current-version-icon class="shrink-0 text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                    </svg>
                                </span>
                            @else
                                <span class="shrink-0 text-gray-500 dark:text-gray-400">
                                    v{{ $pipeline->currentVersion?->version ?? '-' }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-sm text-gray-600 dark:text-gray-300">
                            {{ $pipeline->currentVersion?->description ?: 'Описание не задано.' }}
                        </p>
                    </article>
                </a>
            @endforeach
        </div>

        {{ $pipelines->links('pagination.twui') }}
    @endif

    @if ($showCreatePipelineModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" data-create-pipeline-modal>
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50" wire:click="closeCreatePipelineModal"></div>

            <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
                     wire:click.stop>
                    <form
                        x-data="{
                            title: @js($createPipelineTitle),
                            description: @js($createPipelineDescription),
                            steps: (@js($createPipelineStepNames)).map((name, index) => ({ id: index + 1, name })),
                            nextStepId: @js(count($createPipelineStepNames) + 1),
                            addStep(name = '') {
                                this.steps.push({ id: this.nextStepId++, name })
                            },
                            removeStep(stepId) {
                                this.steps = this.steps.filter((step) => step.id !== stepId)
                            },
                            submit() {
                                const orderedStepIds = Array
                                    .from(this.$refs.stepsList.querySelectorAll('[data-step-id]'))
                                    .map((element) => Number(element.dataset.stepId))
                                const stepsById = new Map(this.steps.map((step) => [step.id, step]))
                                const orderedStepNames = orderedStepIds
                                    .map((stepId) => stepsById.get(stepId))
                                    .filter((step) => step !== undefined)
                                    .map((step) => step.name)

                                $wire.savePipeline(
                                    this.title,
                                    this.description,
                                    orderedStepNames,
                                )
                            },
                        }"
                        x-on:submit.prevent="submit()"
                        class="space-y-5"
                    >
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Добавить шаблон</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Заполните название, описание и настройте список шагов.</p>
                        </div>

                        <div>
                            <label for="create-pipeline-title" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Название</label>
                            <input id="create-pipeline-title" type="text" x-model="title"
                                   class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">
                            @error('createPipelineTitle')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="create-pipeline-description" class="block text-sm/6 font-medium text-gray-900 dark:text-white">Описание</label>
                            <textarea id="create-pipeline-description" rows="4" x-model="description"
                                      class="mt-2 block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"></textarea>
                            @error('createPipelineDescription')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <ul x-ref="stepsList" x-sort class="divide-y divide-gray-200 dark:divide-white/10">
                                    <template x-for="step in steps" :key="step.id">
                                        <li x-sort:item="step.id" :data-step-id="step.id" class="flex items-center gap-3 px-3 py-2">
                                            <span x-sort:handle
                                                  class="shrink-0 cursor-grab text-gray-500 hover:text-gray-700 active:cursor-grabbing dark:text-gray-400 dark:hover:text-gray-200"
                                                  aria-label="Перетащить шаг">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </span>

                                            <input type="text"
                                                   x-model="step.name"
                                                   class="block w-full rounded-md bg-white px-3 py-1.5 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500">

                                            <button type="button"
                                                    x-on:click="removeStep(step.id)"
                                                    class="shrink-0 text-gray-500 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600 dark:text-gray-400 dark:hover:text-gray-200 dark:focus-visible:outline-gray-500"
                                                    aria-label="Удалить шаг">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                                                    <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z" />
                                                </svg>
                                            </button>
                                        </li>
                                    </template>
                                </ul>

                                <div class="border-t border-gray-200 px-3 py-2 dark:border-white/10">
                                    <button type="button"
                                            x-on:click="addStep()"
                                            class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-gray-700 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white dark:focus-visible:outline-indigo-500">
                                        <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5">
                                            <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                                        </svg>
                                        <span>Добавить шаг</span>
                                    </button>
                                </div>
                            </div>

                            @error('stepNames')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            @error('stepNames.*')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-10 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400">
                                Сохранить
                            </button>
                            <button type="button" wire:click="closeCreatePipelineModal"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20">
                                Отменить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
