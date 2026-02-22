<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-100 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Video2Book') }}</title>
    <script>
        (() => {
            const themeKey = 'video2book:theme';

            try {
                const storedTheme = window.localStorage.getItem(themeKey);
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = storedTheme === 'light' || storedTheme === 'dark'
                    ? storedTheme
                    : (prefersDark ? 'dark' : 'light');

                document.documentElement.classList.toggle('dark', theme === 'dark');
            } catch (error) {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="h-full bg-gray-100 text-gray-900 dark:bg-gray-900 dark:text-gray-100">
<div class="min-h-full">
    <nav class="border-b border-gray-200 bg-white dark:border-white/10 dark:bg-gray-800/50">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex">
                    <div class="flex shrink-0 items-center">
                        <img src="{{ asset('favicon.png') }}" alt="Video2Book" class="h-8 w-auto">
                    </div>
                    <div class="hidden sm:-my-px sm:ml-6 sm:flex sm:space-x-8">
                        @foreach (config('navigation.main', []) as $item)
                            <a href="{{ route($item['route']) }}"
                               wire:navigate
                               data-menu-item="{{ $item['key'] }}"
                               data-active="{{ request()->routeIs($item['active']) ? 'true' : 'false' }}"
                               aria-current="{{ request()->routeIs($item['active']) ? 'page' : 'false' }}"
                               class="inline-flex items-center border-b-2 px-1 pt-1 font-medium {{ request()->routeIs($item['active']) ? 'border-indigo-600 text-gray-900 dark:border-indigo-500 dark:text-white' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200' }}">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center sm:gap-3">
                    <div class="inline-flex items-center gap-0.5 rounded-full bg-gray-900/5 p-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"
                         role="group"
                         aria-label="Theme switcher">
                        <button type="button"
                                data-theme-set="light"
                                aria-pressed="false"
                                class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500">
                            <span class="sr-only">Switch to light theme</span>
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.5"
                                 aria-hidden="true"
                                 class="size-5">
                                <path d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364-1.06-1.06M6.697 6.697 5.636 5.636m12.728 0-1.06 1.06M6.697 17.303l-1.06 1.06M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <button type="button"
                                data-theme-set="dark"
                                aria-pressed="false"
                                class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500">
                            <span class="sr-only">Switch to dark theme</span>
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.5"
                                 aria-hidden="true"
                                 class="size-5">
                                <path d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.599.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>

                    <button type="button"
                            data-settings-trigger
                            class="relative rounded-full p-1 text-gray-500 hover:text-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-300 dark:hover:text-white dark:focus:outline-indigo-500">
                        <span class="absolute -inset-1.5"></span>
                        <span class="sr-only">Open settings</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                <div class="-mr-2 flex items-center sm:hidden">
                    <button type="button"
                            class="relative inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white dark:focus:outline-indigo-500"
                            data-mobile-toggle="mobile-menu"
                            aria-expanded="false">
                        <span class="absolute -inset-0.5"></span>
                        <span class="sr-only">Open main menu</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"
                             class="size-6 menu-icon-open">
                            <path d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"
                             class="size-6 menu-icon-close hidden">
                            <path d="M6 18 18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden sm:hidden">
            <div class="space-y-1 pt-2 pb-3">
                @foreach (config('navigation.main', []) as $item)
                    <a href="{{ route($item['route']) }}"
                       wire:navigate
                       data-menu-item="{{ $item['key'] }}"
                       data-active="{{ request()->routeIs($item['active']) ? 'true' : 'false' }}"
                       aria-current="{{ request()->routeIs($item['active']) ? 'page' : 'false' }}"
                       class="block border-l-4 py-2 pr-4 pl-3 font-medium {{ request()->routeIs($item['active']) ? 'border-indigo-600 bg-indigo-50 text-indigo-700 dark:border-indigo-500 dark:bg-indigo-600/10 dark:text-indigo-300' : 'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:border-white/20 dark:hover:bg-white/5 dark:hover:text-gray-200' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
            <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="inline-flex items-center gap-0.5 rounded-full bg-gray-900/5 p-0.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"
                         role="group"
                         aria-label="Theme switcher mobile">
                        <button type="button"
                                data-theme-set="light"
                                aria-pressed="false"
                                class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500">
                            <span class="sr-only">Switch to light theme</span>
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.5"
                                 aria-hidden="true"
                                 class="size-5">
                                <path d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364-1.06-1.06M6.697 6.697 5.636 5.636m12.728 0-1.06 1.06M6.697 17.303l-1.06 1.06M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <button type="button"
                                data-theme-set="dark"
                                aria-pressed="false"
                                class="rounded-full p-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 dark:focus-visible:ring-indigo-500">
                            <span class="sr-only">Switch to dark theme</span>
                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="1.5"
                                 aria-hidden="true"
                                 class="size-5">
                                <path d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.599.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"
                                      stroke-linecap="round"
                                      stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>

                    <button type="button"
                            data-settings-trigger
                            class="relative rounded-full p-1 text-gray-500 hover:text-gray-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600 dark:text-gray-300 dark:hover:text-white dark:focus:outline-indigo-500">
                        <span class="absolute -inset-1.5"></span>
                        <span class="sr-only">Open settings</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="pb-10">
        <header>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                @if (! empty($breadcrumbs))
                    <nav aria-label="Breadcrumb" data-breadcrumbs class="py-4 flex">
                        <ol role="list"
                            class="flex flex-wrap items-center gap-x-3 gap-y-2 pl-7 text-gray-500 dark:text-gray-400 md:flex-nowrap md:gap-y-0 mc:pl-0">
                            <li class="-ml-7 shrink-0 md:ml-0">
                                <a href="{{ route('home') }}"
                                   wire:navigate
                                   class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0"
                                         aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5"/>
                                    </svg>
                                    <span class="sr-only">Главная</span>
                                </a>
                            </li>
                            @foreach (($breadcrumbs ?? []) as $breadcrumb)
                                <li class="flex min-w-0 max-w-full flex-nowrap items-center gap-3 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5" stroke="currentColor"
                                         class="size-4 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 5.25 7.5 6.75-7.5 6.75"/>
                                    </svg>
                                    @if (($breadcrumb['current'] ?? false) || empty($breadcrumb['url']))
                                        <span class="max-w-full font-medium text-gray-700 dark:text-gray-200"
                                              @if ($breadcrumb['current'] ?? false) aria-current="page" @endif>
                                            {{ $breadcrumb['label'] }}
                                        </span>
                                    @else
                                        <a href="{{ $breadcrumb['url'] }}"
                                           wire:navigate
                                           class="max-w-full font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                            {{ $breadcrumb['label'] }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @else
                    <div class="h-6"></div>
                @endif
            </div>
        </header>
        <main>
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

@livewireScripts
</body>
</html>
