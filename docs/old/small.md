# Мелкие исправления

## [x] Обновление UI для PipelineDetailView

1) Названия шагов становятся активными элементами. Клик на название переключает на редактирование шага.

2) Отдельную иконку редактирования шага меняем на иконку удаления шага.
    - При клике на иконку удаления открываем окно для подтверждения удаления:
    ```vue
    <template>
    <div>
        <button class="rounded-md bg-gray-950/5 px-2.5 py-1.5 text-sm font-semibold text-gray-900 hover:bg-gray-950/10 dark:bg-white/10 dark:text-white dark:inset-ring dark:inset-ring-white/5 dark:hover:bg-white/20" @click="open = true">Open dialog</button>
        <TransitionRoot as="template" :show="open">
        <Dialog class="relative z-10" @close="open = false">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="" leave="ease-in duration-200" leave-from="" leave-to="opacity-0">
            <div class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50"></div>
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" enter-to=" translate-y-0 sm:scale-100" leave="ease-in duration-200" leave-from=" translate-y-0 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10">
                    <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10 dark:bg-red-500/10">
                        <ExclamationTriangleIcon class="size-6 text-red-600 dark:text-red-400" aria-hidden="true" />
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <DialogTitle as="h3" class="text-base font-semibold text-gray-900 dark:text-white">Удалить шаг?</DialogTitle>
                        <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">При удалении шага будет создана новая версия пайплайна</p>
                        </div>
                    </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                    <button type="button" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto dark:bg-red-500 dark:shadow-none dark:hover:bg-red-400" @click="open = false">Удалить</button>
                    <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20" @click="open = false" ref="cancelButtonRef">Отмена</button>
                    </div>
                </DialogPanel>
                </TransitionChild>
            </div>
            </div>
        </Dialog>
        </TransitionRoot>
    </div>
    </template>

    <script setup>
    import { ref } from 'vue'
    import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
    import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

    const open = ref(true)
    </script>
    ```
    - При удалении мы просто создаем новую версию пайплайна и для этой версии в последовательности шагов исключаем выбранный шаг.

## [x] PipelineRunView: Иконка для кнопки «Перезапустить с этого шага»

- Кнопку «Перезапустить с этого шага» замени на желтую кнопку с иконкой без надписи
- Код для отрисовки иконки возьми отсюда:
```html
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right-from-line-icon lucide-arrow-right-from-line"><path d="M3 5v14"/><path d="M21 12H7"/><path d="m15 18 6-6-6-6"/></svg>
```
- Если пайплайн обрабатывается, то кнопка не активна во всех шагах

## [x] PipelineRunView: Скачивание результатов в pdf

1) На фронтенде
- Добавь кнопку для скачивания результатов шага в виде pdf рядом с кнопкой перезапуска.
- На кнопке должна быть иконка и надпись "pdf"
- Код для иконки:
```html
```<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-down-icon lucide-file-down"><path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
- После нажатия кнопки скачивания должен открыться диалог сохранения файла
- Название файла формируй так: {Название урока} - {Название шага пайплайна}.pdf

2) На бекенде
- Преобразовывай результаты шага в html с помощью league/commonmark (уже установлен)
- PDF формируй с помощью elibyy/tcpdf-laravel (уже установлен)
- Проконтролируй оформление html, потому что elibyy/tcpdf-laravel воспринимает ограниченные стили
- у нас в текстах используются:
    - заголовки
    - нумерованные и ненумерованные много уровневые списки
    - italic, bold, underline

## [x] PipelineRunView: Скачивание результатов в md

1) На фронтенде
- Добавь кнопку с иконкой скачивания и надписью "md"
- После нажатия кнопки скачивания должен открыться диалог сохранения файла
- Название файла формируй так: {Название урока} - {Название шага пайплайна}.md

2) На бекенде
- В файл сохраняй результаты обработки шага без каких-либо преобразований