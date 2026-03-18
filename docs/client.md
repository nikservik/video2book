# UI-инструкция для Electron + Vue клиента

Документ нужен для того, чтобы Vue-разработчик без догадок и свободных трактовок собрал урезанный Electron-клиент с тем же визуальным языком, что и исходное веб-приложение.

Этот документ автономный. Его можно копировать в отдельный репозиторий клиента без правок на пути, файлы и внутреннюю структуру другого проекта.

Ниже даны готовые куски кода, которые можно копировать почти без изменений.

## Что именно надо повторить

- Повторяем только визуальную часть текущего интерфейса.
- В клиенте оставляем только 2 view:
  - страница со списком проектов по папкам;
  - страница проекта со списком уроков.
- Никаких новых дизайнерских решений не добавлять.
- Никаких библиотек UI не добавлять.
- Использовать только Vue 3 + `<script setup lang="ts">` + Tailwind CSS v4.
- Не использовать `@tailwindplus/elements`, Livewire, Alpine, Headless UI и любые готовые select/dropdown-компоненты.
- Если в исходном приложении элемент сделан через Tailwind Plus или Livewire, в клиенте его надо повторить обычным Vue-кодом, но с теми же классами и внешним видом.

## Что надо убрать по сравнению с текущим приложением

### Шапка

- Оставить только:
  - иконку проекта;
  - переключатель светлой и тёмной темы;
  - иконку шестерёнки.
- Верхнее меню разделов убрать полностью.

### Breadcrumbs

- Оставить полностью.
- Домик слева оставить.
- Разделители-chevron оставить.
- На странице проектов breadcrumb: `Главная -> Проекты`.
- На странице проекта breadcrumb: `Главная -> Проекты -> {Название проекта}`.
- В этом клиенте `Главная` должна вести на `/projects`, потому что других view нет.

### Страница со списком проектов

- Заголовок `Проекты` убрать.
- Кнопку `Добавить папку` убрать.
- Список папок оставить в том же контейнере, с теми же отступами, теми же цветами и тем же accordion-поведением.
- У открытой папки убрать:
  - кнопку `Изменить`;
  - кнопку `Добавить проект`.
- Проекты внутри открытой папки оставить таблицей с тем же фоном строк и теми же метриками.
- Drag and drop полностью убрать.
- Иконку для перетаскивания убрать.
- Чтобы геометрия таблицы не “поехала”, сохранить пустую первую колонку той же ширины, но без иконки.

### Страница проекта

- Заголовок проекта оставить.
- Подпись с длительностью проекта оставить.
- Layout с левой колонкой уроков и правой колонкой действий оставить.
- На mobile оставить ту же кнопку-гамбургер для открытия панели действий.
- В правой колонке оставить только 4 действия:
  - сортировка с иконкой и dropdown;
  - `Добавить урок`;
  - `Добавить урок из аудио`;
  - `Добавить список уроков` с иконкой.
- Из правой колонки убрать:
  - `Редактировать проект`;
  - `Пересчитать длительность`;
  - весь блок экспорта;
  - `Удалить проект`.
- В строке урока оставить:
  - название урока;
  - кнопку перехода на исходник;
  - иконку загрузки аудио;
  - длительность аудио;
  - список статусов шаблонов.
- Из строки урока убрать:
  - кнопку редактирования урока;
  - кнопку удаления урока;
  - кнопку добавления шаблона.
- У клиента только 2 view, поэтому карточки шаблонов должны быть read-only. Не надо делать переход на страницу прогона. Внешне они должны выглядеть как текущие карточки шаблонов, но без навигации.

## Обязательная структура файлов

Создать в клиенте именно такие файлы:

```text
src/
  App.vue
  main.ts
  assets/
    favicon.png
    main.css
  composables/
    useTheme.ts
  data/
    mockUi.ts
  router/
    index.ts
  types/
    ui.ts
  components/
    ui/
      AppIcon.vue
      AppHeader.vue
      AppBreadcrumbs.vue
      AppShell.vue
      BaseDialog.vue
    project/
      LessonsSortDropdown.vue
      PipelineVersionDropdown.vue
      ProjectActionsPanel.vue
      ProjectLessonsList.vue
      modals/
        CreateLessonModal.vue
        AddLessonFromAudioModal.vue
        AddLessonsListModal.vue
    projects/
      ProjectsFoldersList.vue
  views/
    ProjectsView.vue
    ProjectView.vue
```

## Обязательные визуальные правила

- Максимальная ширина контента: `max-w-7xl`.
- Горизонтальные отступы shell:
  - `px-4` на mobile;
  - `sm:px-6`;
  - `lg:px-8`.
- Высота шапки: `h-16`.
- Главный фон приложения:
  - светлая тема: `bg-gray-100`;
  - тёмная тема: `dark:bg-gray-900`.
- Основные белые карточки:
  - `rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800`.
- Основная акцентная кнопка:
  - `bg-indigo-600 text-white`;
  - hover `hover:bg-indigo-500`;
  - в dark `dark:bg-indigo-500 dark:hover:bg-indigo-400`.
- Вторичная кнопка:
  - `bg-white text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50`;
  - в dark `dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20`.
- Никаких своих цветов кроме тех, что уже используются в текущем приложении.
- Никаких новых скруглений. Использовать `rounded-lg`, `rounded-md`, `rounded-full` ровно там, где это уже есть в текущем UI.

## Скопировать asset логотипа

Скопировать PNG-логотип из исходного приложения в клиент:

```text
src/assets/favicon.png
```

Не перерисовывать его и не заменять на другой. Использовать тот же исходный файл, что и в веб-приложении.

## 1. Глобальные стили

Создать файл `src/assets/main.css`:

```css
@import "tailwindcss";

@custom-variant dark (&:where(.dark, .dark *));

@theme {
  --font-sans: "Instrument Sans", ui-sans-serif, system-ui, sans-serif,
    "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol",
    "Noto Color Emoji";
}

html {
  @apply h-full bg-gray-100;
}

html.dark {
  @apply bg-gray-900;
}

body,
#app {
  @apply h-full bg-gray-100 font-sans text-gray-900 dark:bg-gray-900 dark:text-gray-100;
}
```

## 2. Типы данных

Создать файл `src/types/ui.ts`:

```ts
export type ThemeMode = "light" | "dark";

export interface BreadcrumbItem {
  label: string;
  to?: string;
  current?: boolean;
}

export interface FolderProjectItem {
  id: number;
  name: string;
  lessonsCount: number;
  durationLabel: string;
  to: string;
}

export interface FolderItem {
  id: number;
  name: string;
  projectsCount: number;
  isOpen: boolean;
  isHidden?: boolean;
  projects: FolderProjectItem[];
}

export type LessonSortValue = "created_at" | "name";

export interface LessonSortOption {
  value: LessonSortValue;
  label: string;
}

export interface PipelineVersionOption {
  id: number;
  label: string;
  description?: string;
}

export interface YoutubeDuplicateLessonWarning {
  projectName: string;
  lessonName: string;
  projectTo: string;
}

export type LessonAudioStatus = "queued" | "running" | "loaded" | "failed";

export type PipelineRunStatus =
  | "done"
  | "queued"
  | "running"
  | "paused"
  | "failed"
  | "unknown";

export interface LessonPipelineRunItem {
  id: number;
  title: string;
  versionLabel: string;
  status: PipelineRunStatus;
}

export interface LessonItem {
  id: number;
  name: string;
  createdAtOrder: number;
  sourceUrl?: string;
  audioStatus: LessonAudioStatus;
  audioDurationLabel?: string;
  audioErrorTooltip?: string;
  pipelineRuns: LessonPipelineRunItem[];
}

export interface ProjectDetails {
  id: number;
  name: string;
  durationLabel?: string;
  lessons: LessonItem[];
}
```

## 3. Тема: только та же логика, что в текущем приложении

Создать файл `src/composables/useTheme.ts`:

```ts
import { computed, shallowRef } from "vue";
import type { ThemeMode } from "../types/ui";

const THEME_KEY = "video2book:theme";
const theme = shallowRef<ThemeMode>("light");

function readStoredTheme(): ThemeMode | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const storedTheme = window.localStorage.getItem(THEME_KEY);

    if (storedTheme === "light" || storedTheme === "dark") {
      return storedTheme;
    }
  } catch {
    return null;
  }

  return null;
}

function readSystemTheme(): ThemeMode {
  if (
    typeof window !== "undefined" &&
    window.matchMedia &&
    window.matchMedia("(prefers-color-scheme: dark)").matches
  ) {
    return "dark";
  }

  return "light";
}

export function applyTheme(nextTheme: ThemeMode, persist = true): void {
  theme.value = nextTheme;

  if (typeof document !== "undefined") {
    document.documentElement.classList.toggle("dark", nextTheme === "dark");
  }

  if (!persist || typeof window === "undefined") {
    return;
  }

  try {
    window.localStorage.setItem(THEME_KEY, nextTheme);
  } catch {
    // Игнорируем ошибки localStorage в Electron sandbox.
  }
}

export function initializeTheme(): void {
  applyTheme(readStoredTheme() ?? readSystemTheme(), false);
}

export function useTheme() {
  const isDark = computed(() => theme.value === "dark");

  function setLightTheme(): void {
    applyTheme("light");
  }

  function setDarkTheme(): void {
    applyTheme("dark");
  }

  return {
    theme,
    isDark,
    setLightTheme,
    setDarkTheme,
  };
}
```

## 4. Общий набор SVG-иконок

Создать файл `src/components/ui/AppIcon.vue`:

```vue
<script setup lang="ts">
export type AppIconName =
  | "sun"
  | "moon"
  | "settings"
  | "home"
  | "chevron-right"
  | "menu"
  | "close"
  | "folder-open"
  | "folder-closed"
  | "folder-hidden"
  | "external-link"
  | "audio-download"
  | "status-done"
  | "status-queued"
  | "status-running"
  | "status-paused"
  | "status-failed"
  | "status-unknown"
  | "list-plus"
  | "check"
  | "alert-triangle"
  | "sort";

const props = withDefaults(
  defineProps<{
    name: AppIconName;
    class?: string;
  }>(),
  {
    class: "size-5",
  },
);
</script>

<template>
  <svg
    v-if="props.name === 'sun'"
    :class="props.class"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    aria-hidden="true"
  >
    <path
      d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364-1.06-1.06M6.697 6.697 5.636 5.636m12.728 0-1.06 1.06M6.697 17.303l-1.06 1.06M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>

  <svg
    v-else-if="props.name === 'moon'"
    :class="props.class"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    aria-hidden="true"
  >
    <path
      d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.599.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>

  <svg
    v-else-if="props.name === 'settings'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z"
    />
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
    />
  </svg>

  <svg
    v-else-if="props.name === 'home'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5"
    />
  </svg>

  <svg
    v-else-if="props.name === 'chevron-right'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="m9 5.25 7.5 6.75-7.5 6.75"
    />
  </svg>

  <svg
    v-else-if="props.name === 'menu'"
    :class="props.class"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    aria-hidden="true"
  >
    <path
      d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>

  <svg
    v-else-if="props.name === 'close'"
    :class="props.class"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    aria-hidden="true"
  >
    <path
      d="M6 18 18 6M6 6l12 12"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>

  <svg
    v-else-if="props.name === 'folder-open'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776"
    />
  </svg>

  <svg
    v-else-if="props.name === 'folder-closed'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"
    />
  </svg>

  <svg
    v-else-if="props.name === 'folder-hidden'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"
    />
  </svg>

  <svg
    v-else-if="props.name === 'external-link'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M4.25 5.5a.75.75 0 0 0-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75v-4a.75.75 0 0 1 1.5 0v4A2.25 2.25 0 0 1 12.75 17h-8.5A2.25 2.25 0 0 1 2 14.75v-8.5A2.25 2.25 0 0 1 4.25 4h5a.75.75 0 0 1 0 1.5h-5Z"
      clip-rule="evenodd"
    />
    <path
      fill-rule="evenodd"
      d="M6.194 12.753a.75.75 0 0 0 1.06.053L16.5 4.44v2.81a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.553l-9.056 8.194a.75.75 0 0 0-.053 1.06Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'audio-download'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M5.5 17a4.5 4.5 0 0 1-1.44-8.765 4.5 4.5 0 0 1 8.302-3.046 3.5 3.5 0 0 1 4.504 4.272A4 4 0 0 1 15 17H5.5Zm5.25-9.25a.75.75 0 0 0-1.5 0v4.59l-1.95-2.1a.75.75 0 1 0-1.1 1.02l3.25 3.5a.75.75 0 0 0 1.1 0l3.25-3.5a.75.75 0 1 0-1.1-1.02l-1.95 2.1V7.75Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-done'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-queued'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M1 8a7 7 0 1 1 14 0A7 7 0 0 1 1 8Zm7.75-4.25a.75.75 0 0 0-1.5 0V8c0 .414.336.75.75.75h3.25a.75.75 0 0 0 0-1.5h-2.5v-3.5Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-running'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M12.78 7.595a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06l2.72-2.72-2.72-2.72a.75.75 0 0 1 1.06-1.06l3.25 3.25Zm-8.25-3.25 3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.75.75 0 0 1-1.06-1.06l2.72-2.72-2.72-2.72a.75.75 0 0 1 1.06-1.06Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-paused'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      d="M4.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1ZM10.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5h-1Z"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-failed'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'status-unknown'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 16 16"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      fill-rule="evenodd"
      d="M15 8A7 7 0 1 1 1 8a7 7 0 0 1 14 0Zm-6 3.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM7.293 5.293a1 1 0 1 1 .99 1.667c-.459.134-1.033.566-1.033 1.29v.25a.75.75 0 1 0 1.5 0v-.115a2.5 2.5 0 1 0-2.518-4.153.75.75 0 1 0 1.061 1.06Z"
      clip-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'list-plus'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"
    />
  </svg>

  <svg
    v-else-if="props.name === 'check'"
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="currentColor"
    aria-hidden="true"
  >
    <path
      d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
      clip-rule="evenodd"
      fill-rule="evenodd"
    />
  </svg>

  <svg
    v-else-if="props.name === 'alert-triangle'"
    :class="props.class"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.5"
    aria-hidden="true"
  >
    <path
      d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.95 3.374H4.647c-1.733 0-2.816-1.874-1.95-3.374L10.05 3.374c.866-1.5 3.034-1.5 3.9 0l7.353 12.752ZM12 16.5h.008v.008H12V16.5Z"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>

  <svg
    v-else
    :class="props.class"
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    stroke-width="1.5"
    stroke="currentColor"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="m16.5 6-4.5-4.5L7.5 6m9 12 4.5-4.5L16.5 9m-9 9-4.5-4.5L7.5 9M12 3v18"
    />
  </svg>
</template>
```

## 5. Header без верхнего меню

Создать файл `src/components/ui/AppHeader.vue`:

```vue
<script setup lang="ts">
import appLogo from "../../assets/favicon.png";
import { useTheme } from "../../composables/useTheme";
import AppIcon from "./AppIcon.vue";

const emit = defineEmits<{
  (event: "settings"): void;
}>();

const { theme, setLightTheme, setDarkTheme } = useTheme();
</script>

<template>
  <nav class="border-b border-gray-200 bg-white dark:border-white/10 dark:bg-gray-800/50">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 justify-between">
        <div class="flex">
          <div class="flex shrink-0 items-center">
            <img :src="appLogo" alt="Video2Book" class="h-8 w-auto" />
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div
            class="inline-flex items-center gap-0.5 rounded-full bg-gray-900/5 p-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"
            role="group"
            aria-label="Theme switcher"
          >
            <button
              type="button"
              :aria-pressed="theme === 'light'"
              class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500"
              :class="theme === 'light' ? 'bg-white shadow-sm' : ''"
              @click="setLightTheme"
            >
              <span class="sr-only">Switch to light theme</span>
              <AppIcon name="sun" class="size-5" />
            </button>

            <button
              type="button"
              :aria-pressed="theme === 'dark'"
              class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500"
              :class="theme === 'dark' ? 'bg-gray-200 shadow-sm dark:bg-gray-600' : ''"
              @click="setDarkTheme"
            >
              <span class="sr-only">Switch to dark theme</span>
              <AppIcon name="moon" class="size-5" />
            </button>
          </div>

          <button
            type="button"
            class="relative rounded-full p-1 text-gray-500 hover:text-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-300 dark:hover:text-white dark:focus:outline-indigo-500"
            @click="emit('settings')"
          >
            <span class="absolute -inset-1.5"></span>
            <span class="sr-only">Open settings</span>
            <AppIcon name="settings" class="size-6" />
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>
```

## 6. Breadcrumbs

Создать файл `src/components/ui/AppBreadcrumbs.vue`:

```vue
<script setup lang="ts">
import { RouterLink } from "vue-router";
import type { BreadcrumbItem } from "../../types/ui";
import AppIcon from "./AppIcon.vue";

defineProps<{
  items: BreadcrumbItem[];
}>();
</script>

<template>
  <nav aria-label="Breadcrumb" class="py-4 flex">
    <ol
      role="list"
      class="flex flex-wrap items-center gap-x-3 gap-y-2 pl-7 text-gray-500 dark:text-gray-400 md:flex-nowrap md:gap-y-0 md:pl-0"
    >
      <li class="-ml-7 shrink-0 md:ml-0">
        <RouterLink
          to="/projects"
          class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          <AppIcon name="home" class="size-4 shrink-0" />
          <span class="sr-only">Главная</span>
        </RouterLink>
      </li>

      <li
        v-for="item in items"
        :key="`${item.label}-${item.to ?? 'current'}`"
        class="flex min-w-0 max-w-full flex-nowrap items-center gap-3 text-sm"
      >
        <AppIcon
          name="chevron-right"
          class="size-4 shrink-0 text-gray-400 dark:text-gray-500"
        />

        <span
          v-if="item.current || !item.to"
          class="max-w-full font-medium text-gray-700 dark:text-gray-200"
          :aria-current="item.current ? 'page' : undefined"
        >
          {{ item.label }}
        </span>

        <RouterLink
          v-else
          :to="item.to"
          class="max-w-full font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          {{ item.label }}
        </RouterLink>
      </li>
    </ol>
  </nav>
</template>
```

## 7. Общий shell

Создать файл `src/components/ui/AppShell.vue`:

```vue
<script setup lang="ts">
import type { BreadcrumbItem } from "../../types/ui";
import AppBreadcrumbs from "./AppBreadcrumbs.vue";
import AppHeader from "./AppHeader.vue";

defineProps<{
  breadcrumbs: BreadcrumbItem[];
}>();

const emit = defineEmits<{
  (event: "settings"): void;
}>();
</script>

<template>
  <div class="min-h-full">
    <AppHeader @settings="emit('settings')" />

    <div class="pb-10">
      <header>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <AppBreadcrumbs :items="breadcrumbs" />
        </div>
      </header>

      <main>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
```

## 8. Dropdown сортировки без Tailwind Plus

Это замена текущему `el-select` из Laravel-приложения. В клиенте нужен обычный Vue dropdown, но по виду максимально близкий.

Создать файл `src/components/project/LessonsSortDropdown.vue`:

```vue
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, shallowRef, useTemplateRef } from "vue";
import type { LessonSortOption, LessonSortValue } from "../../types/ui";
import AppIcon from "../ui/AppIcon.vue";

const props = defineProps<{
  modelValue: LessonSortValue;
  options: LessonSortOption[];
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: LessonSortValue): void;
}>();

const isOpen = shallowRef(false);
const dropdownRoot = useTemplateRef<HTMLDivElement>("dropdownRoot");

const selectedLabel = computed(() => {
  return (
    props.options.find((option) => option.value === props.modelValue)?.label ??
    "Сортировка по дате добавления"
  );
});

function choose(value: LessonSortValue): void {
  emit("update:modelValue", value);
  isOpen.value = false;
}

function toggleDropdown(): void {
  isOpen.value = !isOpen.value;
}

function handleDocumentClick(event: MouseEvent): void {
  if (!dropdownRoot.value) {
    return;
  }

  if (dropdownRoot.value.contains(event.target as Node)) {
    return;
  }

  isOpen.value = false;
}

onMounted(() => {
  document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
  <div ref="dropdownRoot" class="relative">
    <button
      type="button"
      class="w-full text-sm font-semibold inline-flex items-center gap-2 rounded-lg bg-white py-2 pr-2 pl-3 text-left text-gray-900 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/10 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
      :aria-expanded="isOpen ? 'true' : 'false'"
      @click="toggleDropdown"
    >
      <AppIcon name="sort" class="size-5 shrink-0 text-gray-500 dark:text-gray-400" />
      <span class="min-w-0 flex-1 truncate">
        {{ selectedLabel }}
      </span>
      <svg
        viewBox="0 0 16 16"
        fill="currentColor"
        aria-hidden="true"
        class="size-4 shrink-0 text-gray-500 dark:text-gray-400"
      >
        <path
          d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z"
          clip-rule="evenodd"
          fill-rule="evenodd"
        />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="absolute left-0 z-30 mt-1 w-full overflow-auto rounded-lg bg-white shadow-lg outline-1 outline-black/5 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10"
    >
      <button
        v-for="option in props.options"
        :key="option.value"
        type="button"
        class="block w-full cursor-default px-3 py-2 text-left text-sm text-gray-900 select-none dark:text-white"
        :class="
          option.value === props.modelValue
            ? 'bg-indigo-50 font-semibold dark:bg-indigo-500/20'
            : 'hover:bg-indigo-50 dark:hover:bg-indigo-500/20'
        "
        @click="choose(option.value)"
      >
        <span class="block truncate">{{ option.label }}</span>
      </button>
    </div>
  </div>
</template>
```

## 8.1. Базовый modal shell

Создать файл `src/components/ui/BaseDialog.vue`:

```vue
<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    open: boolean;
    maxWidthClass?: string;
  }>(),
  {
    maxWidthClass: "sm:max-w-lg",
  },
);

const emit = defineEmits<{
  (event: "close"): void;
}>();
</script>

<template>
  <div v-if="props.open" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div
      class="fixed inset-0 bg-gray-500/75 transition-opacity dark:bg-gray-900/50"
      @click="emit('close')"
    />

    <div tabindex="0" class="flex min-h-full items-end justify-center p-4 text-center focus:outline-none sm:items-center sm:p-0">
      <div
        class="relative w-full transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:p-6 dark:bg-gray-800 dark:outline dark:-outline-offset-1 dark:outline-white/10"
        :class="props.maxWidthClass"
        @click.stop
      >
        <slot />
      </div>
    </div>

    <slot name="after" />
  </div>
</template>
```

## 8.2. Dropdown выбора версии шаблона для модалок

Создать файл `src/components/project/PipelineVersionDropdown.vue`:

```vue
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, shallowRef, useTemplateRef } from "vue";
import type { PipelineVersionOption } from "../../types/ui";
import AppIcon from "../ui/AppIcon.vue";

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    options: PipelineVersionOption[];
    placeholder?: string;
  }>(),
  {
    placeholder: "Выберите версию",
  },
);

const emit = defineEmits<{
  (event: "update:modelValue", value: number | null): void;
}>();

const isOpen = shallowRef(false);
const dropdownRoot = useTemplateRef<HTMLDivElement>("dropdownRoot");

const selectedOption = computed(() => {
  return props.options.find((option) => option.id === props.modelValue) ?? null;
});

const selectedLabel = computed(() => {
  return selectedOption.value?.label ?? props.placeholder;
});

function toggleDropdown(): void {
  isOpen.value = !isOpen.value;
}

function choose(optionId: number): void {
  emit("update:modelValue", optionId);
  isOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
  if (!dropdownRoot.value) {
    return;
  }

  if (dropdownRoot.value.contains(event.target as Node)) {
    return;
  }

  isOpen.value = false;
}

onMounted(() => {
  document.addEventListener("click", handleDocumentClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleDocumentClick);
});
</script>

<template>
  <div ref="dropdownRoot" class="relative">
    <button
      type="button"
      class="grid w-full cursor-default grid-cols-1 rounded-md bg-white py-1.5 pr-2 pl-3 text-left text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:focus-visible:outline-indigo-500"
      :aria-expanded="isOpen ? 'true' : 'false'"
      @click="toggleDropdown"
    >
      <span class="col-start-1 row-start-1 truncate pr-6">
        {{ selectedLabel }}
      </span>
      <svg
        viewBox="0 0 16 16"
        fill="currentColor"
        aria-hidden="true"
        class="col-start-1 row-start-1 size-5 self-center justify-self-end text-gray-500 sm:size-4 dark:text-gray-400"
      >
        <path
          d="M5.22 10.22a.75.75 0 0 1 1.06 0L8 11.94l1.72-1.72a.75.75 0 1 1 1.06 1.06l-2.25 2.25a.75.75 0 0 1-1.06 0l-2.25-2.25a.75.75 0 0 1 0-1.06ZM10.78 5.78a.75.75 0 0 1-1.06 0L8 4.06 6.28 5.78a.75.75 0 0 1-1.06-1.06l2.25-2.25a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 0 1 0 1.06Z"
          clip-rule="evenodd"
          fill-rule="evenodd"
        />
      </svg>
    </button>

    <div
      v-if="isOpen"
      class="absolute left-0 z-30 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white shadow-lg outline-1 outline-black/5 dark:bg-gray-800 dark:shadow-none dark:-outline-offset-1 dark:outline-white/10"
    >
      <button
        v-for="option in props.options"
        :key="option.id"
        type="button"
        class="relative block w-full cursor-default py-2 pr-4 pl-8 text-left text-gray-900 select-none hover:bg-indigo-50 dark:text-white dark:hover:bg-indigo-500/20"
        @click="choose(option.id)"
      >
        <span
          class="block truncate text-sm"
          :class="option.id === props.modelValue ? 'font-semibold' : 'font-normal'"
        >
          {{ option.label }}
        </span>
        <span
          class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400"
        >
          {{ option.description || "Описание не задано." }}
        </span>
        <span
          v-if="option.id === props.modelValue"
          class="absolute inset-y-0 left-0 flex items-center pl-1.5 text-indigo-600 dark:text-indigo-400"
        >
          <AppIcon name="check" class="size-5" />
        </span>
      </button>
    </div>
  </div>
</template>
```

## 8.3. Все три modal-варианта добавления уроков

Создать файл `src/components/project/modals/CreateLessonModal.vue`:

```vue
<script setup lang="ts">
import { shallowRef } from "vue";
import { RouterLink } from "vue-router";
import type {
  PipelineVersionOption,
  YoutubeDuplicateLessonWarning,
} from "../../../types/ui";
import BaseDialog from "../../ui/BaseDialog.vue";
import PipelineVersionDropdown from "../PipelineVersionDropdown.vue";

const props = withDefaults(
  defineProps<{
    open: boolean;
    pipelineVersionOptions: PipelineVersionOption[];
    duplicateLessonWarning?: YoutubeDuplicateLessonWarning | null;
  }>(),
  {
    duplicateLessonWarning: null,
  },
);

const emit = defineEmits<{
  (event: "close"): void;
  (
    event: "save",
    payload: {
      lessonName: string;
      youtubeUrl: string;
      pipelineVersionId: number | null;
    },
  ): void;
}>();

const lessonName = shallowRef("");
const youtubeUrl = shallowRef("");
const pipelineVersionId = shallowRef<number | null>(
  props.pipelineVersionOptions[0]?.id ?? null,
);

function setPipelineVersionId(value: number | null): void {
  pipelineVersionId.value = value;
}

function submit(): void {
  emit("save", {
    lessonName: lessonName.value,
    youtubeUrl: youtubeUrl.value,
    pipelineVersionId: pipelineVersionId.value,
  });
}
</script>

<template>
  <BaseDialog :open="props.open" max-width-class="sm:max-w-lg" @close="emit('close')">
    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
          Добавить урок
        </h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Заполните название, ссылку на YouTube и версию шаблона.
        </p>
      </div>

      <div>
        <label for="lesson-name" class="block text-sm/6 font-medium text-gray-900 dark:text-white">
          Название урока
        </label>
        <div class="mt-2 grid grid-cols-1">
          <input
            id="lesson-name"
            v-model="lessonName"
            type="text"
            name="lesson_name"
            class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
          />
        </div>
      </div>

      <div>
        <label
          for="lesson-youtube-url"
          class="block text-sm/6 font-medium text-gray-900 dark:text-white"
        >
          Ссылка на YouTube
        </label>
        <div class="mt-2 grid grid-cols-1">
          <input
            id="lesson-youtube-url"
            v-model="youtubeUrl"
            type="url"
            name="lesson_youtube_url"
            class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
          />
        </div>

        <p
          v-if="props.duplicateLessonWarning"
          class="mt-2 text-sm text-amber-700 dark:text-amber-400"
        >
          Урок с таким видео уже есть:
          <RouterLink
            :to="props.duplicateLessonWarning.projectTo"
            class="font-semibold underline decoration-amber-500/70 underline-offset-2 hover:text-amber-600 dark:hover:text-amber-300"
          >
            {{ props.duplicateLessonWarning.projectName }} -
            {{ props.duplicateLessonWarning.lessonName }}
          </RouterLink>
        </p>
      </div>

      <div>
        <label
          for="lesson-pipeline-version"
          class="block text-sm/6 font-medium text-gray-900 dark:text-white"
        >
          Версия шаблона
        </label>
        <div class="mt-2">
          <PipelineVersionDropdown
            :model-value="pipelineVersionId"
            :options="props.pipelineVersionOptions"
            @update:model-value="setPipelineVersionId"
          />
        </div>
      </div>

      <div class="mt-10 sm:flex sm:flex-row-reverse">
        <button
          type="submit"
          class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400"
        >
          Сохранить
        </button>
        <button
          type="button"
          class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
          @click="emit('close')"
        >
          Отменить
        </button>
      </div>
    </form>
  </BaseDialog>
</template>
```

Создать файл `src/components/project/modals/AddLessonFromAudioModal.vue`:

```vue
<script setup lang="ts">
import { shallowRef } from "vue";
import type { PipelineVersionOption } from "../../../types/ui";
import BaseDialog from "../../ui/BaseDialog.vue";
import AppIcon from "../../ui/AppIcon.vue";
import PipelineVersionDropdown from "../PipelineVersionDropdown.vue";

const props = defineProps<{
  open: boolean;
  pipelineVersionOptions: PipelineVersionOption[];
}>();

const emit = defineEmits<{
  (event: "close"): void;
  (
    event: "save",
    payload: {
      lessonName: string;
      audioFile: File | null;
      pipelineVersionId: number | null;
    },
  ): void;
}>();

const lessonName = shallowRef("");
const audioFile = shallowRef<File | null>(null);
const selectedAudioFilename = shallowRef<string | null>(null);
const pipelineVersionId = shallowRef<number | null>(
  props.pipelineVersionOptions[0]?.id ?? null,
);
const isUploading = shallowRef(false);
const showUploadErrorNotification = shallowRef(false);

function setPipelineVersionId(value: number | null): void {
  pipelineVersionId.value = value;
}

function closeUploadErrorNotification(): void {
  showUploadErrorNotification.value = false;
}

function reloadPage(): void {
  window.location.reload();
}

function handleAudioFileChange(event: Event): void {
  const input = event.target as HTMLInputElement;
  const selectedFile = input.files?.[0] ?? null;

  audioFile.value = selectedFile;
  selectedAudioFilename.value = selectedFile?.name ?? null;
  showUploadErrorNotification.value = false;

  if (!selectedFile) {
    isUploading.value = false;
    return;
  }

  isUploading.value = true;

  window.setTimeout(() => {
    isUploading.value = false;
  }, 600);
}

function submit(): void {
  emit("save", {
    lessonName: lessonName.value,
    audioFile: audioFile.value,
    pipelineVersionId: pipelineVersionId.value,
  });
}
</script>

<template>
  <BaseDialog :open="props.open" max-width-class="sm:max-w-lg" @close="emit('close')">
    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
          Добавить урок из аудио
        </h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Укажите название, версию шаблона и загрузите аудиофайл.
        </p>
      </div>

      <div>
        <label
          for="audio-lesson-name"
          class="block text-sm/6 font-medium text-gray-900 dark:text-white"
        >
          Название урока
        </label>
        <div class="mt-2 grid grid-cols-1">
          <input
            id="audio-lesson-name"
            v-model="lessonName"
            type="text"
            name="audio_lesson_name"
            class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
          />
        </div>
      </div>

      <div>
        <label for="lesson-audio-file" class="block text-sm/6 font-medium text-gray-900 dark:text-white">
          Аудиофайл
        </label>
        <label
          for="lesson-audio-file"
          class="mt-2 relative flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50 px-6 py-0 text-center hover:border-indigo-400 hover:bg-indigo-50/30 dark:border-white/15 dark:bg-white/5 dark:hover:border-indigo-400/70 dark:hover:bg-indigo-500/10"
        >
          <input
            id="lesson-audio-file"
            type="file"
            accept=".mp3,.wav,.m4a,.aac,.ogg,.oga,.flac,.webm,.mp4,audio/*"
            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            @change="handleAudioFileChange"
          />

          <div class="py-20">
            <p class="text-sm font-medium text-gray-900 dark:text-white">
              Перетащите аудиофайл сюда или нажмите для выбора
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              MP3, WAV, M4A, AAC, OGG, FLAC, WEBM, MP4
            </p>
            <p
              v-if="selectedAudioFilename"
              class="mt-3 text-sm text-indigo-700 dark:text-indigo-300"
            >
              Выбран файл: {{ selectedAudioFilename }}
            </p>
          </div>
        </label>

        <div
          v-if="isUploading"
          class="mt-2 text-sm text-gray-600 dark:text-gray-300"
        >
          Загрузка файла...
        </div>
      </div>

      <div>
        <label
          for="audio-lesson-pipeline-version"
          class="block text-sm/6 font-medium text-gray-900 dark:text-white"
        >
          Версия шаблона
        </label>
        <div class="mt-2">
          <PipelineVersionDropdown
            :model-value="pipelineVersionId"
            :options="props.pipelineVersionOptions"
            @update:model-value="setPipelineVersionId"
          />
        </div>
      </div>

      <div class="mt-10 sm:flex sm:flex-row-reverse">
        <button
          type="submit"
          class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400"
        >
          Сохранить
        </button>
        <button
          type="button"
          class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
          @click="emit('close')"
        >
          Отменить
        </button>
      </div>
    </form>

    <template #after>
      <div aria-live="assertive" class="pointer-events-none fixed inset-0 flex items-end px-4 py-6 sm:items-start sm:p-6">
        <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
          <div
            v-if="showUploadErrorNotification"
            class="pointer-events-auto w-full max-w-sm translate-y-0 transform rounded-lg bg-white opacity-100 shadow-lg outline-1 outline-black/5 transition duration-300 ease-out sm:translate-x-0 dark:bg-gray-800 dark:-outline-offset-1 dark:outline-white/10"
          >
            <div class="p-4">
              <div class="flex items-start">
                <div class="shrink-0">
                  <AppIcon
                    name="alert-triangle"
                    class="size-6 text-red-500 dark:text-red-400"
                  />
                </div>
                <div class="ml-3 w-0 flex-1 pt-0.5">
                  <p class="text-sm font-medium text-gray-900 dark:text-white">
                    Не удалось загрузить файл
                  </p>
                  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Попробуйте выбрать файл ещё раз. Если ошибка повторится, обновите страницу и повторите загрузку.
                  </p>
                  <div class="mt-3 flex items-center gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center rounded-md bg-indigo-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
                      @click="reloadPage"
                    >
                      Обновить страницу
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center rounded-md px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                      @click="closeUploadErrorNotification"
                    >
                      Закрыть
                    </button>
                  </div>
                </div>
                <div class="ml-4 flex shrink-0">
                  <button
                    type="button"
                    class="inline-flex rounded-md text-gray-400 hover:text-gray-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:hover:text-white dark:focus:outline-indigo-500"
                    @click="closeUploadErrorNotification"
                  >
                    <span class="sr-only">Закрыть уведомление</span>
                    <AppIcon name="close" class="size-5" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </BaseDialog>
</template>
```

Создать файл `src/components/project/modals/AddLessonsListModal.vue`:

```vue
<script setup lang="ts">
import { shallowRef } from "vue";
import BaseDialog from "../../ui/BaseDialog.vue";

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (event: "close"): void;
  (event: "save", payload: { lessonsList: string }): void;
}>();

const lessonsList = shallowRef(`Урок 1
https://www.youtube.com/watch?v=...

Урок 2
https://www.youtube.com/watch?v=...`);

function submit(): void {
  emit("save", {
    lessonsList: lessonsList.value,
  });
}
</script>

<template>
  <BaseDialog :open="props.open" max-width-class="sm:max-w-2xl" @close="emit('close')">
    <form class="space-y-5" @submit.prevent="submit">
      <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
          Добавить список уроков
        </h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Каждый урок указывается парой строк: заголовок и ссылка на видео.
        </p>
      </div>

      <div>
        <label
          for="project-lessons-list"
          class="block text-sm/6 font-medium text-gray-900 dark:text-white"
        >
          Список уроков
        </label>
        <div class="mt-2 grid grid-cols-1">
          <textarea
            id="project-lessons-list"
            v-model="lessonsList"
            name="project_lessons_list"
            rows="10"
            class="col-start-1 row-start-1 block w-full rounded-md bg-white py-1.5 pr-3 pl-3 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:text-sm/6 dark:bg-white/5 dark:text-white dark:outline-white/10 dark:placeholder:text-gray-500 dark:focus:outline-indigo-500"
          />
        </div>
      </div>

      <div class="mt-10 sm:flex sm:flex-row-reverse">
        <button
          type="submit"
          class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 sm:ml-3 sm:w-auto dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400"
        >
          Сохранить
        </button>
        <button
          type="button"
          class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring-1 inset-ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
          @click="emit('close')"
        >
          Отменить
        </button>
      </div>
    </form>
  </BaseDialog>
</template>
```

Для визуальной проверки дополнительных состояний:

- в `CreateLessonModal` можно временно передать `youtubeDuplicateLessonWarningMock` вместо `null`, чтобы проверить amber-warning блок про дубликат YouTube-ссылки;
- в `AddLessonFromAudioModal` можно временно поставить `showUploadErrorNotification.value = true`, чтобы проверить toast об ошибке загрузки.

## 9. Правая колонка страницы проекта

Создать файл `src/components/project/ProjectActionsPanel.vue`:

```vue
<script setup lang="ts">
import type { LessonSortOption, LessonSortValue } from "../../types/ui";
import AppIcon from "../ui/AppIcon.vue";
import LessonsSortDropdown from "./LessonsSortDropdown.vue";

defineProps<{
  open: boolean;
  sort: LessonSortValue;
  sortOptions: LessonSortOption[];
}>();

const emit = defineEmits<{
  (event: "update:open", value: boolean): void;
  (event: "update:sort", value: LessonSortValue): void;
  (event: "add-lesson"): void;
  (event: "add-audio-lesson"): void;
  (event: "add-lessons-list"): void;
}>();

function closePanel(): void {
  emit("update:open", false);
}

function handleAddLesson(): void {
  closePanel();
  emit("add-lesson");
}

function handleAddAudioLesson(): void {
  closePanel();
  emit("add-audio-lesson");
}

function handleAddLessonsList(): void {
  closePanel();
  emit("add-lessons-list");
}

function handleSortChange(value: LessonSortValue): void {
  emit("update:sort", value);
  closePanel();
}
</script>

<template>
  <aside
    class="absolute top-0 right-0 left-0 z-20 md:order-2 md:static md:col-span-1 md:block"
    :class="{ hidden: !open }"
  >
    <div
      class="rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-gray-800 md:bg-transparent md:dark:bg-transparent md:p-0 md:shadow-none md:border-none"
    >
      <div class="space-y-3">
        <div class="space-y-2">
          <LessonsSortDropdown
            :model-value="sort"
            :options="sortOptions"
            @update:model-value="handleSortChange"
          />
        </div>

        <div class="space-y-2 border-t-0 border-gray-200 pt-3 dark:border-white/10">
          <button
            type="button"
            class="w-full text-sm rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
            @click="handleAddLesson"
          >
            Добавить урок
          </button>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
            @click="handleAddAudioLesson"
          >
            Добавить урок из аудио
          </button>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 text-sm rounded-lg bg-white px-3 py-2 font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
            @click="handleAddLessonsList"
          >
            <AppIcon name="list-plus" class="size-5" />
            Добавить список уроков
          </button>
        </div>
      </div>
    </div>
  </aside>
</template>
```

## 10. Список уроков на странице проекта

Создать файл `src/components/project/ProjectLessonsList.vue`:

```vue
<script setup lang="ts">
import type {
  LessonAudioStatus,
  LessonItem,
  PipelineRunStatus,
} from "../../types/ui";
import AppIcon from "../ui/AppIcon.vue";

defineProps<{
  lessons: LessonItem[];
}>();

function pipelineStatusIconName(status: PipelineRunStatus) {
  return (
    {
      done: "status-done",
      queued: "status-queued",
      running: "status-running",
      paused: "status-paused",
      failed: "status-failed",
      unknown: "status-unknown",
    } as const
  )[status];
}

function pipelineRunStatusBadgeClass(status: PipelineRunStatus): string {
  return (
    {
      done: "inline-flex items-center rounded-full bg-green-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-green-700 dark:bg-green-400/10 dark:text-green-400",
      queued:
        "inline-flex items-center rounded-full bg-gray-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400",
      running:
        "inline-flex items-center rounded-full bg-amber-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-amber-800 dark:bg-amber-400/10 dark:text-amber-300",
      paused:
        "inline-flex items-center rounded-full bg-sky-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-sky-700 dark:bg-sky-400/10 dark:text-sky-300",
      failed:
        "inline-flex items-center rounded-full bg-red-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-red-700 dark:bg-red-400/10 dark:text-red-400",
      unknown:
        "inline-flex items-center rounded-full bg-gray-100 -mr-1 p-0.5 text-xs font-medium whitespace-nowrap text-gray-600 dark:bg-gray-400/10 dark:text-gray-400",
    } as const
  )[status];
}

function lessonAudioDownloadIconClass(status: LessonAudioStatus): string {
  return (
    {
      failed: "text-red-500 dark:text-red-400",
      running: "text-yellow-500 dark:text-yellow-400",
      loaded: "text-green-500 dark:text-green-400",
      queued: "text-gray-500 dark:text-gray-400",
    } as const
  )[status];
}
</script>

<template>
  <div
    v-if="lessons.length === 0"
    class="rounded-lg border border-gray-200 bg-white px-6 py-6 shadow-sm dark:border-white/10 dark:bg-gray-800"
  >
    <p class="text-gray-600 dark:text-gray-300">В этом проекте пока нет уроков.</p>
  </div>

  <div
    v-else
    class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800 divide-y divide-gray-200 dark:divide-white/10"
  >
    <article
      v-for="lesson in lessons"
      :key="lesson.id"
      class="px-4 py-3 flex flex-col items-start gap-2 md:gap-3 md:pr-3 md:flex-row"
    >
      <div class="w-full flex items-start justify-between gap-3 md:w-2/3">
        <span class="text-base text-gray-900 dark:text-white">
          {{ lesson.name }}
        </span>

        <div class="mt-px flex items-center gap-1">
          <a
            v-if="lesson.sourceUrl"
            :href="lesson.sourceUrl"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center rounded-lg bg-white p-1 font-semibold text-gray-500 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/5 dark:hover:bg-white/20"
            aria-label="Открыть исходник урока"
          >
            <AppIcon name="external-link" class="size-4" />
          </a>

          <span
            :title="lesson.audioStatus === 'failed' ? lesson.audioErrorTooltip : undefined"
            :class="lessonAudioDownloadIconClass(lesson.audioStatus)"
          >
            <AppIcon name="audio-download" class="size-5" />
          </span>

          <span
            class="inline-block text-right w-10 min-w-10 shrink-0 whitespace-nowrap text-xs font-medium text-gray-500 dark:text-gray-400"
          >
            {{ lesson.audioDurationLabel ?? "" }}
          </span>
        </div>
      </div>

      <div class="w-full md:w-1/3 flex items-start gap-1">
        <p
          v-if="lesson.pipelineRuns.length === 0"
          class="w-full text-sm text-center py-0.5 text-gray-500 dark:text-gray-400"
        >
          Шаблонов пока нет
        </p>

        <div v-else class="min-w-0 space-y-1">
          <div
            v-for="pipelineRun in lesson.pipelineRuns"
            :key="pipelineRun.id"
            class="group relative min-w-0"
          >
            <div
              class="block min-w-0 rounded-lg border border-gray-200 bg-gray-100 px-3 py-0.5 dark:border-white/10 dark:bg-gray-900/50"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="truncate text-sm text-gray-700 dark:text-gray-200">
                  {{ pipelineRun.title }}
                  <span class="text-gray-500 dark:text-gray-400">
                    • {{ pipelineRun.versionLabel }}
                  </span>
                </span>

                <span :class="pipelineRunStatusBadgeClass(pipelineRun.status)">
                  <AppIcon
                    :name="pipelineStatusIconName(pipelineRun.status)"
                    class="size-4"
                  />
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </article>
  </div>
</template>
```

## 11. Список папок и проектов

Создать файл `src/components/projects/ProjectsFoldersList.vue`:

```vue
<script setup lang="ts">
import { useRouter } from "vue-router";
import type { FolderItem } from "../../types/ui";
import AppIcon from "../ui/AppIcon.vue";

const props = defineProps<{
  folders: FolderItem[];
}>();

const emit = defineEmits<{
  (event: "toggle-folder", folderId: number): void;
}>();

const router = useRouter();

function openProject(projectUrl: string): void {
  router.push(projectUrl);
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="props.folders.length === 0"
      class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800"
    >
      <p class="text-gray-600 dark:text-gray-300">Пока нет папок.</p>
    </div>

    <div
      v-else
      class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-800"
    >
      <div class="divide-y divide-gray-200 dark:divide-white/10">
        <section v-for="folder in props.folders" :key="folder.id">
          <div
            class="flex cursor-pointer items-center gap-3 px-3 py-3 md:px-5"
            @click="emit('toggle-folder', folder.id)"
          >
            <AppIcon
              :name="folder.isOpen ? 'folder-open' : 'folder-closed'"
              class="size-5"
              :class="
                folder.isOpen
                  ? 'text-indigo-600 dark:text-indigo-400'
                  : 'text-gray-600 dark:text-gray-300'
              "
            />

            <div class="flex-1 flex min-w-0 md:items-center justify-between gap-3">
              <div class="flex min-w-0 items-center gap-2">
                <p class="truncate font-semibold text-gray-900 dark:text-white">
                  {{ folder.name }}
                </p>

                <AppIcon
                  v-if="folder.isHidden"
                  name="folder-hidden"
                  class="size-5 shrink-0 text-gray-500 dark:text-gray-400"
                />

                <p class="hidden mt-0.5 shrink-0 text-sm text-gray-500 dark:text-gray-400 md:block">
                  Проектов: {{ folder.projectsCount }}
                </p>
              </div>
            </div>
          </div>

          <div v-if="folder.isOpen" class="border-t border-gray-200 dark:border-white/10">
            <div
              v-if="folder.projects.length === 0"
              class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400 md:px-5"
            >
              В этой папке пока нет проектов.
            </div>

            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                  <tr
                    v-for="project in folder.projects"
                    :key="project.id"
                    class="cursor-pointer transition hover:bg-gray-50 dark:hover:bg-white/5 bg-gray-100 dark:bg-gray-900/70"
                    @click="openProject(project.to)"
                  >
                    <td class="w-10 min-w-10 max-w-10 py-2 text-right md:w-17 md:min-w-17 md:max-w-17"></td>

                    <td
                      class="w-full max-w-0 px-3 py-2 text-sm font-medium text-gray-900 md:w-auto md:max-w-none dark:text-white"
                    >
                      <p class="truncate">{{ project.name }}</p>

                      <p class="font-normal md:hidden mt-1 leading-tight">
                        <span class="truncate inline-block mr-2 text-gray-500 dark:text-gray-400">
                          Уроков: {{ project.lessonsCount }}
                        </span>
                        <span class="truncate inline-block text-gray-500 dark:text-gray-400">
                          Длительность: {{ project.durationLabel }}
                        </span>
                      </p>
                    </td>

                    <td class="hidden px-4 py-2 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                      Уроков: {{ project.lessonsCount }}
                    </td>

                    <td class="hidden px-4 py-2 text-sm text-gray-700 md:table-cell dark:text-gray-300">
                      Длительность: {{ project.durationLabel }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
```

## 12. Моковые данные для проверки всего UI

Не пропускать этот шаг. Сначала собрать UI на этих данных, чтобы увидеть все состояния иконок. Только после этого подключать реальные данные.

Создать файл `src/data/mockUi.ts`:

```ts
import type {
  FolderItem,
  LessonSortOption,
  PipelineVersionOption,
  ProjectDetails,
  YoutubeDuplicateLessonWarning,
} from "../types/ui";

export const lessonSortOptionsMock: LessonSortOption[] = [
  {
    value: "created_at",
    label: "Сортировка по дате добавления",
  },
  {
    value: "name",
    label: "Сортировка по названию",
  },
];

export const pipelineVersionOptionsMock: PipelineVersionOption[] = [
  {
    id: 7,
    label: "Базовый шаблон • v5",
    description: "Расшифровка, чистка и базовая структура текста.",
  },
  {
    id: 8,
    label: "Глоссарий • v2",
    description: "Извлечение терминов и кратких пояснений.",
  },
  {
    id: 9,
    label: "Конспект • v1",
    description: "Краткий конспект с заголовками и тезисами.",
  },
];

export const youtubeDuplicateLessonWarningMock: YoutubeDuplicateLessonWarning = {
  projectName: "Модернизм",
  lessonName: "01. Введение",
  projectTo: "/projects/102",
};

export const foldersMock: FolderItem[] = [
  {
    id: 1,
    name: "История дизайна",
    projectsCount: 2,
    isOpen: true,
    projects: [
      {
        id: 101,
        name: "Баухаус",
        lessonsCount: 14,
        durationLabel: "08:30:12",
        to: "/projects/101",
      },
      {
        id: 102,
        name: "Модернизм",
        lessonsCount: 9,
        durationLabel: "05:12:48",
        to: "/projects/102",
      },
    ],
  },
  {
    id: 2,
    name: "Архитектура",
    projectsCount: 3,
    isOpen: false,
    projects: [
      {
        id: 201,
        name: "Японский метаболизм",
        lessonsCount: 7,
        durationLabel: "03:41:10",
        to: "/projects/201",
      },
      {
        id: 202,
        name: "Брутализм",
        lessonsCount: 11,
        durationLabel: "06:01:27",
        to: "/projects/202",
      },
      {
        id: 203,
        name: "Постмодернизм",
        lessonsCount: 5,
        durationLabel: "02:54:09",
        to: "/projects/203",
      },
    ],
  },
  {
    id: 3,
    name: "Скрытые проекты",
    projectsCount: 1,
    isOpen: false,
    isHidden: true,
    projects: [
      {
        id: 301,
        name: "Внутренний архив",
        lessonsCount: 4,
        durationLabel: "01:48:33",
        to: "/projects/301",
      },
    ],
  },
];

export const projectMock: ProjectDetails = {
  id: 101,
  name: "Баухаус",
  durationLabel: "08:30:12",
  lessons: [
    {
      id: 1,
      name: "01. Введение",
      createdAtOrder: 1,
      sourceUrl: "https://example.com/source/lesson-1",
      audioStatus: "loaded",
      audioDurationLabel: "18:24",
      pipelineRuns: [
        {
          id: 1001,
          title: "Базовый шаблон",
          versionLabel: "v5",
          status: "done",
        },
      ],
    },
    {
      id: 2,
      name: "02. Ранний период",
      createdAtOrder: 2,
      sourceUrl: "https://example.com/source/lesson-2",
      audioStatus: "running",
      pipelineRuns: [
        {
          id: 1002,
          title: "Базовый шаблон",
          versionLabel: "v5",
          status: "running",
        },
      ],
    },
    {
      id: 3,
      name: "03. Школа и преподаватели",
      createdAtOrder: 3,
      sourceUrl: "https://example.com/source/lesson-3",
      audioStatus: "failed",
      audioErrorTooltip: "Ошибка загрузки аудио",
      pipelineRuns: [
        {
          id: 1003,
          title: "Базовый шаблон",
          versionLabel: "v5",
          status: "failed",
        },
      ],
    },
    {
      id: 4,
      name: "04. Типографика",
      createdAtOrder: 4,
      sourceUrl: "https://example.com/source/lesson-4",
      audioStatus: "queued",
      pipelineRuns: [
        {
          id: 1004,
          title: "Базовый шаблон",
          versionLabel: "v5",
          status: "queued",
        },
        {
          id: 1005,
          title: "Глоссарий",
          versionLabel: "v2",
          status: "paused",
        },
      ],
    },
    {
      id: 5,
      name: "05. Наследие",
      createdAtOrder: 5,
      sourceUrl: "https://example.com/source/lesson-5",
      audioStatus: "loaded",
      audioDurationLabel: "24:51",
      pipelineRuns: [],
    },
  ],
};
```

## 13. Страница проектов

Создать файл `src/views/ProjectsView.vue`:

```vue
<script setup lang="ts">
import { shallowRef } from "vue";
import type { BreadcrumbItem, FolderItem } from "../types/ui";
import { foldersMock } from "../data/mockUi";
import AppShell from "../components/ui/AppShell.vue";
import ProjectsFoldersList from "../components/projects/ProjectsFoldersList.vue";

const breadcrumbs: BreadcrumbItem[] = [
  {
    label: "Проекты",
    current: true,
  },
];

const folders = shallowRef<FolderItem[]>(foldersMock);

function toggleFolder(folderId: number): void {
  folders.value = folders.value.map((folder) => {
    if (folder.id === folderId) {
      return {
        ...folder,
        isOpen: !folder.isOpen,
      };
    }

    return folder;
  });
}

function openSettings(): void {
  window.dispatchEvent(new CustomEvent("video2book:settings"));
}
</script>

<template>
  <AppShell :breadcrumbs="breadcrumbs" @settings="openSettings">
    <ProjectsFoldersList :folders="folders" @toggle-folder="toggleFolder" />
  </AppShell>
</template>
```

## 14. Страница проекта

Создать файл `src/views/ProjectView.vue`:

```vue
<script setup lang="ts">
import { computed, shallowRef } from "vue";
import type {
  BreadcrumbItem,
  LessonItem,
  LessonSortValue,
} from "../types/ui";
import {
  lessonSortOptionsMock,
  pipelineVersionOptionsMock,
  projectMock,
} from "../data/mockUi";
import AddLessonFromAudioModal from "../components/project/modals/AddLessonFromAudioModal.vue";
import AddLessonsListModal from "../components/project/modals/AddLessonsListModal.vue";
import CreateLessonModal from "../components/project/modals/CreateLessonModal.vue";
import AppIcon from "../components/ui/AppIcon.vue";
import AppShell from "../components/ui/AppShell.vue";
import ProjectActionsPanel from "../components/project/ProjectActionsPanel.vue";
import ProjectLessonsList from "../components/project/ProjectLessonsList.vue";

const project = shallowRef(projectMock);
const actionsMenuOpen = shallowRef(false);
const lessonSort = shallowRef<LessonSortValue>("created_at");
const showCreateLessonModal = shallowRef(false);
const showAddLessonFromAudioModal = shallowRef(false);
const showAddLessonsListModal = shallowRef(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  {
    label: "Проекты",
    to: "/projects",
  },
  {
    label: project.value.name,
    current: true,
  },
]);

const sortedLessons = computed<LessonItem[]>(() => {
  if (lessonSort.value === "name") {
    return [...project.value.lessons].sort((left, right) =>
      left.name.localeCompare(right.name, "ru"),
    );
  }

  return [...project.value.lessons].sort(
    (left, right) => left.createdAtOrder - right.createdAtOrder,
  );
});

function openSettings(): void {
  window.dispatchEvent(new CustomEvent("video2book:settings"));
}

function handleAddLesson(): void {
  setActionsMenuOpen(false);
  showCreateLessonModal.value = true;
}

function handleAddAudioLesson(): void {
  setActionsMenuOpen(false);
  showAddLessonFromAudioModal.value = true;
}

function handleAddLessonsList(): void {
  setActionsMenuOpen(false);
  showAddLessonsListModal.value = true;
}

function toggleActionsMenu(): void {
  actionsMenuOpen.value = !actionsMenuOpen.value;
}

function setActionsMenuOpen(value: boolean): void {
  actionsMenuOpen.value = value;
}

function setLessonSort(value: LessonSortValue): void {
  lessonSort.value = value;
}

function closeCreateLessonModal(): void {
  showCreateLessonModal.value = false;
}

function closeAddLessonFromAudioModal(): void {
  showAddLessonFromAudioModal.value = false;
}

function closeAddLessonsListModal(): void {
  showAddLessonsListModal.value = false;
}

function saveCreateLesson(payload: {
  lessonName: string;
  youtubeUrl: string;
  pipelineVersionId: number | null;
}): void {
  window.dispatchEvent(new CustomEvent("video2book:create-lesson", { detail: payload }));
  closeCreateLessonModal();
}

function saveLessonFromAudio(payload: {
  lessonName: string;
  audioFile: File | null;
  pipelineVersionId: number | null;
}): void {
  window.dispatchEvent(new CustomEvent("video2book:create-audio-lesson", { detail: payload }));
  closeAddLessonFromAudioModal();
}

function saveLessonsList(payload: { lessonsList: string }): void {
  window.dispatchEvent(new CustomEvent("video2book:create-lessons-list", { detail: payload }));
  closeAddLessonsListModal();
}
</script>

<template>
  <AppShell :breadcrumbs="breadcrumbs" @settings="openSettings">
    <div class="space-y-6">
      <div class="mx-2 md:mx-6 flex items-center justify-between gap-3">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
          {{ project.name }}
          <span
            v-if="project.durationLabel"
            class="md:ml-3 inline-block tracking-normal text-lg font-normal text-gray-500 dark:text-gray-400"
          >
            Длительность {{ project.durationLabel }}
          </span>
        </h1>

        <button
          type="button"
          class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white dark:focus:outline-indigo-500 md:hidden"
          :aria-expanded="actionsMenuOpen ? 'true' : 'false'"
          @click="toggleActionsMenu"
        >
          <span class="absolute -inset-0.5"></span>
          <span class="sr-only">Открыть меню действий проекта</span>
          <AppIcon v-if="!actionsMenuOpen" name="menu" class="size-6" />
          <AppIcon v-else name="close" class="size-6" />
        </button>
      </div>

      <div
        v-if="actionsMenuOpen"
        class="fixed inset-0 z-10 md:hidden"
        @click="setActionsMenuOpen(false)"
      />

      <div class="relative grid grid-cols-1 gap-6 md:grid-cols-3">
        <ProjectActionsPanel
          :open="actionsMenuOpen"
          :sort="lessonSort"
          :sort-options="lessonSortOptionsMock"
          @update:open="setActionsMenuOpen"
          @update:sort="setLessonSort"
          @add-lesson="handleAddLesson"
          @add-audio-lesson="handleAddAudioLesson"
          @add-lessons-list="handleAddLessonsList"
        />

        <section class="md:order-1 md:col-span-2">
          <ProjectLessonsList :lessons="sortedLessons" />
        </section>
      </div>

      <CreateLessonModal
        :open="showCreateLessonModal"
        :pipeline-version-options="pipelineVersionOptionsMock"
        :duplicate-lesson-warning="null"
        @close="closeCreateLessonModal"
        @save="saveCreateLesson"
      />

      <AddLessonFromAudioModal
        :open="showAddLessonFromAudioModal"
        :pipeline-version-options="pipelineVersionOptionsMock"
        @close="closeAddLessonFromAudioModal"
        @save="saveLessonFromAudio"
      />

      <AddLessonsListModal
        :open="showAddLessonsListModal"
        @close="closeAddLessonsListModal"
        @save="saveLessonsList"
      />
    </div>
  </AppShell>
</template>
```

## 15. Router

Создать файл `src/router/index.ts`:

```ts
import { createRouter, createWebHashHistory } from "vue-router";
import ProjectView from "../views/ProjectView.vue";
import ProjectsView from "../views/ProjectsView.vue";

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    {
      path: "/",
      redirect: "/projects",
    },
    {
      path: "/projects",
      name: "projects",
      component: ProjectsView,
    },
    {
      path: "/projects/:projectId",
      name: "project",
      component: ProjectView,
    },
  ],
});

export default router;
```

## 16. Root app и entrypoint

Создать файл `src/App.vue`:

```vue
<template>
  <RouterView />
</template>
```

Создать файл `src/main.ts`:

```ts
import { createApp } from "vue";
import App from "./App.vue";
import "./assets/main.css";
import router from "./router";
import { initializeTheme } from "./composables/useTheme";

initializeTheme();

createApp(App).use(router).mount("#app");
```

## 17. Что должно получиться визуально

### Страница проектов

- Сразу под breadcrumbs идёт карточка со списком папок.
- Никакого заголовка страницы сверху нет.
- Никаких кнопок действий над списком нет.
- Каждая папка:
  - открывается и закрывается по клику;
  - показывает иконку открытой или закрытой папки;
  - показывает название папки;
  - показывает счётчик `Проектов: N`;
  - если папка скрытая, показывает иконку скрытой папки.
- Внутри раскрытой папки:
  - таблица проектов остаётся такой же, как сейчас;
  - первая узкая колонка остаётся пустой;
  - строки подсвечиваются на hover;
  - на mobile метрики `Уроков` и `Длительность` переезжают под названием;
  - drag icon нет.

### Страница проекта

- Сверху:
  - название проекта;
  - рядом строка `Длительность XX:XX:XX`;
  - на mobile справа кнопка-гамбургер.
- Основная сетка:
  - слева уроки;
  - справа actions-панель.
- В actions-панели:
  - dropdown сортировки со sort-иконкой;
  - кнопка `Добавить урок`;
  - кнопка `Добавить урок из аудио`;
  - кнопка `Добавить список уроков` с иконкой.
- На странице проекта должны быть подключены 3 модалки:
  - `Добавить урок`;
  - `Добавить урок из аудио`;
  - `Добавить список уроков`.
- Модалки должны использовать тот же overlay, тот же white-card shell, те же кнопки `Сохранить` и `Отменить`, что и в исходном UI.
- Ширина модалок:
  - `Добавить урок` -> `sm:max-w-lg`;
  - `Добавить урок из аудио` -> `sm:max-w-lg`;
  - `Добавить список уроков` -> `sm:max-w-2xl`.
- В строке урока:
  - слева название;
  - справа в верхней части: ссылка-исходник, иконка аудио, длительность;
  - справа в нижней части: карточки статусов шаблонов.
- Карточки шаблонов:
  - должны выглядеть как сейчас;
  - должны оставаться read-only;
  - не должны вести на третью страницу.

## 18. Карта соответствия иконок и цветов

### Иконки папок

- Открытая папка:
  - `AppIcon name="folder-open"`
  - цвет `text-indigo-600 dark:text-indigo-400`
- Закрытая папка:
  - `AppIcon name="folder-closed"`
  - цвет `text-gray-600 dark:text-gray-300`

### Иконка скрытой папки

- `AppIcon name="folder-hidden"`
- цвет `text-gray-500 dark:text-gray-400`

### Иконка исходника урока

- `AppIcon name="external-link"`
- кнопка на белом фоне с inset-ring, как в текущем приложении.

### Иконка загрузки аудио

- `AppIcon name="audio-download"`
- цвета строго такие:
  - `loaded` -> `text-green-500 dark:text-green-400`
  - `running` -> `text-yellow-500 dark:text-yellow-400`
  - `failed` -> `text-red-500 dark:text-red-400`
  - `queued` -> `text-gray-500 dark:text-gray-400`

### Иконки статусов шаблона

- `done` -> `AppIcon name="status-done"`
- `queued` -> `AppIcon name="status-queued"`
- `running` -> `AppIcon name="status-running"`
- `paused` -> `AppIcon name="status-paused"`
- `failed` -> `AppIcon name="status-failed"`
- fallback -> `AppIcon name="status-unknown"`

### Цвета badge статусов шаблона

- `done`
  - `bg-green-100 text-green-700 dark:bg-green-400/10 dark:text-green-400`
- `queued`
  - `bg-gray-100 text-gray-600 dark:bg-gray-400/10 dark:text-gray-400`
- `running`
  - `bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-300`
- `paused`
  - `bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300`
- `failed`
  - `bg-red-100 text-red-700 dark:bg-red-400/10 dark:text-red-400`

## 19. Что запрещено делать

- Не добавлять никакие menu items в header.
- Не возвращать кнопку `Добавить папку`.
- Не возвращать заголовок `Проекты`.
- Не возвращать кнопки `Изменить` и `Добавить проект` у папки.
- Не возвращать drag and drop.
- Не возвращать drag-handle иконку.
- Не заменять modal shell на нативные `dialog`, системные окна Electron или стороннюю библиотеку.
- Не возвращать кнопки:
  - `Редактировать проект`;
  - `Пересчитать длительность`;
  - `Скачать проект в PDF`;
  - `Скачать проект в MD`;
  - `Скачать проект в DOCX`;
  - `Удалить проект`;
  - `Изменить название урока`;
  - `Удалить урок`;
  - `Добавить версию шаблона`.
- Не делать третью страницу прогона.
- Не заменять текущие серые, белые и indigo-цвета на другие оттенки.

## 20. Порядок сборки

Делать именно так:

1. Скопировать `favicon.png`.
2. Вставить `main.css`.
3. Вставить `ui.ts`.
4. Вставить `useTheme.ts`.
5. Вставить `AppIcon.vue`.
6. Вставить `AppHeader.vue`, `AppBreadcrumbs.vue`, `AppShell.vue`, `BaseDialog.vue`.
7. Вставить `LessonsSortDropdown.vue` и `PipelineVersionDropdown.vue`.
8. Вставить `ProjectActionsPanel.vue`.
9. Вставить три modal-компонента добавления уроков.
10. Вставить `ProjectLessonsList.vue`.
11. Вставить `ProjectsFoldersList.vue`.
12. Вставить `mockUi.ts`.
13. Вставить `ProjectsView.vue` и `ProjectView.vue`.
14. Вставить `router/index.ts`, `App.vue`, `main.ts`.
15. Сначала проверить, что интерфейс полностью совпадает на mock data.
16. Только потом заменить mock data на реальные данные из клиента.

## 21. Финальный чек-лист совпадения

Если хотя бы один пункт не выполнен, UI считается недовоспроизведённым.

- В шапке только 3 элемента: логотип, theme switcher, settings.
- Breadcrumbs выглядят как в текущем приложении.
- На странице проектов нет заголовка и нет верхних кнопок.
- У папок есть только toggle-поведение и счётчики.
- У открытой папки нет кнопок действий.
- У проекта внутри папки нет drag icon.
- На странице проекта сохранён layout `2/3 + 1/3`.
- На mobile у страницы проекта есть hamburger-кнопка действий.
- В правой колонке проекта только 4 действия.
- Все 3 модалки открытия уроков подключены к кнопкам и открываются поверх страницы проекта.
- Модалка YouTube-урока содержит 3 поля: название, YouTube URL, версия шаблона.
- Модалка аудио-урока содержит 3 поля: название, dropzone/файл, версия шаблона.
- Модалка списка уроков содержит textarea с таким же текстом-подсказкой, как в текущем приложении.
- У урока осталась ссылка на исходник.
- У урока осталась иконка загрузки аудио.
- У урока остались карточки статусов шаблонов.
- У урока нет edit/delete/add-template контролов.
- Статусы `done/queued/running/paused/failed` имеют отдельные SVG и свои цвета.
- Тёмная тема переключается через localStorage ключ `video2book:theme`.
- Используются только Tailwind CSS и обычный Vue-код.

## 22. Как сверять результат

Если при интеграции с реальными данными нужен референс, сверять итог только с визуалом работающего исходного приложения.

Не переписывать эту инструкцию под внутренние пути, имена папок или структуру другого проекта. Этот markdown должен оставаться переносимым и самодостаточным для отдельного клиентского репозитория.
