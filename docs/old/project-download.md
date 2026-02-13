# Скачивание обработанного проекта

Мне нужен механизм, который позволит скачать результаты обработки шагов пайплайна для всех уроков проекта.


1) В ProjectView нужно добавить кнопку «Скачать»
- На кнопке сначала стоит иконка скачивания, а потом надпись «Скачать»
- Кнопка расположена слева от кнопки «+ Добавить урок»
- При нажатии открывается модал из пункта 2

2) Модал скачивания проекта
- Шаблон кода:
```html
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
                <div>
                  <div class="mt-3 text-center sm:mt-5">
                    <DialogTitle as="h3" class="text-base font-semibold text-gray-900 dark:text-white">Скачивание файлов проекта</DialogTitle>
                    <div class="mt-2">
                        ...

                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                  <button type="button" class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500" @click="open = false">Отмена</button>
                  <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20" @click="open = false" ref="cancelButtonRef">Скачать</button>
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
import { CheckIcon } from '@heroicons/vue/24/outline'

const open = ref(true)
</script>
```
- В модале нужно показать список всех версий пайплайнов, использованных в уроках проекта. Если версия пайплайна использовалась хотя бы в одном уроке – показываем
- При клике на версию пайплайна выпадает список шагов этого пайплайна. В этом списке показываем шаги только с типом text.
- Одновременно может быть открыт список шагов только у одного пайплайна.
- Каждый шаг - это radio
- Шаблон для отображения списка шагов:
```html
<template>
  <fieldset aria-label="Plan">
    <div class="space-y-5">
      <div v-for="plan in plans" :key="plan.id" class="relative flex items-start">
        <div class="flex h-6 items-center">
          <input :id="plan.id" :aria-describedby="`${plan.id}-description`" name="plan" type="radio" :checked="plan.id === 'small'" class="relative size-4 appearance-none rounded-full border border-gray-300 bg-white before:absolute before:inset-1 before:rounded-full before:bg-white not-checked:before:hidden checked:border-indigo-600 checked:bg-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:border-gray-300 disabled:bg-gray-100 disabled:before:bg-gray-400 dark:border-white/10 dark:bg-white/5 dark:checked:border-indigo-500 dark:checked:bg-indigo-500 dark:focus-visible:outline-indigo-500 dark:disabled:border-white/5 dark:disabled:bg-white/10 dark:disabled:before:bg-white/20 forced-colors:appearance-auto forced-colors:before:hidden" />
        </div>
        <div class="ml-3 text-sm/6">
          <label :for="plan.id" class="font-medium text-gray-900 dark:text-white">{{ plan.name }}</label>
        </div>
      </div>
    </div>
  </fieldset>
</template>

<script setup>
const plans = [
  { id: 'small', name: 'Small' },
  { id: 'medium', name: 'Medium' },
  { id: 'large', name: 'Large' },
]
</script>
```
- Выбран может быть только один шаг из всех пайплайнов
- Ниже выбор формата скачивания: pdf/md
- Выбор формата сделай кнопками как кнопки скачивания в уроке. Активная подсвечена цветом, неактивная – с серым фоном.
- Пользователь должен выбрать шаг и формат скачивания.

3) Скачивание
- Можно использовать механику скачивания результатов отдельных шагов.
- После нажатия на кнопку скачать открывается системный диалог для выбора папки, куда сохранять файлы.
- Для каждого урока сохраняем отдельный файл с именем: {Название урока} - {Название шага}.pdf/md
- Если у урока нет выбранной версии пайплана или выбранный шаг не обработан/с ошибкой, то файл для него не сохраняем
